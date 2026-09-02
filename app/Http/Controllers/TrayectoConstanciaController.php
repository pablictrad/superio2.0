<?php

namespace App\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class TrayectoConstanciaController extends Controller
{
    public function descargar(string $dni, int $cohorte)
    {
        $dni = preg_replace('/[^0-9]/', '', $dni);

        $inscripciones = DB::table('tb_trayecto_formativo as tf')
            ->leftJoin('tb_instituciones_trayecto as it', 'tf.institucion_trayecto_id', '=', 'it.id')
            ->where('tf.dni', $dni)
            ->where('tf.cohorte', $cohorte)
            ->orderByDesc('tf.id')
            ->select('tf.*', 'it.nombre as institucion_nombre', 'it.cue as institucion_cue')
            ->get();

        abort_if($inscripciones->isEmpty(), 404, 'No se encontró inscripción para este DNI en esta convocatoria.');

        $datos = $inscripciones->first();

        $documentos = [
            'f2'                     => ['label' => 'Declaración Jurada de cargos F2', 'path' => $datos->f2_path],
            'certificacion_servicio' => ['label' => 'Certificación de servicio actualizada', 'path' => $datos->certificacion_servicio_path],
            'concepto'               => ['label' => 'Concepto elevado por la institución', 'path' => $datos->concepto_path],
        ];

        foreach ($documentos as &$doc) {
            $doc['entregado'] = !empty($doc['path']) && Storage::disk('public')->exists($doc['path']);
        }
        unset($doc);

        $tz = config('app.timezone', 'America/Argentina/Buenos_Aires');
        $fechaHoy = Carbon::now($tz)->locale('es')->isoFormat('D [de] MMMM [de] YYYY');

        $pdf = Pdf::loadView('pdf.trayecto-constancia', [
            'datos'          => $datos,
            'inscripciones'  => $inscripciones,
            'documentos'     => $documentos,
            'nombreTrayecto' => config('trayecto.nombre'),
            'cohorte'        => $cohorte,
            'fechaHoy'       => $fechaHoy,
        ])
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'defaultFont'          => 'sans-serif',
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled'      => false,
                'dpi'                  => 150,
            ]);

        return $pdf->download('constancia_trayecto_' . $dni . '_' . $cohorte . '.pdf');
    }
}
