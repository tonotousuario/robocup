<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InscripcionController;
use App\Http\Controllers\InspeccionController;
use App\Http\Controllers\InstitucionController;
use App\Http\Controllers\RobotController;
use App\Http\Controllers\UsuarioController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('robots', RobotController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::get('inscripciones', [InscripcionController::class, 'index'])->name('inscripciones.index');
    Route::post('inscripciones', [InscripcionController::class, 'store'])->name('inscripciones.store');
    Route::patch('inscripciones/{inscripcion}/pagar', [InscripcionController::class, 'pagar'])->name('inscripciones.pagar');
    Route::patch('inscripciones/{inscripcion}/cancelar', [InscripcionController::class, 'cancelar'])->name('inscripciones.cancelar');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('inspecciones', [InspeccionController::class, 'index'])->name('inspecciones.index');
    Route::post('inspecciones', [InspeccionController::class, 'guardar'])->name('inspecciones.guardar');
});

Route::middleware(['auth', 'verified', 'role:Administrador'])->group(function () {
    Route::resource('instituciones', InstitucionController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->parameters(['instituciones' => 'institucion']);
    Route::resource('usuarios', UsuarioController::class)->only(['index', 'store', 'update', 'destroy']);
});

require __DIR__.'/settings.php';
