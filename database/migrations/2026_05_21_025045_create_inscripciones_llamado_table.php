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
       Schema::create('inscripciones_llamado', function (Blueprint $table) {
            $table->id();
           // Relación con el llamado
            $table->unsignedBigInteger('llamado_id');
            $table->foreign('llamado_id')
                  ->references('id')
                  ->on('nuevo_llamado')
                  ->onDelete('cascade');

            // Datos del docente inscripto
            $table->string('apellido', 100);
            $table->string('nombre', 100);
            $table->string('dni', 15);
            $table->string('telefono', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('domicilio', 200)->nullable();

            // Datos de titulación / antecedentes (opcional, libre)
            $table->text('titulos')->nullable();
            $table->text('observaciones')->nullable();

            // Orden (para el LOM)
            $table->unsignedSmallInteger('orden')->nullable();

            // Estado de la inscripción
            $table->enum('estado', ['pendiente', 'habilitado', 'sin_clasificar'])
                  ->default('pendiente');

            // Puntaje asignado por la comisión
            $table->decimal('puntaje', 6, 2)->nullable();

            $table->timestamps();

            // Un docente (por DNI) no puede inscribirse dos veces al mismo llamado
            $table->unique(['llamado_id', 'dni'], 'uq_inscripcion_llamado_dni');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inscripciones_llamado');
    }
};
