<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;


/**
 * LlamadoPdfController
 *
 * Genera el PDF oficial de un llamado docente.
 * Incluye:
 *   - Cabecera institucional
 *   - Datos completos del llamado (zona, instituto, carrera, espacio/cargo,
 *     perfil, horas, año, período, turno, situación de revista, horario)
 *   - Sección "Habilitados" con ranking por puntaje
 *   - Sección "Sin Clasificar" (estado = sin_clasificar)
 *
 * Requiere: composer require barryvdh/laravel-dompdf
 */
class LlamadoPdfController extends Controller
{
    public function generar(int $llamadoId)
    {
        // ── 1. Cabecera del llamado ──────────────────────────────────────────
        $llamado = DB::table('nuevo_llamado')
            ->leftJoin('tb_zona',       'nuevo_llamado.idtb_zona',      '=', 'tb_zona.id')
            ->leftJoin('tipo_llamado',  'nuevo_llamado.idtipo_llamado', '=', 'tipo_llamado.id')
            ->leftJoin('tb_tipoestado', 'nuevo_llamado.idtb_tipoestado','=', 'tb_tipoestado.idtb_tipoestado')
            ->where('nuevo_llamado.id', $llamadoId)
            ->select(
                'nuevo_llamado.*',
                'tb_zona.nombre_zona',
                'tipo_llamado.nombre  as tipo_nombre',
                'tb_tipoestado.nombre_tipoestado as estado_nombre'
            )
            ->first();

        abort_if(!$llamado, 404, 'Llamado no encontrado.');

        // ── 2. Detalles: espacios curriculares ───────────────────────────────
        $espacios = DB::table('nuevo_espacios_por_llamado as epl')
            ->join('nuevo_rel_carrera_espacio as rce', 'epl.nuevo_rel_carrera_espacio_id', '=', 'rce.id')
            ->join('tb_espacioscurriculares as ec',    'rce.espacio_id',   '=', 'ec.idEspacioCurricular')
            ->join('tb_carreras as c',                 'rce.carrera_id',   '=', 'c.id')
            ->join('tb_instituto_superior as inst',    'epl.instituto_id', '=', 'inst.id')
            ->leftJoin('tb_periodo_cursado as per',    'rce.periodo_id',   '=', 'per.idtb_periodo_cursado')
            ->leftJoin('tb_turnos as t',               'rce.turno_id',     '=', 't.id')
            ->leftJoin('tb_perfil as p',               'rce.perfil_id',    '=', 'p.idtb_perfil')
            ->join('tb_situacion_revista as sr',       'epl.situacion_revista_id', '=', 'sr.idtb_situacion_revista')
            ->where('epl.llamado_id', $llamadoId)
            ->select(
                'inst.nombre        as instituto',
                'c.nombre           as carrera',
                'ec.nombre_espacio  as nombre',
                'rce.hora_catedra',
                'rce.anio',
                'per.nombre_periodo as periodo',
                't.nombre_turno     as turno',
                'p.nombre_perfil    as perfil',
                'epl.horario_espacio as horario',
                'sr.nombre_situacion_revista as situacion_revista',
                DB::raw("'Espacio' as tipo")
            )
            ->get();

        // ── 3. Detalles: cargos ──────────────────────────────────────────────
        $cargos = DB::table('nuevo_cargo_por_llamado as cpl')
            ->join('nuevo_rel_carrera_cargo as rcc', 'cpl.nuevo_rel_carrera_cargo_id', '=', 'rcc.id')
            ->join('tb_cargos as ca',                'rcc.cargo_id',    '=', 'ca.id')
            ->join('tb_carreras as c',               'rcc.carrera_id',  '=', 'c.id')
            ->join('tb_instituto_superior as inst',  'cpl.instituto_id','=', 'inst.id')
            ->leftJoin('tb_periodo_cursado as per',  'rcc.periodo_id',  '=', 'per.idtb_periodo_cursado')
            ->leftJoin('tb_turnos as t',             'rcc.turno_id',    '=', 't.id')
            ->leftJoin('tb_perfil as p',             'rcc.perfil_id',   '=', 'p.idtb_perfil')
            ->join('tb_situacion_revista as sr',     'cpl.situacion_revista_id', '=', 'sr.idtb_situacion_revista')
            ->where('cpl.llamado_id', $llamadoId)
            ->select(
                'inst.nombre        as instituto',
                'c.nombre           as carrera',
                'ca.nombre_cargo    as nombre',
                'rcc.hora_catedra',
                'rcc.anio',
                'per.nombre_periodo as periodo',
                't.nombre_turno     as turno',
                'p.nombre_perfil    as perfil',
                'cpl.horario_cargo  as horario',
                'sr.nombre_situacion_revista as situacion_revista',
                DB::raw("'Cargo' as tipo")
            )
            ->get();

        $detalles = $espacios->merge($cargos)->values();

        // ── 4. Inscriptos habilitados (ordenados por puntaje desc) ───────────
        $habilitados = DB::table('inscripciones_llamado')
            ->where('llamado_id', $llamadoId)
            ->where('estado', 'habilitado')
            ->orderByDesc('puntaje')
            ->orderBy('apellido')
            ->get()
            ->map(function ($ins, $idx) {
                $ins->orden_display = str_pad($idx + 1, 2, '0', STR_PAD_LEFT);
                return $ins;
            });

        // ── 5. Sin clasificar ────────────────────────────────────────────────
        $sinClasificar = DB::table('inscripciones_llamado')
            ->where('llamado_id', $llamadoId)
            ->where('estado', 'sin_clasificar')
            ->orderBy('apellido')
            ->get();

        // ── 6. Fechas formateadas ────────────────────────────────────────────
        $tz = config('app.timezone', 'America/Argentina/Buenos_Aires');
        $llamado->fecha_ini_fmt = Carbon::parse($llamado->fecha_ini, $tz)->format('d/m/Y H:i');
        $llamado->fecha_fin_fmt = Carbon::parse($llamado->fecha_fin, $tz)->format('d/m/Y H:i');
        $llamado->fecha_hoy     = Carbon::now($tz)->isoFormat('D [de] MMMM [de] YYYY');

        // ── 7. Renderizar PDF ────────────────────────────────────────────────
        $pdf = app('dompdf.wrapper')->loadView('pdf.llamado', compact(
        'llamado',
        'detalles',
        'habilitados',
        'sinClasificar'
    ))
    ->setPaper('a4', 'landscape')
    ->setOptions([
        'defaultFont'          => 'sans-serif',
        'isHtml5ParserEnabled' => true,
        'isRemoteEnabled'      => false,
        'dpi'                  => 150,
    ]);

        $filename = 'llamado_' . $llamadoId . '_' . now()->format('Ymd_His') . '.pdf';

        return $pdf->download($filename);
    }
}
