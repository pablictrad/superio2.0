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
            ->join('tb_espacioscurriculares as ec',    'rce.espacio_id',   '=', 'ec.idespaciocurricular')
            ->join('tb_carreras as c',                 'rce.carrera_id',   '=', 'c.id')
            ->join('tb_instituto_superior as inst',    'epl.instituto_id', '=', 'inst.id')
            ->where('epl.llamado_id', $inscripcion->llamado_id)
            ->select('ec.nombre_espacio as nombre', 'c.nombre as carrera', 'inst.nombre as instituto')
            ->get();

        // Si el llamado es de cargos en lugar de espacios
       // DESPUÉS
        if ($espacios->isEmpty()) {
            $espacios = DB::table('nuevo_cargo_por_llamado as cpl')
                ->join('nuevo_rel_instituto_cargo as ric', 'cpl.nuevo_rel_instituto_cargo_id', '=', 'ric.id')
                ->join('tb_cargos as ca',                 'ric.cargo_id',     '=', 'ca.id')
                ->join('tb_instituto_superior as inst',   'cpl.instituto_id', '=', 'inst.id')
                ->leftJoin('tb_carreras as c',             'cpl.carrera_id',   '=', 'c.id') // nullable: solo Bedel la tiene
                ->where('cpl.llamado_id', $inscripcion->llamado_id)
                ->select('ca.nombre_cargo as nombre', 'c.nombre as carrera', 'inst.nombre as instituto')
                ->get();
        }

        // ── 4. Títulos, certificados y domicilio (DNI/comprobante/zona) ─────
        $titulos      = collect();
        $certificados = collect();
        $domicilio    = null;

        if (!empty($inscripcion->docente_id)) {
            $titulos = DB::table('tb_docente_titulos')
                ->where('docente_id', $inscripcion->docente_id)
                ->get();

            $certificados = DB::table('tb_docente_certificados')
                ->where('docente_id', $inscripcion->docente_id)
                ->get();

           $domicilio = DB::table('tb_docentes as d')
            ->join('tb_domicilio as dom', 'd.domicilio_id', '=', 'dom.idtb_domicilio')
            ->leftJoin('tb_localidades as loc', 'dom.localidad_id', '=', 'loc.id')
            ->leftJoin('tb_departamentos as dep', 'loc.iddepartamento', '=', 'dep.iddepartamento')
            ->where('d.id', $inscripcion->docente_id)
            ->select(
                'dom.*',
                'loc.localidad as localidad_nombre',
                'loc.zona_override',
                'dep.zona as zona_departamento'
            )
         ->first();
        }

        // ── 5. Fecha ────────────────────────────────────────────────
        $tz = config('app.timezone', 'America/Argentina/Buenos_Aires');
        $fechaHoy = Carbon::now($tz)->isoFormat('D [de] MMMM [de] YYYY');

        // ── 6. PDF ──────────────────────────────────────────────────
        $pdf = Pdf::loadView('pdf.constancia', compact(
            'inscripcion', 'llamado', 'espacios',
            'titulos', 'certificados', 'fechaHoy', 'domicilio'
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