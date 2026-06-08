<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EncuentroController;
use App\Http\Controllers\InscripcionController;
use App\Http\Controllers\InspeccionController;
use App\Http\Controllers\InstitucionController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\RobotController;
use App\Http\Controllers\TiempoController;
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

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('tiempos', [TiempoController::class, 'index'])->name('tiempos.index');
    Route::post('tiempos', [TiempoController::class, 'guardar'])->name('tiempos.guardar');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('combate', [EncuentroController::class, 'index'])->name('combate.index');
    Route::post('combate/generar', [EncuentroController::class, 'generar'])->name('combate.generar');
    Route::patch('encuentros/{encuentro}/ganador', [EncuentroController::class, 'registrarGanador'])->name('encuentros.ganador');
    Route::patch('encuentros/{encuentro}/round', [EncuentroController::class, 'registrarRound'])->name('encuentros.round');
    Route::patch('encuentros/{encuentro}/default', [EncuentroController::class, 'ganarPorDefault'])->name('encuentros.default');
    Route::patch('encuentros/{encuentro}/descalificar', [EncuentroController::class, 'descalificar'])->name('encuentros.descalificar');
    Route::post('encuentros/{encuentro}/amonestacion', [EncuentroController::class, 'amonestar'])->name('encuentros.amonestar');
    Route::patch('inscripciones/{inscripcion}/reparacion', [EncuentroController::class, 'marcarReparacion'])->name('inscripciones.reparacion');
});

Route::middleware(['auth', 'verified', 'role:Administrador'])->group(function () {
    Route::resource('instituciones', InstitucionController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->parameters(['instituciones' => 'institucion']);
    Route::resource('usuarios', UsuarioController::class)->only(['index', 'store', 'update', 'destroy']);
});

Route::middleware(['auth', 'verified', 'role:Administrador,Juez'])->group(function () {
    Route::get('reportes', [ReporteController::class, 'index'])->name('reportes.index');
});

require __DIR__.'/settings.php';
