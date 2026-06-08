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
        Schema::create('amonestaciones', function (Blueprint $table) {
            $table->bigIncrements('id_amonestacion');

            $table->unsignedBigInteger('id_encuentro');
            $table->foreign('id_encuentro')->references('id_encuentro')->on('encuentros')->cascadeOnDelete();

            $table->unsignedBigInteger('id_inscripcion');
            $table->foreign('id_inscripcion')->references('id_inscripcion')->on('inscripciones')->cascadeOnDelete();

            $table->unsignedBigInteger('id_juez');
            $table->foreign('id_juez')->references('id')->on('users')->onDelete('no action');

            $table->integer('numero_round')->nullable();
            $table->text('motivo');
            $table->timestamp('fecha')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('amonestaciones');
    }
};
