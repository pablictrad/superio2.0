<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Flag global (tabla de una sola fila tb_trayecto_config) que el admin usa
 * para habilitar/deshabilitar la inscripción pública al Trayecto Formativo,
 * sin depender de tocar el .env del servidor.
 */
class TrayectoConfig
{
    public static function habilitado(): bool
    {
        return (bool) (DB::table('tb_trayecto_config')->value('habilitado') ?? false);
    }

    public static function setHabilitado(bool $valor): void
    {
        $id = DB::table('tb_trayecto_config')->value('id');

        if ($id) {
            DB::table('tb_trayecto_config')->where('id', $id)->update([
                'habilitado' => $valor,
                'updated_at' => now(),
            ]);
            return;
        }

        DB::table('tb_trayecto_config')->insert([
            'habilitado' => $valor,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
