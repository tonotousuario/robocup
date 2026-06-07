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
        Schema::create('inscripciones', function (Blueprint $table) {
            $table->bigIncrements('id_inscripcion');

            $table->unsignedBigInteger('id_robot');
            $table->foreign('id_robot')->references('id_robot')->on('robots')->cascadeOnDelete();

            $table->unsignedBigInteger('id_tarifa')->nullable();
            $table->foreign('id_tarifa')->references('id_tarifa')->on('tarifas')->onDelete('no action');

            $table->timestamp('fecha_registro')->useCurrent();
            $table->decimal('monto_pagado', 10, 2)->default(0.00);
            $table->enum('estado_pago', ['Pendiente', 'Pagado', 'Cancelado'])->default('Pendiente');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inscripciones');
    }
};
