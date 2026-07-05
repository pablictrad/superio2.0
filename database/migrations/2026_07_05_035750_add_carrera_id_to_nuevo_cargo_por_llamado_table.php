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
        Schema::table('nuevo_cargo_por_llamado', function (Blueprint $table) {
             $table->unsignedInteger('carrera_id')->nullable()->after('nuevo_rel_instituto_cargo_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('nuevo_cargo_por_llamado', function (Blueprint $table) {
           $table->dropColumn('carrera_id');
        });
    }
};
