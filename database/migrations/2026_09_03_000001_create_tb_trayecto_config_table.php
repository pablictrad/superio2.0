<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabla de una sola fila: flag global que el admin usa para
     * habilitar/deshabilitar la inscripción pública al Trayecto Formativo
     * (botón en la pantalla principal + acceso directo a /trayecto/registrar).
     * Se inicializa en `true` para no interrumpir la convocatoria activa.
     */
    public function up(): void
    {
        Schema::create('tb_trayecto_config', function (Blueprint $table) {
            $table->id();
            $table->boolean('habilitado')->default(true);
            $table->timestamps();
        });

        DB::table('tb_trayecto_config')->insert([
            'habilitado' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_trayecto_config');
    }
};
