<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tb_docentes', function (Blueprint $table) {
            $table->id();
            $table->string('dni', 9)->unique()->comment('Sin puntos ni espacios');
            $table->string('apellido', 100);
            $table->string('nombre', 100);
            $table->string('telefono', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('domicilio', 200)->nullable();
            $table->string('localidad', 100)->nullable();
            $table->boolean('tiene_legajo')->default(false)->comment('Posee legajo en la institución');
            $table->timestamps();
        });
          // ── 2. TÍTULOS ─────────────────────────────────────────────────────────
        Schema::create('tb_docente_titulos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('docente_id')->constrained('tb_docentes')->cascadeOnDelete();
            $table->string('nombre_titulo', 200)->comment('Nombre exacto para control de duplicados');
            $table->string('institucion', 200)->nullable()->comment('Institución que lo otorgó');
            $table->year('anio_egreso')->nullable();
            $table->string('archivo_path', 500)->nullable()->comment('Ruta en storage/public');
            $table->string('archivo_nombre_original', 300)->nullable()->comment('Nombre original del archivo subido');
            $table->timestamps();
 
            // Unicidad: un docente no puede tener el mismo título dos veces
            $table->unique(['docente_id', 'nombre_titulo'], 'uq_docente_titulo');
        });
 
        // ── 3. CERTIFICADOS ────────────────────────────────────────────────────
        Schema::create('tb_docente_certificados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('docente_id')->constrained('tb_docentes')->cascadeOnDelete();
            $table->string('nombre_certificado', 200)->comment('Nombre exacto para control de duplicados');
            $table->string('tipo', 50)->nullable()->comment('Ej: Capacitación, Antigüedad, Desempeño');
            $table->string('archivo_path', 500)->nullable();
            $table->string('archivo_nombre_original', 300)->nullable();
            $table->timestamps();
 
            $table->unique(['docente_id', 'nombre_certificado'], 'uq_docente_certificado');
        });
 
        // ── 4. ALTER inscripciones_llamado ─────────────────────────────────────
        Schema::table('inscripciones_llamado', function (Blueprint $table) {
            // FK al docente (nullable para no romper registros viejos)
            $table->foreignId('docente_id')
                  ->nullable()
                  ->after('llamado_id')
                  ->constrained('tb_docentes')
                  ->nullOnDelete();
 
            // Legajo
            $table->boolean('tiene_legajo')
                  ->default(false)
                  ->after('domicilio')
                  ->comment('El docente indicó que posee legajo');
 
            // F2 presentado
            $table->boolean('presento_f2')
                  ->default(false)
                  ->after('tiene_legajo')
                  ->comment('Presentó formulario F2 firmado por la escuela');
 
            $table->string('f2_path', 500)
                  ->nullable()
                  ->after('presento_f2')
                  ->comment('Ruta del archivo F2 si se subió digitalmente');
 
            // Localidad
            $table->string('localidad', 100)
                  ->nullable()
                  ->after('domicilio');
 
            // Constancia generada
            $table->string('codigo_constancia', 20)
                  ->nullable()
                  ->unique()
                  ->comment('Código único de la constancia de inscripción');
 
            $table->timestamp('constancia_generada_at')
                  ->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
         Schema::table('inscripciones_llamado', function (Blueprint $table) {
            $table->dropForeign(['docente_id']);
            $table->dropColumn([
                'docente_id', 'tiene_legajo', 'presento_f2',
                'f2_path', 'localidad', 'codigo_constancia', 'constancia_generada_at',
            ]);
        });
 
        Schema::dropIfExists('tb_docente_certificados');
        Schema::dropIfExists('tb_docente_titulos');
        Schema::dropIfExists('tb_docentes');
    }
};
