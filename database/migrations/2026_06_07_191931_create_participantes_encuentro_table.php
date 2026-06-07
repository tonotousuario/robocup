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
        Schema::create('participantes_encuentro', function (Blueprint $table) {
            $table->unsignedBigInteger('id_encuentro');
            $table->unsignedBigInteger('id_inscripcion');
            $table->integer('puntos_obtenidos')->default(0);
            $table->boolean('es_ganador')->default(false);

            $table->primary(['id_encuentro', 'id_inscripcion']);
            $table->foreign('id_encuentro')->references('id_encuentro')->on('encuentros')->cascadeOnDelete();
            $table->foreign('id_inscripcion')->references('id_inscripcion')->on('inscripciones')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('participantes_encuentro');
    }
};
