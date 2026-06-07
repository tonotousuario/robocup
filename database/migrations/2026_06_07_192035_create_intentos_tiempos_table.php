<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('intentos_tiempos', function (Blueprint $table) {
            $table->bigIncrements('id_intento');

            $table->unsignedBigInteger('id_inscripcion');
            $table->foreign('id_inscripcion')->references('id_inscripcion')->on('inscripciones')->cascadeOnDelete();

            $table->integer('numero_vuelta');
            $table->decimal('tiempo_logrado', 8, 3);
            $table->decimal('penalizacion_segundos', 8, 3)->default(0.000);

            $table->unique(['id_inscripcion', 'numero_vuelta']);
        });

        DB::statement('ALTER TABLE intentos_tiempos ADD CONSTRAINT chk_numero_vuelta CHECK (numero_vuelta BETWEEN 1 AND 3)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('intentos_tiempos');
    }
};
