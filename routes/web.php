<?php

use App\Http\Controllers\ActivacionController;
use App\Http\Controllers\CargaCombustibleController;
use App\Http\Controllers\ConductorController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Integraciones\TracksolidController;
use App\Http\Controllers\MantenimientoController;
use App\Http\Controllers\MapaController;
use App\Http\Controllers\PlantillaMantenimientoController;
use App\Http\Controllers\RecorridoController;
use App\Http\Controllers\SucursalController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VehiculoController;
use App\Http\Controllers\VehiculoDocumentoController;
use App\Http\Controllers\VehiculoFotoController;
use Illuminate\Support\Facades\Route;

// La raíz no muestra landing: siempre redirige al login (los usuarios ya
// autenticados son reenviados al dashboard por el middleware `guest` del login).
Route::redirect('/', '/login')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('mapa', [MapaController::class, 'index'])->name('mapa');

    Route::get('vehiculos/{vehiculo}/recorrido', [RecorridoController::class, 'index'])
        ->name('vehiculos.recorrido');

    Route::resource('conductores', ConductorController::class)
        ->parameters(['conductores' => 'conductor'])
        ->except(['show']);

    Route::resource('sucursales', SucursalController::class)
        ->parameters(['sucursales' => 'sucursal'])
        ->except(['show']);

    Route::resource('usuarios', UserController::class)
        ->parameters(['usuarios' => 'user'])
        ->except(['show']);
    Route::put('usuarios/{user}/password', [UserController::class, 'updatePassword'])
        ->name('usuarios.password.update');

    Route::resource('vehiculos', VehiculoController::class);

    Route::scopeBindings()->group(function () {
        Route::post('vehiculos/{vehiculo}/fotos', [VehiculoFotoController::class, 'store'])
            ->name('vehiculos.fotos.store');
        Route::delete('vehiculos/{vehiculo}/fotos/{foto}', [VehiculoFotoController::class, 'destroy'])
            ->name('vehiculos.fotos.destroy');

        Route::get('vehiculos/{vehiculo}/documentos', [VehiculoDocumentoController::class, 'index'])
            ->name('vehiculos.documentos.index');
        Route::post('vehiculos/{vehiculo}/documentos', [VehiculoDocumentoController::class, 'store'])
            ->name('vehiculos.documentos.store');
        Route::delete('vehiculos/{vehiculo}/documentos/{documento}', [VehiculoDocumentoController::class, 'destroy'])
            ->name('vehiculos.documentos.destroy');
    });

    // Acceso rápido del conductor: lista sus vehículos para registrar carga.
    Route::get('registrar-carga', [CargaCombustibleController::class, 'rapido'])
        ->name('combustible.rapido');

    // Bandeja de cargas por procesar (admin).
    Route::get('combustible/pendientes', [CargaCombustibleController::class, 'pendientes'])
        ->name('combustible.pendientes');

    // El historial de combustible se ancla al vehículo; la carga se valida
    // contra su vehículo en el controlador (no scopeBindings: la relación es
    // `cargasCombustible`, no `cargas`).
    Route::get('vehiculos/{vehiculo}/combustible', [CargaCombustibleController::class, 'index'])
        ->name('vehiculos.combustible.index');
    Route::post('vehiculos/{vehiculo}/combustible', [CargaCombustibleController::class, 'store'])
        ->name('vehiculos.combustible.store');
    Route::put('vehiculos/{vehiculo}/combustible/{carga}', [CargaCombustibleController::class, 'update'])
        ->name('vehiculos.combustible.update');
    Route::delete('vehiculos/{vehiculo}/combustible/{carga}', [CargaCombustibleController::class, 'destroy'])
        ->name('vehiculos.combustible.destroy');

    // Registro de Activación de Unidad en Reposo (Subproceso 3). Anclado al
    // vehículo; la activación se valida contra su vehículo en el controlador.
    Route::get('vehiculos/{vehiculo}/activaciones', [ActivacionController::class, 'index'])
        ->name('vehiculos.activaciones.index');
    Route::post('vehiculos/{vehiculo}/activaciones', [ActivacionController::class, 'store'])
        ->name('vehiculos.activaciones.store');
    Route::delete('vehiculos/{vehiculo}/activaciones/{activacion}', [ActivacionController::class, 'destroy'])
        ->name('vehiculos.activaciones.destroy');

    Route::get('vehiculos/{vehiculo}/mantenimiento', [MantenimientoController::class, 'index'])
        ->name('vehiculos.mantenimiento.index');
    Route::post('vehiculos/{vehiculo}/mantenimiento', [MantenimientoController::class, 'store'])
        ->name('vehiculos.mantenimiento.store');
    Route::put('vehiculos/{vehiculo}/mantenimiento/{mantenimiento}', [MantenimientoController::class, 'update'])
        ->name('vehiculos.mantenimiento.update');
    Route::delete('vehiculos/{vehiculo}/mantenimiento/{mantenimiento}', [MantenimientoController::class, 'destroy'])
        ->name('vehiculos.mantenimiento.destroy');

    // Mantenedor (admin): plantillas de mantenimiento.
    Route::prefix('mantenedor')->name('mantenedor.')->group(function () {
        Route::get('plantillas-mantenimiento', [PlantillaMantenimientoController::class, 'index'])
            ->name('plantillas.index');
        Route::post('plantillas-mantenimiento', [PlantillaMantenimientoController::class, 'store'])
            ->name('plantillas.store');
        Route::put('plantillas-mantenimiento/{plantilla}', [PlantillaMantenimientoController::class, 'update'])
            ->name('plantillas.update');
        Route::delete('plantillas-mantenimiento/{plantilla}', [PlantillaMantenimientoController::class, 'destroy'])
            ->name('plantillas.destroy');
    });

    Route::prefix('integraciones')->name('integraciones.')->group(function () {
        Route::get('tracksolid', [TracksolidController::class, 'index'])
            ->name('tracksolid.index');
        Route::post('tracksolid/vincular', [TracksolidController::class, 'vincular'])
            ->name('tracksolid.vincular');
        Route::post('tracksolid/importar', [TracksolidController::class, 'importar'])
            ->name('tracksolid.importar');
        Route::post('tracksolid/{vehiculo}/sincronizar', [TracksolidController::class, 'sincronizar'])
            ->name('tracksolid.sincronizar');
        Route::post('tracksolid/{vehiculo}/calibrar', [TracksolidController::class, 'calibrar'])
            ->name('tracksolid.calibrar');
        Route::get('tracksolid/{vehiculo}/camaras', [TracksolidController::class, 'camaraPage'])
            ->name('tracksolid.camaras');
        Route::get('tracksolid/{vehiculo}/camara', [TracksolidController::class, 'camara'])
            ->name('tracksolid.camara');
        Route::delete('tracksolid/{vehiculo}/desvincular', [TracksolidController::class, 'desvincular'])
            ->name('tracksolid.desvincular');
    });
});

require __DIR__.'/settings.php';
