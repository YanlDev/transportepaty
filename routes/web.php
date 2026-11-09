<?php

use App\Http\Controllers\AbastecimientoController;
use App\Http\Controllers\AdicionalTramoController;
use App\Http\Controllers\AlertaController;
use App\Http\Controllers\Almacen\AlmacenController;
use App\Http\Controllers\Almacen\ArticuloController;
use App\Http\Controllers\Almacen\ConfiguracionAlmacenController;
use App\Http\Controllers\Almacen\IngresoAlmacenController;
use App\Http\Controllers\Almacen\InventarioAlmacenController;
use App\Http\Controllers\Almacen\ProveedorController;
use App\Http\Controllers\Almacen\SalidaAlmacenController;
use App\Http\Controllers\AreaAvisoController;
use App\Http\Controllers\AsistenciaController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\ConciliacionCombustibleController;
use App\Http\Controllers\ConductorController;
use App\Http\Controllers\ConductorDocumentoController;
use App\Http\Controllers\ConfiguracionAbastecimientoController;
use App\Http\Controllers\ConfiguracionAlertaController;
use App\Http\Controllers\ConfiguracionController;
use App\Http\Controllers\ConfiguracionProgramacionController;
use App\Http\Controllers\ContabilidadController;
use App\Http\Controllers\CotizacionController;
use App\Http\Controllers\CuentaBancariaController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmisionGreController;
use App\Http\Controllers\EquipamientoCarretaController;
use App\Http\Controllers\FacturaController;
use App\Http\Controllers\GuiaPorAsignarController;
use App\Http\Controllers\HistorialController;
use App\Http\Controllers\LecturaKilometrajeController;
use App\Http\Controllers\LlantaController;
use App\Http\Controllers\MantenimientoController;
use App\Http\Controllers\NovedadController;
use App\Http\Controllers\OrdenTrabajoController;
use App\Http\Controllers\ParametroCostoController;
use App\Http\Controllers\PlanMantenimientoController;
use App\Http\Controllers\ReciboWhatsappController;
use App\Http\Controllers\RolController;
use App\Http\Controllers\RutaController;
use App\Http\Controllers\TallerController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VehiculoController;
use App\Http\Controllers\VehiculoDocumentoController;
use App\Http\Controllers\ViajeController;
use App\Http\Controllers\ViaticoController;
use App\Http\Controllers\VueltaController;
use App\Http\Controllers\WhatsappController;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Route;

// La raíz no muestra landing: siempre redirige al login (los usuarios ya
// autenticados son reenviados al dashboard por el middleware `guest` del login).
Route::redirect('/', '/login')->name('home');

// Los ✓✓ de WhatsApp: los manda el servicio Node del mismo servidor, con su
// token (ver ReciboWhatsappController), no una persona con sesión.
Route::post('whatsapp/recibos', ReciboWhatsappController::class)
    ->withoutMiddleware(PreventRequestForgery::class)
    ->name('whatsapp.recibos');

