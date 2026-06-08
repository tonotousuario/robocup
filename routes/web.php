<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InstitucionController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
});

Route::middleware(['auth', 'verified', 'role:Administrador'])->group(function () {
    Route::resource('instituciones', InstitucionController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->parameters(['instituciones' => 'institucion']);
});

require __DIR__.'/settings.php';
