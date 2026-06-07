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
        Schema::create('robots', function (Blueprint $table) {
            $table->bigIncrements('id_robot');
            $table->string('nombre');

            $table->unsignedBigInteger('id_piloto');
            $table->foreign('id_piloto')->references('id')->on('users')->onDelete('no action');

            $table->unsignedBigInteger('id_institucion')->nullable();
            $table->foreign('id_institucion')->references('id_institucion')->on('instituciones')->nullOnDelete();

            $table->unsignedBigInteger('id_categoria');
            $table->foreign('id_categoria')->references('id_categoria')->on('categorias')->onDelete('no action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('robots');
    }
};