Route::middleware('auth')->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // La puerta única de la configuración: cada ajuste sigue en su
    // pantalla, acá se juntan.
    Route::get('configuracion', [ConfiguracionController::class, 'index'])->name('configuracion.index');

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

    // Armar una GR-transportista con los datos que SUNAT confirma en vivo.
    Route::get('viajes/emitir', [EmisionGreController::class, 'create'])->name('viajes.emitir');
    Route::post('viajes/emitir', [EmisionGreController::class, 'emitir'])->name('viajes.emitir.store');
    Route::post('viajes/emitir/registrar', [EmisionGreController::class, 'registrar'])
        ->name('viajes.emitir.registrar');
    Route::get('viajes/emitir/guia', [EmisionGreController::class, 'guia'])->name('viajes.emitir.guia');
    Route::get('viajes/emitir/vehiculos/{vehiculo}', [EmisionGreController::class, 'vehiculo'])
        ->name('viajes.emitir.vehiculo');
    Route::get('viajes/emitir/conductores/{conductor}', [EmisionGreController::class, 'conductor'])
        ->name('viajes.emitir.conductor');
    Route::patch('viajes/{viaje}/tipo-carga', [ViajeController::class, 'actualizarTipoCarga'])
        ->name('viajes.actualizarTipoCarga');
    Route::post('viajes/{viaje}/anulacion', [ViajeController::class, 'anular'])->name('viajes.anular');
    Route::delete('viajes/{viaje}/anulacion', [ViajeController::class, 'reactivar'])->name('viajes.reactivar');
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
    // Facturas en PDF: se leen, se registran y se asocian con las GR que citan.
    Route::post('facturas/importar', [FacturaController::class, 'importar'])->name('facturas.importar');
    // Asociar a mano, desde la bandeja «Facturas por asociar».
    Route::post('facturas/{factura}/viajes', [FacturaController::class, 'asociar'])->name('facturas.asociar');
    // PATCH y no PUT: la cobranza se edita celda por celda y cada guardado
    // manda solo el campo que se acaba de tocar.
    Route::patch('facturas/{factura}', [FacturaController::class, 'update'])->name('facturas.update');
    Route::delete('facturas/{factura}', [FacturaController::class, 'destroy'])->name('facturas.destroy');
    // Si el papel de la GR ya llegó a la oficina: lo marca la cobranza.
    Route::patch('viajes/{viaje}/gr-fisica', [ContabilidadController::class, 'marcarGrFisica'])
        ->name('contabilidad.gr-fisica');
    // GR que se emitió pero no se cobra (una cajita que viajó con la carga).
    Route::patch('viajes/{viaje}/no-facturable', [ContabilidadController::class, 'marcarNoFacturable'])
        ->name('contabilidad.no-facturable');
    // Sacar un solo viaje de su factura, sin anular la factura entera.
    Route::delete('facturas/{factura}/viajes/{viaje}', [FacturaController::class, 'desvincular'])
        ->name('facturas.desvincular');

    // Qué unidades salen con carga particular cada día. Se carga antes de
    // que exista la GR, así que no se deduce de `viajes`.
    // Taller: la bandeja de tickets por revisar y el apto o no apto de salida.
    Route::get('taller', [TallerController::class, 'index'])->name('taller.index');
    Route::post('vueltas/{vuelta}/revision-taller', [TallerController::class, 'store'])->name('taller.store');

    // Abastecimiento: el tablero con lo autorizado a cada ticket (la
    // tarjeta confirmada de cada tramo), el combustible autorizado, que se
    // anula y nunca se borra, y el despacho real del voucher; aparte, la
    // pizarra de dotaciones y la conciliación del mes.
    Route::get('abastecimiento', [AbastecimientoController::class, 'index'])->name('abastecimiento.index');
    Route::get('abastecimiento/conciliacion', [ConciliacionCombustibleController::class, 'index'])->name('abastecimiento.conciliacion');
    Route::post('abastecimiento', [AbastecimientoController::class, 'store'])->name('abastecimiento.store');
    Route::post('abastecimiento/{abastecimiento}/anular', [AbastecimientoController::class, 'anular'])->name('abastecimiento.anular');
    Route::put('abastecimiento/{abastecimiento}', [AbastecimientoController::class, 'update'])->whereNumber('abastecimiento')->name('abastecimiento.update');
    Route::put('abastecimiento/{abastecimiento}/precio', [AbastecimientoController::class, 'precio'])->name('abastecimiento.precio');
    Route::put('abastecimiento/{abastecimiento}/despacho', [AbastecimientoController::class, 'despacho'])->name('abastecimiento.despacho');
    Route::post('rutas-vuelta/{rutaVuelta}/variante', [AbastecimientoController::class, 'elegirVariante'])->name('abastecimiento.variante');

    // El catálogo de rutas: el recorrido y la dotación de cada tramo
    // estándar. Lo que se elige al programar y lo que abastecimiento ejecuta.
    Route::get('rutas', [RutaController::class, 'index'])->name('rutas.index');
    Route::post('rutas', [RutaController::class, 'store'])->name('rutas.store');
    Route::put('rutas/{ruta}', [RutaController::class, 'update'])->name('rutas.update');
    Route::delete('rutas/{ruta}', [RutaController::class, 'destroy'])->name('rutas.destroy');
    // Los viáticos de cada ticket: se depositan, se corrigen y se anulan;
    // nunca se borran.
    Route::post('abastecimiento/viaticos', [ViaticoController::class, 'store'])->name('viaticos.store');
    Route::put('abastecimiento/viaticos/{viatico}', [ViaticoController::class, 'update'])->name('viaticos.update');
    Route::post('abastecimiento/viaticos/{viatico}/anular', [ViaticoController::class, 'anular'])->name('viaticos.anular');
    Route::get('abastecimiento/configuracion', [ConfiguracionAbastecimientoController::class, 'index'])
        ->name('abastecimiento.configuracion');
    Route::put('abastecimiento/capacidades', [ConfiguracionAbastecimientoController::class, 'guardarCapacidades'])
        ->name('abastecimiento.capacidades');
    Route::post('abastecimiento/puntos', [ConfiguracionAbastecimientoController::class, 'storePunto'])
        ->name('abastecimiento.puntos.store');
    Route::put('abastecimiento/puntos/{punto}', [ConfiguracionAbastecimientoController::class, 'updatePunto'])
        ->name('abastecimiento.puntos.update');
    Route::delete('abastecimiento/puntos/{punto}', [ConfiguracionAbastecimientoController::class, 'destroyPunto'])
        ->name('abastecimiento.puntos.destroy');

    // La configuración de la programación: destinos de la carga particular
    // y palabras de cada zona. Antes que las rutas con
    // {vuelta} para que «configuracion» no se lea como una vuelta.
    Route::get('vueltas/configuracion', [ConfiguracionProgramacionController::class, 'index'])
        ->name('programacion.configuracion');
    Route::post('vueltas/configuracion/destinos', [ConfiguracionProgramacionController::class, 'storeDestino'])
        ->name('programacion.destinos.store');
    Route::put('vueltas/configuracion/destinos/{destino}', [ConfiguracionProgramacionController::class, 'updateDestino'])
        ->name('programacion.destinos.update');
    Route::delete('vueltas/configuracion/destinos/{destino}', [ConfiguracionProgramacionController::class, 'destroyDestino'])
        ->name('programacion.destinos.destroy');
    Route::post('vueltas/configuracion/palabras', [ConfiguracionProgramacionController::class, 'storePalabra'])
        ->name('programacion.palabras.store');
    Route::delete('vueltas/configuracion/palabras/{palabra}', [ConfiguracionProgramacionController::class, 'destroyPalabra'])
        ->name('programacion.palabras.destroy');

    // Las GR por asignar: ninguna cae sola en un ticket. Quien programa la
    // asigna (o la marca sin ticket, con motivo) y la puede volver a sacar.
    Route::get('vueltas/guias', [GuiaPorAsignarController::class, 'index'])->name('vueltas.guias');
    Route::post('viajes/{viaje}/ticket', [GuiaPorAsignarController::class, 'asignar'])->name('viajes.ticket.asignar');
    Route::delete('viajes/{viaje}/ticket', [GuiaPorAsignarController::class, 'quitar'])->name('viajes.ticket.quitar');
    Route::post('viajes/{viaje}/sin-ticket', [GuiaPorAsignarController::class, 'sinTicket'])->name('viajes.sinTicket');
    Route::delete('viajes/{viaje}/sin-ticket', [GuiaPorAsignarController::class, 'devolver'])->name('viajes.sinTicket.devolver');

    // El ticket de cada unidad: ida y vuelta, programado por una persona.
    Route::get('vueltas', [VueltaController::class, 'index'])->name('vueltas.index');
    Route::get('vueltas/unidades/{vehiculo}', [VueltaController::class, 'unidad'])->name('vueltas.unidad');
    Route::get('vueltas/{vuelta}', [VueltaController::class, 'show'])->whereNumber('vuelta')->name('vueltas.show');
    Route::post('vueltas', [VueltaController::class, 'store'])->name('vueltas.store');
    Route::put('vueltas/{vuelta}', [VueltaController::class, 'update'])->name('vueltas.update');
    Route::delete('vueltas/{vuelta}', [VueltaController::class, 'destroy'])->name('vueltas.destroy');
    Route::post('vueltas/{vuelta}/llegada', [VueltaController::class, 'registrarLlegada'])->name('vueltas.llegada');
    Route::post('vueltas/{vuelta}/adicionales', [AdicionalTramoController::class, 'store'])->name('vueltas.adicionales.store');
    Route::delete('vueltas/{vuelta}/adicionales/{adicional}', [AdicionalTramoController::class, 'destroy'])->name('vueltas.adicionales.destroy');
    Route::post('vueltas/{vuelta}/cerrar', [VueltaController::class, 'cerrar'])->name('vueltas.cerrar');
    Route::post('vueltas/{vuelta}/reabrir', [VueltaController::class, 'reabrir'])->name('vueltas.reabrir');
    Route::post('vueltas/{vuelta}/aviso', [VueltaController::class, 'avisarConductor'])->name('vueltas.aviso');
    Route::post('vueltas/{vuelta}/aviso-area/{area}', [VueltaController::class, 'avisarArea'])->name('vueltas.avisoArea');

    // La pantalla de salidas de antes: quien la tenga en favoritos llega a
    // los tickets.
    Route::redirect('programacion', '/vueltas');

    // Mantenimiento: el semáforo de la flota, el km que se anota al llegar a
    // base y las órdenes de taller. `ordenes/crear` va antes que
    // `ordenes/{orden}` para no leerse como una orden.
    Route::get('mantenimiento', [MantenimientoController::class, 'index'])->name('mantenimiento.index');
    Route::get('mantenimiento/preventivo', [MantenimientoController::class, 'preventivo'])
        ->name('mantenimiento.preventivo');
    Route::get('mantenimiento/unidades/{vehiculo}', [MantenimientoController::class, 'show'])
        ->name('mantenimiento.show');
    Route::post('mantenimiento/kilometrajes', [LecturaKilometrajeController::class, 'store'])
        ->name('mantenimiento.kilometrajes.store');
    Route::delete('mantenimiento/kilometrajes/{lectura}', [LecturaKilometrajeController::class, 'destroy'])
        ->name('mantenimiento.kilometrajes.destroy');
    Route::get('mantenimiento/ordenes/crear', [OrdenTrabajoController::class, 'create'])
        ->name('mantenimiento.ordenes.create');
    Route::post('mantenimiento/ordenes', [OrdenTrabajoController::class, 'store'])
        ->name('mantenimiento.ordenes.store');
    Route::get('mantenimiento/ordenes/{orden}/editar', [OrdenTrabajoController::class, 'edit'])
        ->name('mantenimiento.ordenes.edit');
    // PUT con fotos: llega como POST con `_method`, porque PHP no lee
    // archivos de un PUT multipart.
    Route::put('mantenimiento/ordenes/{orden}', [OrdenTrabajoController::class, 'update'])
        ->name('mantenimiento.ordenes.update');
    Route::delete('mantenimiento/ordenes/{orden}', [OrdenTrabajoController::class, 'destroy'])
        ->name('mantenimiento.ordenes.destroy');
    Route::delete('mantenimiento/ordenes/{orden}/fotos/{foto}', [OrdenTrabajoController::class, 'eliminarFoto'])
        ->name('mantenimiento.ordenes.fotos.destroy');
    Route::get('mantenimiento/llantas', [LlantaController::class, 'index'])
        ->name('mantenimiento.llantas.index');
    Route::get('mantenimiento/llantas/{vehiculo}', [LlantaController::class, 'show'])
        ->name('mantenimiento.llantas.show');
    Route::post('mantenimiento/llantas/{vehiculo}/movimientos', [LlantaController::class, 'store'])
        ->name('mantenimiento.llantas.store');
    Route::post('mantenimiento/llantas/{vehiculo}/mediciones', [LlantaController::class, 'medir'])
        ->name('mantenimiento.llantas.medir');
    Route::put('mantenimiento/llantas/montadas/{llanta}', [LlantaController::class, 'update'])
        ->name('mantenimiento.llantas.update');
    Route::put('mantenimiento/llantas/movimientos/{movimiento}', [LlantaController::class, 'corregirMovimiento'])
        ->name('mantenimiento.llantas.movimientos.update');
    Route::delete('mantenimiento/llantas/movimientos/{movimiento}', [LlantaController::class, 'eliminarMovimiento'])
        ->name('mantenimiento.llantas.movimientos.destroy');
    Route::put('mantenimiento/llantas-parametros', [LlantaController::class, 'actualizarParametros'])
        ->name('mantenimiento.llantas.parametros.update');
    Route::get('mantenimiento/equipamiento', [EquipamientoCarretaController::class, 'index'])
        ->name('mantenimiento.equipamiento.index');
    Route::put('mantenimiento/equipamiento/catalogo', [EquipamientoCarretaController::class, 'actualizarCatalogo'])
        ->name('mantenimiento.equipamiento.catalogo');
    Route::get('mantenimiento/equipamiento/{vehiculo}', [EquipamientoCarretaController::class, 'show'])
        ->name('mantenimiento.equipamiento.show');
    Route::post('mantenimiento/equipamiento/{vehiculo}/conteos', [EquipamientoCarretaController::class, 'store'])
        ->name('mantenimiento.equipamiento.store');
    Route::delete('mantenimiento/equipamiento/conteos/{conteo}', [EquipamientoCarretaController::class, 'destroy'])
        ->name('mantenimiento.equipamiento.destroy');
    Route::get('mantenimiento/planes/{plan}/editar', [PlanMantenimientoController::class, 'edit'])
        ->name('mantenimiento.planes.edit');
    Route::put('mantenimiento/planes/{plan}', [PlanMantenimientoController::class, 'update'])
        ->name('mantenimiento.planes.update');

    // Almacén del taller: el stock, el catálogo, lo que entra (compras y
    // devoluciones), lo que sale (vales) y los conteos. Las rutas fijas
    // (`crear`, `exportar`) van antes que las de un documento.
    Route::prefix('almacen')->name('almacen.')->group(function () {
        Route::get('/', [AlmacenController::class, 'index'])->name('index');
        Route::get('stock/exportar', [AlmacenController::class, 'exportar'])->name('exportar');
        Route::get('kardex/exportar', [ArticuloController::class, 'exportarKardexGeneral'])->name('kardex.exportar');

        Route::get('articulos/crear', [ArticuloController::class, 'create'])->name('articulos.create');
        Route::post('articulos', [ArticuloController::class, 'store'])->name('articulos.store');
        Route::get('articulos/{articulo}', [ArticuloController::class, 'show'])->name('articulos.show');
        Route::get('articulos/{articulo}/editar', [ArticuloController::class, 'edit'])->name('articulos.edit');
        Route::put('articulos/{articulo}', [ArticuloController::class, 'update'])->name('articulos.update');
        Route::get('articulos/{articulo}/kardex', [ArticuloController::class, 'exportarKardex'])->name('articulos.kardex');

        Route::get('configuracion', [ConfiguracionAlmacenController::class, 'index'])->name('configuracion');
        Route::post('configuracion/categorias', [ConfiguracionAlmacenController::class, 'crearCategoria'])->name('categorias.store');
        Route::put('configuracion/categorias/{categoria}', [ConfiguracionAlmacenController::class, 'renombrarCategoria'])->name('categorias.update');
        Route::delete('configuracion/categorias/{categoria}', [ConfiguracionAlmacenController::class, 'quitarCategoria'])->name('categorias.destroy');
        Route::post('configuracion/ubicaciones', [ConfiguracionAlmacenController::class, 'crearUbicacion'])->name('ubicaciones.store');
        Route::put('configuracion/ubicaciones/{ubicacion}', [ConfiguracionAlmacenController::class, 'editarUbicacion'])->name('ubicaciones.update');
        Route::delete('configuracion/ubicaciones/{ubicacion}', [ConfiguracionAlmacenController::class, 'quitarUbicacion'])->name('ubicaciones.destroy');
        Route::put('configuracion/minimos', [ConfiguracionAlmacenController::class, 'guardarMinimos'])->name('minimos.update');

        Route::get('proveedores', [ProveedorController::class, 'index'])->name('proveedores.index');
        Route::post('proveedores', [ProveedorController::class, 'store'])->name('proveedores.store');
        Route::put('proveedores/{proveedor}', [ProveedorController::class, 'update'])->name('proveedores.update');

        Route::get('ingresos', [IngresoAlmacenController::class, 'index'])->name('ingresos.index');
        Route::get('ingresos/crear', [IngresoAlmacenController::class, 'create'])->name('ingresos.create');
        Route::post('ingresos', [IngresoAlmacenController::class, 'store'])->name('ingresos.store');
        Route::get('ingresos/{ingreso}', [IngresoAlmacenController::class, 'show'])->name('ingresos.show');
        Route::get('ingresos/{ingreso}/comprobante', [IngresoAlmacenController::class, 'comprobante'])->name('ingresos.comprobante');
        Route::post('ingresos/{ingreso}/anular', [IngresoAlmacenController::class, 'anular'])->name('ingresos.anular');

        Route::get('salidas', [SalidaAlmacenController::class, 'index'])->name('salidas.index');
        Route::get('salidas/crear', [SalidaAlmacenController::class, 'create'])->name('salidas.create');
        Route::post('salidas', [SalidaAlmacenController::class, 'store'])->name('salidas.store');
        Route::get('salidas/{salida}', [SalidaAlmacenController::class, 'show'])->name('salidas.show');
        Route::post('salidas/{salida}/devolver', [SalidaAlmacenController::class, 'devolver'])->name('salidas.devolver');
        Route::post('salidas/{salida}/anular', [SalidaAlmacenController::class, 'anular'])->name('salidas.anular');

        Route::get('inventarios', [InventarioAlmacenController::class, 'index'])->name('inventarios.index');
        Route::post('inventarios', [InventarioAlmacenController::class, 'store'])->name('inventarios.store');
        Route::get('inventarios/{inventario}', [InventarioAlmacenController::class, 'show'])->name('inventarios.show');
        Route::post('inventarios/{inventario}/lineas', [InventarioAlmacenController::class, 'guardarLinea'])->name('inventarios.lineas.store');
        Route::delete('inventarios/{inventario}/lineas/{detalle}', [InventarioAlmacenController::class, 'quitarLinea'])->name('inventarios.lineas.destroy');
        Route::post('inventarios/{inventario}/cerrar', [InventarioAlmacenController::class, 'cerrar'])->name('inventarios.cerrar');
        Route::post('inventarios/{inventario}/anular', [InventarioAlmacenController::class, 'anular'])->name('inventarios.anular');
    });

    Route::get('asistencia', [AsistenciaController::class, 'index'])->name('asistencia.index');
    Route::patch('asistencia/{conductor}', [AsistenciaController::class, 'marcar'])->name('asistencia.marcar');
    Route::patch('asistencia/{conductor}/dias-debidos', [AsistenciaController::class, 'actualizarDiasDebidos'])
        ->name('asistencia.diasDebidos');
    Route::patch('asistencia/{conductor}/notas', [AsistenciaController::class, 'actualizarNotas'])
        ->name('asistencia.notas');
    Route::delete('asistencia/{asistencia}', [AsistenciaController::class, 'destroy'])->name('asistencia.destroy');

    // Configuración del sistema: vive bajo /settings, junto a la cuenta
    // propia. Los nombres de ruta no cambian; las URL viejas redirigen.
    Route::prefix('settings')->group(function () {
        Route::resource('usuarios', UserController::class)
            ->parameters(['usuarios' => 'user'])
            ->except(['show', 'destroy']);
        // Nadie se borra: se da de baja y se puede reactivar.
        Route::post('usuarios/{user}/baja', [UserController::class, 'darDeBaja'])->name('usuarios.baja');
        Route::post('usuarios/{user}/reactivar', [UserController::class, 'reactivar'])->name('usuarios.reactivar');
        Route::put('usuarios/{user}/password', [UserController::class, 'updatePassword'])
            ->name('usuarios.password.update');

        Route::get('roles', [RolController::class, 'index'])->name('roles.index');
        Route::post('roles', [RolController::class, 'store'])->name('roles.store');
        Route::put('roles/{rol}', [RolController::class, 'update'])->name('roles.update');
        Route::delete('roles/{rol}', [RolController::class, 'destroy'])->name('roles.destroy');

        Route::get('historial', [HistorialController::class, 'index'])->name('historial.index');
        Route::get('whatsapp', [WhatsappController::class, 'index'])->name('whatsapp.index');
        Route::post('whatsapp/vincular', [WhatsappController::class, 'vincular'])->name('whatsapp.vincular');
        Route::post('whatsapp/probar', [WhatsappController::class, 'probar'])->name('whatsapp.probar');
        Route::post('whatsapp/desvincular', [WhatsappController::class, 'desvincular'])->name('whatsapp.desvincular');
        Route::put('whatsapp/oficina', [WhatsappController::class, 'actualizarOficina'])->name('whatsapp.oficina');
        Route::put('whatsapp/recordatorio', [WhatsappController::class, 'actualizarRecordatorio'])->name('whatsapp.recordatorio');
        Route::post('whatsapp/areas', [AreaAvisoController::class, 'store'])->name('whatsapp.areas.store');
        Route::put('whatsapp/areas/{area}', [AreaAvisoController::class, 'update'])->name('whatsapp.areas.update');
        Route::delete('whatsapp/areas/{area}', [AreaAvisoController::class, 'destroy'])->name('whatsapp.areas.destroy');

        Route::get('alertas', [ConfiguracionAlertaController::class, 'index'])->name('alertas.configuracion');
        Route::put('alertas/{tipo}', [ConfiguracionAlertaController::class, 'update'])->name('alertas.configuracion.update');
        Route::post('alertas/{tipo}/probar', [ConfiguracionAlertaController::class, 'probar'])->name('alertas.configuracion.probar');
    });

    // La bandeja de alertas de cada quien: sin permiso, cada uno ve las suyas.
    Route::get('alertas', [AlertaController::class, 'index'])->name('alertas.index');
    Route::post('alertas/leidas', [AlertaController::class, 'leerTodas'])->name('alertas.leidas');
    Route::post('alertas/{alerta}/abrir', [AlertaController::class, 'abrir'])->name('alertas.abrir');

    Route::redirect('usuarios', '/settings/usuarios');
    Route::redirect('roles', '/settings/roles');
    Route::redirect('whatsapp', '/settings/whatsapp');

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
