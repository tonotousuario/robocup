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
        Schema::create('rounds_encuentro', function (Blueprint $table) {
            $table->bigIncrements('id_round');

            $table->unsignedBigInteger('id_encuentro');
            $table->foreign('id_encuentro')->references('id_encuentro')->on('encuentros')->cascadeOnDelete();

            $table->integer('numero_round');

            $table->unsignedBigInteger('id_inscripcion_ganador')->nullable();
            $table->foreign('id_inscripcion_ganador')->references('id_inscripcion')->on('inscripciones')->nullOnDelete();

            $table->boolean('repetido')->default(false);
            $table->timestamp('fecha')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rounds_encuentro');
    }
};
