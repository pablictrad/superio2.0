<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Espacio_curricular;
use App\Models\Cargo;
use App\Models\Carrera;
use App\Models\Instituto;

return new class extends Migration
{
    /**
     * Agrega la columna 'activo' (boolean, default true) a las tablas
     * de Espacios Curriculares, Cargos, Carreras e Institutos, para
     * poder habilitar/deshabilitar registros en lugar de eliminarlos.
     *
     * Usa getTable() de cada modelo para no depender de adivinar el
     * nombre real de la tabla (ver notas de nomenclatura en el proyecto).
     */
    public function up(): void
    {
        foreach ([Espacio_curricular::class, Cargo::class, Carrera::class, Instituto::class] as $modelClass) {
            $table = (new $modelClass)->getTable();

            if (! Schema::hasColumn($table, 'activo')) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->boolean('activo')->default(true);
                });
            }
        }
    }

    public function down(): void
    {
        foreach ([Espacio_curricular::class, Cargo::class, Carrera::class, Instituto::class] as $modelClass) {
            $table = (new $modelClass)->getTable();

            if (Schema::hasColumn($table, 'activo')) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->dropColumn('activo');
                });
            }
        }
    }
};
