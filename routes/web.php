<?php

use App\Http\Controllers\AsistenciaController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\ConductorController;
use App\Http\Controllers\ConductorDocumentoController;
use App\Http\Controllers\ContabilidadController;
use App\Http\Controllers\CotizacionController;
use App\Http\Controllers\CuentaBancariaController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FacturaController;
use App\Http\Controllers\NovedadController;
use App\Http\Controllers\ParametroCostoController;
use App\Http\Controllers\ProgramacionController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VehiculoController;
use App\Http\Controllers\VehiculoDocumentoController;
use App\Http\Controllers\ViajeController;
use Illuminate\Support\Facades\Route;

// La raíz no muestra landing: siempre redirige al login (los usuarios ya
// autenticados son reenviados al dashboard por el middleware `guest` del login).
Route::redirect('/', '/login')->name('home');

Route::middleware('auth')->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::resource('conductores', ConductorController::class)
        ->parameters(['conductores' => 'conductor']);

    // El año completo de asistencia va aparte de la ficha: son doce
    // calendarios y ahí adentro dejaban las celdas ilegibles.
    Route::get('conductores/{conductor}/asistencia', [ConductorController::class, 'asistencia'])
        ->name('conductores.asistencia');

    Route::scopeBindings()->group(function () {
        Route::post('conductores/{conductor}/documentos', [ConductorDocumentoController::class, 'store'])
            ->name('conductores.documentos.store');
        Route::patch('conductores/{conductor}/documentos/{documento}/vencimiento', [ConductorDocumentoController::class, 'actualizarVencimiento'])
            ->name('conductores.documentos.vencimiento');
        Route::delete('conductores/{conductor}/documentos/{documento}', [ConductorDocumentoController::class, 'destroy'])
            ->name('conductores.documentos.destroy');
    });

    // Antes del resource para que `clientes/express` no se lea como el `show`
    // de un cliente.
    Route::post('clientes/express', [ClienteController::class, 'storeExpress'])
        ->name('clientes.express');
    Route::resource('clientes', ClienteController::class)
        ->parameters(['clientes' => 'cliente']);

    // El cotizador y el PDF van antes del resource para que
    // `cotizaciones/cotizador` no se lea como el `show` de una cotización.
    Route::get('cotizaciones/cotizador', [CotizacionController::class, 'cotizador'])
        ->name('cotizaciones.cotizador');
    Route::get('cotizaciones/{cotizacion}/pdf', [CotizacionController::class, 'pdf'])
        ->name('cotizaciones.pdf');
    Route::resource('cotizaciones', CotizacionController::class)
        ->parameters(['cotizaciones' => 'cotizacion']);

    Route::get('parametros-costo', [ParametroCostoController::class, 'edit'])
        ->name('parametros-costo.edit');
    Route::put('parametros-costo', [ParametroCostoController::class, 'update'])
        ->name('parametros-costo.update');
    Route::post('parametros-costo/componentes', [ParametroCostoController::class, 'storeComponente'])
        ->name('parametros-costo.componentes.store');
    Route::put('parametros-costo/componentes/{componente}', [ParametroCostoController::class, 'updateComponente'])
        ->name('parametros-costo.componentes.update');

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

    // La cobranza: la misma tabla de viajes leída desde el dinero. Las
    // cuentas van antes del resto para que `cuentas-bancarias` no se confunda
    // con un parámetro de contabilidad.
    Route::get('contabilidad', [ContabilidadController::class, 'index'])->name('contabilidad.index');
    // Antes de `cuentas` por lo mismo: `exportar` no es un parámetro.
    Route::get('contabilidad/exportar', [ContabilidadController::class, 'exportar'])
        ->name('contabilidad.exportar');
    Route::get('contabilidad/cuentas', [CuentaBancariaController::class, 'index'])
        ->name('cuentas-bancarias.index');
    Route::post('contabilidad/cuentas', [CuentaBancariaController::class, 'store'])
        ->name('cuentas-bancarias.store');
    Route::put('contabilidad/cuentas/{cuenta}', [CuentaBancariaController::class, 'update'])
        ->name('cuentas-bancarias.update');
    Route::delete('contabilidad/cuentas/{cuenta}', [CuentaBancariaController::class, 'destroy'])
        ->name('cuentas-bancarias.destroy');

    Route::post('facturas', [FacturaController::class, 'store'])->name('facturas.store');
    // PATCH y no PUT: la cobranza se edita celda por celda y cada guardado
    // manda solo el campo que se acaba de tocar.
    Route::patch('facturas/{factura}', [FacturaController::class, 'update'])->name('facturas.update');
    Route::delete('facturas/{factura}', [FacturaController::class, 'destroy'])->name('facturas.destroy');
    // Si el papel de la GR ya llegó a la oficina: lo marca la cobranza.
    Route::patch('viajes/{viaje}/gr-fisica', [ContabilidadController::class, 'marcarGrFisica'])
        ->name('contabilidad.gr-fisica');
    // Sacar un solo viaje de su factura, sin anular la factura entera.
    Route::delete('viajes/{viaje}/factura', [FacturaController::class, 'desvincular'])
        ->name('facturas.desvincular');

    // Qué unidades salen con carga particular cada día. Se carga antes de
    // que exista la GR, así que no se deduce de `viajes`.
    Route::get('programacion', [ProgramacionController::class, 'index'])->name('programacion.index');
    Route::post('programacion', [ProgramacionController::class, 'store'])->name('programacion.store');
    Route::put('programacion/{programacion}', [ProgramacionController::class, 'update'])
        ->name('programacion.update');
    Route::delete('programacion/{programacion}', [ProgramacionController::class, 'destroy'])
        ->name('programacion.destroy');

    Route::get('asistencia', [AsistenciaController::class, 'index'])->name('asistencia.index');
    Route::patch('asistencia/{conductor}', [AsistenciaController::class, 'marcar'])->name('asistencia.marcar');
    Route::patch('asistencia/{conductor}/dias-debidos', [AsistenciaController::class, 'actualizarDiasDebidos'])
        ->name('asistencia.diasDebidos');
    Route::patch('asistencia/{conductor}/notas', [AsistenciaController::class, 'actualizarNotas'])
        ->name('asistencia.notas');
    Route::delete('asistencia/{asistencia}', [AsistenciaController::class, 'destroy'])->name('asistencia.destroy');

    Route::resource('usuarios', UserController::class)
        ->parameters(['usuarios' => 'user'])
        ->except(['show']);
    Route::put('usuarios/{user}/password', [UserController::class, 'updatePassword'])
        ->name('usuarios.password.update');

    Route::get('tractos', [VehiculoController::class, 'tractos'])->name('tractos.index');
    Route::get('carretas', [VehiculoController::class, 'carretas'])->name('carretas.index');

    Route::resource('vehiculos', VehiculoController::class)->except(['index']);

    Route::scopeBindings()->group(function () {
        Route::get('vehiculos/{vehiculo}/documentos', [VehiculoDocumentoController::class, 'index'])
            ->name('vehiculos.documentos.index');
        Route::post('vehiculos/{vehiculo}/documentos', [VehiculoDocumentoController::class, 'store'])
            ->name('vehiculos.documentos.store');
        Route::patch('vehiculos/{vehiculo}/documentos/{documento}/vencimiento', [VehiculoDocumentoController::class, 'actualizarVencimiento'])
            ->name('vehiculos.documentos.vencimiento');
        Route::delete('vehiculos/{vehiculo}/documentos/{documento}', [VehiculoDocumentoController::class, 'destroy'])
            ->name('vehiculos.documentos.destroy');
    });
});

require __DIR__.'/settings.php';
