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
        Schema::create('inspecciones_checklist', function (Blueprint $table) {
            $table->bigIncrements('id_inspeccion');

            $table->unsignedBigInteger('id_inscripcion');
            $table->foreign('id_inscripcion')->references('id_inscripcion')->on('inscripciones')->cascadeOnDelete();

            $table->unsignedBigInteger('id_juez');
            $table->foreign('id_juez')->references('id')->on('users')->onDelete('no action');

            $table->integer('peso_medido_g');
            $table->string('dimensiones_medidas');
            $table->enum('estado_aprobacion', ['Pendiente', 'Aprobado', 'Rechazado', 'Descalificado']);
            $table->text('observaciones')->nullable();
            $table->timestamp('fecha_inspeccion')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inspecciones_checklist');
    }
};
