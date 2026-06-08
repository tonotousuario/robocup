<?php

use App\Http\Controllers\DashboardController;
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
});

Route::middleware(['auth', 'verified', 'role:Administrador'])->group(function () {
    Route::resource('instituciones', InstitucionController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->parameters(['instituciones' => 'institucion']);
    Route::resource('usuarios', UsuarioController::class)->only(['index', 'store', 'update', 'destroy']);
});

require __DIR__.'/settings.php';
