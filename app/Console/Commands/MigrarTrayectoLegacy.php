<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Migra las 221 inscripciones del Trayecto Formativo desde el MySQL de Sage
 * (bdsuperior.tb_trayecto_formativo + rel_documentos_trayecto, cruzadas con
 * sage.tb_agentes) hacia tb_trayecto_formativo en PostgreSQL, con cohorte=2025.
 *
 * Reglas aplicadas (acordadas en Fase 3):
 *  - nivel se traduce del string largo de Sage al valor normalizado corto
 *    ('Nivel Primario' -> 'Primario', etc.). Un valor no reconocido se
 *    saltea (no se inserta con nivel inválido) y se reporta al final.
 *  - legacy_id = idTrayectoFormativo original: permite reejecutar el comando
 *    sin duplicar (upsert por legacy_id, respaldado por el índice único parcial).
 *  - Los documentos (f2/certificacion_servicio/concepto) vienen de
 *    rel_documentos_trayecto, que en Sage guarda 1 sola fila por `documento`
 *    (DNI) sin importar cuántas inscripciones tenga esa persona. Como el JOIN
 *    se hace por Documento para cada fila de origen, un DNI con 2 filas
 *    (nivel Primario) recibe automáticamente el mismo path en ambas filas
 *    nuevas — no hace falta lógica extra para "replicar" el documento.
 *  - No se copian archivos físicos en esta corrida (--dry-run o real): solo
 *    se guarda el path tal cual estaba en Sage. La copia de archivos es un
 *    paso aparte, pendiente de que se confirme el origen de los PDFs reales.
 */
class MigrarTrayectoLegacy extends Command
{
    protected $signature = 'trayecto:migrar-legacy {--dry-run : No escribe nada en la base, solo muestra lo que haría}';

    protected $description = 'Migra las inscripciones legacy del Trayecto Formativo (Sage/MySQL) a tb_trayecto_formativo (Postgres), cohorte 2025';

    private const COHORTE = 2025;

