<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Texto general de requisitos (fila única, id = 1)
        Schema::create('tb_requisitos_generales', function (Blueprint $table) {
            $table->id();
            $table->longText('contenido')->nullable();
            $table->timestamps();
        });

        // PDFs informativos subidos por el admin
        Schema::create('tb_requisitos_documentos', function (Blueprint $table) {
            $table->id();
            $table->string('titulo');
            $table->string('archivo'); // ruta relativa dentro de storage/app/public
            $table->timestamps();
        });

        // Fila inicial vacía para editar directamente
        DB::table('tb_requisitos_generales')->insert([
            'contenido'  => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_requisitos_documentos');
        Schema::dropIfExists('tb_requisitos_generales');
    }
};
