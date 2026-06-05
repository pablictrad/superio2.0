<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;

class InscripcionConstanciaController extends Controller
{
    public function descargar(string $codigo)
    {
        // ── 1. Inscripción ──────────────────────────────────────────
        $inscripcion = DB::table('inscripciones_llamado')
            ->where('codigo_constancia', $codigo)
            ->first();

        abort_if(!$inscripcion, 404, 'Constancia no encontrada.');

        // ── 2. Llamado ──────────────────────────────────────────────
        $llamado = DB::table('nuevo_llamado')
            ->leftJoin('tipo_llamado', 'nuevo_llamado.idtipo_llamado', '=', 'tipo_llamado.id')
            ->leftJoin('tb_zona',      'nuevo_llamado.idtb_zona',      '=', 'tb_zona.id')
            ->where('nuevo_llamado.id', $inscripcion->llamado_id)
            ->select(
                'nuevo_llamado.id',
                'nuevo_llamado.fecha_fin',
                'tipo_llamado.nombre as tipo_nombre',
                'tb_zona.nombre_zona'
            )
            ->first();

        // ── 3. Espacios del llamado ─────────────────────────────────
        $espacios = DB::table('nuevo_espacios_por_llamado as epl')
            ->join('nuevo_rel_carrera_espacio as rce', 'epl.nuevo_rel_carrera_espacio_id', '=', 'rce.id')
            ->join('tb_espacioscurriculares as ec',    'rce.espacio_id',   '=', 'ec.idEspacioCurricular')
            ->join('tb_carreras as c',                 'rce.carrera_id',   '=', 'c.id')
            ->join('tb_instituto_superior as inst',    'epl.instituto_id', '=', 'inst.id')
            ->where('epl.llamado_id', $inscripcion->llamado_id)
            ->select('ec.nombre_espacio as nombre', 'c.nombre as carrera', 'inst.nombre as instituto')
            ->get();

        // Si el llamado es de cargos en lugar de espacios
        if ($espacios->isEmpty()) {
            $espacios = DB::table('nuevo_cargo_por_llamado as cpl')
                ->join('nuevo_rel_carrera_cargo as rcc', 'cpl.nuevo_rel_carrera_cargo_id', '=', 'rcc.id')
                ->join('tb_cargos as ca',                'rcc.cargo_id',    '=', 'ca.id')
                ->join('tb_carreras as c',               'rcc.carrera_id',  '=', 'c.id')
                ->join('tb_instituto_superior as inst',  'cpl.instituto_id','=', 'inst.id')
                ->where('cpl.llamado_id', $inscripcion->llamado_id)
                ->select('ca.nombre_cargo as nombre', 'c.nombre as carrera', 'inst.nombre as instituto')
                ->get();
        }

        // ── 4. Títulos y certificados ───────────────────────────────
        $titulos      = collect();
        $certificados = collect();

        if (!empty($inscripcion->docente_id)) {
            $titulos = DB::table('tb_docente_titulos')
                ->where('docente_id', $inscripcion->docente_id)
                ->get();

            $certificados = DB::table('tb_docente_certificados')
                ->where('docente_id', $inscripcion->docente_id)
                ->get();
        }

        // ── 5. Fecha ────────────────────────────────────────────────
        $tz = config('app.timezone', 'America/Argentina/Buenos_Aires');
        $fechaHoy = Carbon::now($tz)->isoFormat('D [de] MMMM [de] YYYY');

        // ── 6. PDF ──────────────────────────────────────────────────
        $pdf = Pdf::loadView('pdf.constancia', compact(
            'inscripcion', 'llamado', 'espacios',
            'titulos', 'certificados', 'fechaHoy'
        ))
        ->setPaper('a4', 'portrait')
        ->setOptions([
            'defaultFont'          => 'sans-serif',
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled'      => false,
            'dpi'                  => 150,
        ]);

        return $pdf->download('constancia_' . $codigo . '.pdf');
    }
}