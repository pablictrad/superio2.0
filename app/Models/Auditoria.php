<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;

/**
 * Helper centralizado para registrar movimientos de usuarios (auditoría).
 *
 * Uso típico dentro de cualquier componente Volt/Livewire:
 *   use App\Support\Auditoria;
 *   ...
 *   Auditoria::registrar('aprobar_f2', 'inscripcion', $inscripcionId, "F2 aprobado para {$nombre}");
 */
class Auditoria
{
    public static function registrar(string $accion, ?string $entidad = null, ?int $entidadId = null, ?string $detalle = null): void
    {
        DB::table('tb_auditoria')->insert([
            'usuario_id' => auth()->id(),
            'accion'     => $accion,
            'entidad'    => $entidad,
            'entidad_id' => $entidadId,
            'detalle'    => $detalle,
            'ip'         => Request::ip(),
            'created_at' => now(),
        ]);
    }
}
