<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Genera el listado imprimible (PDF) de docentes inscriptos al Trayecto
 * Formativo, para el panel admin (gestionar-trayecto.blade.php). Respeta
 * los mismos filtros (cohorte/nivel/estamento/búsqueda) que el listado en
 * pantalla, recibidos por query string.
 */
class TrayectoPdfController extends Controller
{
    public function generar(Request $request)
    {
        $cohorte   = (int) $request->query('cohorte', config('trayecto.cohorte_activa'));
        $nivel     = (string) $request->query('nivel', '');
        $estamento = (string) $request->query('estamento', '');
        $busqueda  = (string) $request->query('busqueda', '');

        $inscripciones = DB::table('tb_trayecto_formativo as t')
            ->leftJoin('tb_instituciones_trayecto as i', 'i.id', '=', 't.institucion_trayecto_id')
            ->where('t.cohorte', $cohorte)
            ->when($nivel !== '', fn ($q) => $q->where('t.nivel', $nivel))
            ->when($estamento !== '', fn ($q) => $q->where('t.estamento', $estamento))
            ->when(trim($busqueda) !== '', function ($q) use ($busqueda) {
                $b = '%' . trim($busqueda) . '%';
                $q->where(function ($sub) use ($b) {
                    $sub->where('t.dni', 'ilike', $b)
                        ->orWhere('t.apellido', 'ilike', $b)
                        ->orWhere('t.nombre', 'ilike', $b);
                });
            })
            ->select('t.*', 'i.nombre as institucion_nombre', 'i.cue as institucion_cue')
            ->orderBy('t.apellido')
            ->orderBy('t.nombre')
            ->get();

        $tz = config('app.timezone', 'America/Argentina/Buenos_Aires');

        $pdf = app('dompdf.wrapper')->loadView('pdf.trayecto-listado', [
            'inscripciones'  => $inscripciones,
            'nombreTrayecto' => config('trayecto.nombre'),
            'cohorte'        => $cohorte,
            'filtroNivel'    => $nivel,
            'filtroEstamento'=> $estamento,
            'busqueda'       => $busqueda,
            'fechaHoy'       => Carbon::now($tz)->locale('es')->isoFormat('D [de] MMMM [de] YYYY, HH:mm'),
        ])
            ->setPaper('a4', 'landscape')
            ->setOptions([
                'defaultFont'          => 'sans-serif',
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled'      => false,
                'dpi'                  => 150,
            ]);

        $filename = 'trayecto_inscriptos_' . $cohorte . '_' . now()->format('Ymd_His') . '.pdf';

        return $pdf->download($filename);
    }
}
