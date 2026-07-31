<?php

use App\Http\Controllers\AsignacionController;
use App\Http\Controllers\ConductorController;
use App\Http\Controllers\ConductorDocumentoController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DisponibilidadController;
use App\Http\Controllers\NovedadController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VehiculoController;
use App\Http\Controllers\VehiculoDocumentoController;
use Illuminate\Support\Facades\Route;

// La raíz no muestra landing: siempre redirige al login (los usuarios ya
// autenticados son reenviados al dashboard por el middleware `guest` del login).
Route::redirect('/', '/login')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::resource('conductores', ConductorController::class)
        ->parameters(['conductores' => 'conductor']);

    Route::scopeBindings()->group(function () {
        Route::post('conductores/{conductor}/documentos', [ConductorDocumentoController::class, 'store'])
            ->name('conductores.documentos.store');
        Route::delete('conductores/{conductor}/documentos/{documento}', [ConductorDocumentoController::class, 'destroy'])
            ->name('conductores.documentos.destroy');
    });

    // Antes del resource para que «disponibles» no se lea como un parámetro.
    Route::get('asignaciones/disponibles', [AsignacionController::class, 'disponibles'])
        ->name('asignaciones.disponibles');

    Route::resource('asignaciones', AsignacionController::class)
        ->parameters(['asignaciones' => 'asignacion'])
        ->except(['show']);
    Route::post('asignaciones/{asignacion}/liberar', [AsignacionController::class, 'liberar'])
        ->name('asignaciones.liberar');
    Route::get('asignaciones/{asignacion}/reasignar', [AsignacionController::class, 'formularioReasignar'])
        ->name('asignaciones.reasignar.form');
    Route::patch('asignaciones/{asignacion}/reasignar', [AsignacionController::class, 'reasignar'])
        ->name('asignaciones.reasignar');

    Route::post('novedades', [NovedadController::class, 'store'])->name('novedades.store');
    Route::post('novedades/{novedad}/levantar', [NovedadController::class, 'levantar'])
        ->name('novedades.levantar');

    Route::get('disponibilidad', [DisponibilidadController::class, 'index'])
        ->name('disponibilidad.index');
    Route::post('disponibilidad/arrastrar', [DisponibilidadController::class, 'arrastrar'])
        ->name('disponibilidad.arrastrar');
    Route::patch('disponibilidad/{vehiculo}/celda', [DisponibilidadController::class, 'actualizarCelda'])
        ->name('disponibilidad.celda');
    Route::delete('disponibilidad/{estado}', [DisponibilidadController::class, 'destroy'])
        ->name('disponibilidad.destroy');

    Route::resource('usuarios', UserController::class)
        ->parameters(['usuarios' => 'user'])
        ->except(['show']);
    Route::put('usuarios/{user}/password', [UserController::class, 'updatePassword'])
        ->name('usuarios.password.update');

    Route::resource('vehiculos', VehiculoController::class);

    Route::scopeBindings()->group(function () {
        Route::get('vehiculos/{vehiculo}/documentos', [VehiculoDocumentoController::class, 'index'])
            ->name('vehiculos.documentos.index');
        Route::post('vehiculos/{vehiculo}/documentos', [VehiculoDocumentoController::class, 'store'])
            ->name('vehiculos.documentos.store');
        Route::delete('vehiculos/{vehiculo}/documentos/{documento}', [VehiculoDocumentoController::class, 'destroy'])
            ->name('vehiculos.documentos.destroy');
    });
});

require __DIR__.'/settings.php';
