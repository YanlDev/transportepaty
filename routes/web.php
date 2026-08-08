<?php

use App\Http\Controllers\AsignacionController;
use App\Http\Controllers\AsistenciaController;
use App\Http\Controllers\ConductorController;
use App\Http\Controllers\ConductorDocumentoController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NovedadController;
use App\Http\Controllers\ProgramacionController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VehiculoController;
use App\Http\Controllers\VehiculoDocumentoController;
use App\Http\Controllers\ViajeController;
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

    Route::get('viajes', [ViajeController::class, 'index'])->name('viajes.index');
    Route::post('viajes', [ViajeController::class, 'store'])->name('viajes.store');
    Route::get('viajes/manual', [ViajeController::class, 'create'])->name('viajes.manual.create');
    Route::post('viajes/manual', [ViajeController::class, 'storeManual'])->name('viajes.manual.store');
    Route::post('viajes/resolver', [ViajeController::class, 'resolver'])->name('viajes.resolver');
    Route::patch('viajes/{viaje}/tipo-carga', [ViajeController::class, 'actualizarTipoCarga'])
        ->name('viajes.actualizarTipoCarga');
    Route::delete('viajes/{viaje}', [ViajeController::class, 'destroy'])->name('viajes.destroy');

    Route::get('asistencia', [AsistenciaController::class, 'index'])->name('asistencia.index');
    Route::patch('asistencia/{conductor}', [AsistenciaController::class, 'marcar'])->name('asistencia.marcar');
    Route::delete('asistencia/{asistencia}', [AsistenciaController::class, 'destroy'])->name('asistencia.destroy');

    Route::get('programacion', [ProgramacionController::class, 'index'])->name('programacion.index');
    Route::patch('programacion/{vehiculo}', [ProgramacionController::class, 'marcar'])->name('programacion.marcar');
    Route::delete('programacion/{programacion}', [ProgramacionController::class, 'destroy'])->name('programacion.destroy');

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