    private const MAPA_NIVELES = [
        'Nivel Inicial'      => 'Inicial',
        'Nivel Primario'     => 'Primario',
        'Nivel Secundario'   => 'Secundario',
        'Educación Especial' => 'Especial',
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $this->info($dryRun ? 'Modo DRY-RUN: no se va a escribir nada en la base.' : 'Modo REAL: se va a escribir en tb_trayecto_formativo.');

        $filas = DB::connection('sage_mysql')->select("
            SELECT
                t.idTrayectoFormativo, t.idAgente, t.nivel, t.estamento,
                a.ApeNom, a.Documento, a.telefono, a.email, a.fecha_nac,
                a.Barrio, a.Calle, a.Numero_Casa,
                d.f2 AS doc_f2, d.certificacion_servicio AS doc_cert, d.concepto AS doc_concepto
            FROM bdsuperior.tb_trayecto_formativo t
            LEFT JOIN sage.tb_agentes a ON a.idAgente = t.idAgente
            LEFT JOIN bdsuperior.rel_documentos_trayecto d ON d.documento = a.Documento
            ORDER BY t.idTrayectoFormativo
        ");

        $this->info('Filas leídas desde Sage: ' . count($filas));

        $insertadas = 0;
        $actualizadas = 0;
        $saltadas = [];
        $apenomNoParseable = [];
        $filasPorDni = []; // dni => [ ['legacy_id'=>, 'f2'=>, 'cert'=>, 'concepto'=>], ... ]

        foreach ($filas as $row) {
            if (empty($row->Documento)) {
                $saltadas[] = [
                    'legacy_id' => $row->idTrayectoFormativo,
                    'motivo'    => 'idAgente huérfano (no se encontró agente/documento en sage.tb_agentes)',
                    'nivel'     => $row->nivel,
                ];
                continue;
            }

            if (!isset(self::MAPA_NIVELES[$row->nivel])) {
                $saltadas[] = [
                    'legacy_id' => $row->idTrayectoFormativo,
                    'motivo'    => "nivel no reconocido: \"{$row->nivel}\"",
                    'nivel'     => $row->nivel,
                ];
                continue;
            }

            $nivelNormalizado = self::MAPA_NIVELES[$row->nivel];

            [$apellido, $nombre] = $this->separarApeNom($row->ApeNom);
            if ($nombre === null) {
                $apenomNoParseable[] = ['legacy_id' => $row->idTrayectoFormativo, 'dni' => $row->Documento, 'apenom' => $row->ApeNom];
                $nombre = '';
            }

            $domicilio = trim(($row->Calle ?? '') . ' ' . ($row->Numero_Casa ?? ''));
            $domicilio = $domicilio !== '' ? $domicilio : null;

            $data = [
                'cohorte'                     => self::COHORTE,
                'dni'                         => trim($row->Documento),
                'apellido'                    => $apellido,
                'nombre'                      => $nombre,
                'telefono'                    => $row->telefono ?: null,
                'email'                       => $row->email ?: null,
                'fecha_nac'                   => $row->fecha_nac,
                'domicilio'                   => $domicilio,
                'barrio'                      => $row->Barrio ?: null,
                'nivel'                       => $nivelNormalizado,
                'estamento'                   => $row->estamento,
                'f2_path'                     => $row->doc_f2,
                'certificacion_servicio_path' => $row->doc_cert,
                'concepto_path'               => $row->doc_concepto,
                'legacy_id'                   => $row->idTrayectoFormativo,
                'updated_at'                  => now(),
            ];

            $filasPorDni[$data['dni']][] = [
                'legacy_id' => $row->idTrayectoFormativo,
                'f2'        => $row->doc_f2,
                'cert'      => $row->doc_cert,
                'concepto'  => $row->doc_concepto,
            ];

            if ($dryRun) {
                $this->line("[DRY-RUN] legacy_id={$row->idTrayectoFormativo} dni={$data['dni']} nivel={$nivelNormalizado} estamento=\"{$row->estamento}\"");
                continue;
            }

            $existente = DB::table('tb_trayecto_formativo')->where('legacy_id', $row->idTrayectoFormativo)->first();

            if ($existente) {
                DB::table('tb_trayecto_formativo')->where('legacy_id', $row->idTrayectoFormativo)->update($data);
                $actualizadas++;
            } else {
                $data['created_at'] = now();
                DB::table('tb_trayecto_formativo')->insert($data);
                $insertadas++;
            }
        }

        $this->newLine();
        $this->info('══════════════ RESUMEN ══════════════');
        $this->info('Insertadas: ' . $insertadas);
        $this->info('Actualizadas: ' . $actualizadas);
        $this->info('Saltadas: ' . count($saltadas));

        foreach ($saltadas as $s) {
            $this->warn("  - legacy_id={$s['legacy_id']} · {$s['motivo']}");
        }

        if ($apenomNoParseable) {
            $this->warn('ApeNom no parseable como "Apellido, Nombre" (se migró con nombre vacío, revisar manualmente):');
            foreach ($apenomNoParseable as $a) {
                $this->warn("  - legacy_id={$a['legacy_id']} dni={$a['dni']} ApeNom=\"{$a['apenom']}\"");
            }
        }

        // Verificación de la regla de negocio + consistencia de documentos entre filas Primario
        $conDosFilas = collect($filasPorDni)->filter(fn ($f) => count($f) === 2);
        $conUnaFila  = collect($filasPorDni)->filter(fn ($f) => count($f) === 1);
        $conMasDeDos = collect($filasPorDni)->filter(fn ($f) => count($f) > 2);

        $this->newLine();
        $this->info('DNIs con 1 inscripción: ' . $conUnaFila->count());
        $this->info('DNIs con 2 inscripciones (Primario): ' . $conDosFilas->count());

        if ($conMasDeDos->count() > 0) {
            $this->error('DNIs con MÁS de 2 inscripciones (no debería pasar, revisar la regla de negocio en el origen): ' . $conMasDeDos->count());
            foreach ($conMasDeDos as $dni => $f) {
                $this->error('  - dni=' . $dni . ' (' . count($f) . ' filas, legacy_ids: ' . implode(',', array_column($f, 'legacy_id')) . ')');
            }
        }

        $replicadoOk = 0;
        $replicadoMal = [];
        foreach ($conDosFilas as $dni => $f) {
            $combos = collect($f)->map(fn ($x) => $x['f2'] . '|' . $x['cert'] . '|' . $x['concepto'])->unique();
            if ($combos->count() === 1) {
                $replicadoOk++;
            } else {
                $replicadoMal[] = $dni;
            }
        }

        $this->info("  - con documento(s) replicado(s) de forma consistente en ambas filas: {$replicadoOk} / {$conDosFilas->count()}");
        if ($replicadoMal) {
            $this->error('  - con documentos DISTINTOS entre las 2 filas (revisar manualmente): ' . implode(', ', $replicadoMal));
        }

        return self::SUCCESS;
    }

    /**
     * Separa "APELLIDO, Nombre" en [apellido, nombre]. Devuelve nombre=null
     * si el formato no tiene coma (no parseable de forma confiable).
     */
    private function separarApeNom(?string $apeNom): array
    {
        $apeNom = trim((string) $apeNom);

        if ($apeNom === '') {
            return ['', null];
        }

        if (!str_contains($apeNom, ',')) {
            return [$apeNom, null];
        }

        [$apellido, $nombre] = array_map('trim', explode(',', $apeNom, 2));

        return [$apellido, $nombre];
    }
}
