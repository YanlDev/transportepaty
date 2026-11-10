<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * El catálogo de lo que se puede hacer en el sistema.
 *
 * Vive en código y no en la base porque cada permiso tiene que estar
 * chequeado en alguna policy: uno que existiera solo en la tabla sería una
 * casilla en el panel que no protege nada. La base guarda a quién se le dio
 * cada uno; `SincronizadorPermisos` mantiene las dos cosas alineadas.
 *
 * El valor es `modulo.accion`: el prefijo agrupa la matriz del panel de roles.
 */
enum Permiso: string
{
    use HasLabel;

    case TableroVer = 'tablero.ver';

    case VehiculosVer = 'vehiculos.ver';
    case VehiculosCrear = 'vehiculos.crear';
    case VehiculosEditar = 'vehiculos.editar';
    case VehiculosEliminar = 'vehiculos.eliminar';

    case ConductoresVer = 'conductores.ver';
    case ConductoresCrear = 'conductores.crear';
    case ConductoresEditar = 'conductores.editar';
    case ConductoresEliminar = 'conductores.eliminar';

    case MantenimientoVer = 'mantenimiento.ver';
    case MantenimientoRegistrarKm = 'mantenimiento.registrar_km';
    case MantenimientoGestionar = 'mantenimiento.gestionar';
    case MantenimientoAnular = 'mantenimiento.anular';
    case MantenimientoCargaInicial = 'mantenimiento.carga_inicial';
    case MantenimientoConfigurar = 'mantenimiento.configurar';
    case MantenimientoLlantas = 'mantenimiento.llantas';
    case MantenimientoEquipamiento = 'mantenimiento.equipamiento';

    case AlmacenVer = 'almacen.ver';
    case AlmacenVerCostos = 'almacen.ver_costos';
    case AlmacenCatalogo = 'almacen.catalogo';
    case AlmacenIngresar = 'almacen.ingresar';
    case AlmacenDespachar = 'almacen.despachar';
    case AlmacenInventariar = 'almacen.inventariar';
    case AlmacenAnular = 'almacen.anular';

    case ComprasVer = 'compras.ver';
    case ComprasSolicitar = 'compras.solicitar';
    case ComprasAprobar = 'compras.aprobar';
    case ComprasAprobarEmergencia = 'compras.aprobar_emergencia';
    case ComprasParciales = 'compras.parciales';
    case ComprasAnular = 'compras.anular';

    case TallerVer = 'taller.ver';
    case TallerRevisar = 'taller.revisar';

    case NovedadesRegistrar = 'novedades.registrar';
    case NovedadesLevantar = 'novedades.levantar';

    case ViajesVer = 'viajes.ver';
    case ViajesRegistrar = 'viajes.registrar';
    case ViajesEmitir = 'viajes.emitir';
    case ViajesEditar = 'viajes.editar';
    case ViajesAnular = 'viajes.anular';
    case ViajesEliminar = 'viajes.eliminar';

    case ProgramacionVer = 'programacion.ver';
    case ProgramacionCrear = 'programacion.crear';
    case ProgramacionEditar = 'programacion.editar';
    case ProgramacionEliminar = 'programacion.eliminar';
    case ProgramacionAvisar = 'programacion.avisar';
    case ProgramacionConfigurar = 'programacion.configurar';
    case ProgramacionCerrar = 'programacion.cerrar';
    case ProgramacionAsignarGr = 'programacion.asignar_gr';
    case ProgramacionLlegada = 'programacion.llegada';

    case AbastecimientoVer = 'abastecimiento.ver';
    case AbastecimientoRegistrar = 'abastecimiento.registrar';
    case AbastecimientoConfigurar = 'abastecimiento.configurar';
    case AbastecimientoViaticos = 'abastecimiento.viaticos';
    case AbastecimientoExcederTope = 'abastecimiento.exceder_tope';
    case AbastecimientoCorregir = 'abastecimiento.corregir';

    case AsistenciaVer = 'asistencia.ver';
    case AsistenciaMarcar = 'asistencia.marcar';
    case AsistenciaAjustar = 'asistencia.ajustar';

    case ClientesVer = 'clientes.ver';
    case ClientesCrear = 'clientes.crear';
    case ClientesEditar = 'clientes.editar';
    case ClientesEliminar = 'clientes.eliminar';

    case CotizacionesVer = 'cotizaciones.ver';
    case CotizacionesCrear = 'cotizaciones.crear';
    case CotizacionesEditar = 'cotizaciones.editar';
    case CotizacionesEliminar = 'cotizaciones.eliminar';

    case CostosEditar = 'costos.editar';

    case CobranzaVer = 'cobranza.ver';
    case CobranzaGestionar = 'cobranza.gestionar';

    case CuentasVer = 'cuentas.ver';
    case CuentasGestionar = 'cuentas.gestionar';

    case WhatsappAdministrar = 'whatsapp.administrar';

    case AlertasConfigurar = 'alertas.configurar';

    case HistorialVer = 'historial.ver';

    /**
     * Los módulos en el orden en que los muestra el panel, con su nombre.
     *
     * @var array<string, string>
     */
    public const MODULOS = [
        'tablero' => 'Inicio',
        'programacion' => 'Programación (tickets)',
        'taller' => 'Taller (OK de salida)',
        'abastecimiento' => 'Abastecimiento',
        'viajes' => 'Guías de remisión',
        'vehiculos' => 'Unidades',
        'mantenimiento' => 'Mantenimiento',
        'novedades' => 'Novedades de unidades',
        'conductores' => 'Conductores',
        'asistencia' => 'Asistencia',
        'almacen' => 'Almacén',
        'compras' => 'Compras (vales de compra)',
        'cotizaciones' => 'Cotizaciones',
        'clientes' => 'Clientes',
        'costos' => 'Parámetros de costo',
        'cobranza' => 'Cobranza',
        'cuentas' => 'Cuentas bancarias',
        'whatsapp' => 'WhatsApp',
        'alertas' => 'Alertas',
        'historial' => 'Historial de movimientos',
    ];

    /**
     * Las áreas de la empresa y sus módulos. Es la misma división que el menú:
     * Operación es todo lo que le pasa a un ticket (programarlo, el OK de
     * taller, el combustible, sus GR); Flota, las unidades y quienes las
     * manejan. El panel de permisos se agrupa así para que se vea de un
     * vistazo qué toca cada rol.
     *
     * @var array<string, array{nombre: string, modulos: list<string>}>
     */
    public const AREAS = [
        'general' => ['nombre' => 'General', 'modulos' => ['tablero']],
        'operacion' => ['nombre' => 'Operación', 'modulos' => ['programacion', 'taller', 'abastecimiento', 'viajes']],
        'flota' => ['nombre' => 'Flota', 'modulos' => ['vehiculos', 'mantenimiento', 'novedades', 'conductores', 'asistencia']],
        'almacen' => ['nombre' => 'Almacén', 'modulos' => ['almacen', 'compras']],
        'comercial' => ['nombre' => 'Comercial', 'modulos' => ['cotizaciones', 'clientes', 'costos']],
        'finanzas' => ['nombre' => 'Finanzas', 'modulos' => ['cobranza', 'cuentas']],
        'sistema' => ['nombre' => 'Sistema', 'modulos' => ['whatsapp', 'alertas', 'historial']],
    ];

    /**
     * El nombre del área a la que pertenece un módulo.
     */
    public static function areaDeModulo(string $modulo): string
    {
        foreach (self::AREAS as $area) {
            if (in_array($modulo, $area['modulos'], true)) {
                return $area['nombre'];
            }
        }

        return 'Otros';
    }

    public function modulo(): string
    {
        return explode('.', $this->value, 2)[0];
    }

    public function label(): string
    {
        return match ($this) {
            self::TableroVer => 'Ver el tablero',
            self::VehiculosVer => 'Ver tractos y carretas',
            self::VehiculosCrear => 'Registrar unidades',
            self::VehiculosEditar => 'Editar unidades y sus documentos',
            self::VehiculosEliminar => 'Eliminar unidades',
            self::ConductoresVer => 'Ver conductores',
            self::ConductoresCrear => 'Registrar conductores',
            self::ConductoresEditar => 'Editar conductores y sus documentos',
            self::ConductoresEliminar => 'Eliminar conductores',
            self::MantenimientoVer => 'Ver el mantenimiento de la flota',
            self::MantenimientoRegistrarKm => 'Registrar kilometrajes',
            self::MantenimientoGestionar => 'Registrar y editar órdenes de trabajo',
            self::MantenimientoAnular => 'Anular órdenes de trabajo y movimientos de equipamiento',
            self::MantenimientoCargaInicial => 'Cargar llantas que ya estaban montadas antes del almacén',
            self::MantenimientoConfigurar => 'Configurar el plan de cada marca',
            self::MantenimientoLlantas => 'Registrar instalaciones, mediciones y cambios de llantas',
            self::MantenimientoEquipamiento => 'Contar el equipamiento de las carretas',
            self::AlmacenVer => 'Ver el stock, el kardex y los documentos del almacén',
            self::AlmacenVerCostos => 'Ver los costos y el inventario valorizado',
            self::AlmacenCatalogo => 'Registrar y editar artículos y proveedores',
            self::AlmacenIngresar => 'Registrar compras y devoluciones',
            self::AlmacenDespachar => 'Emitir vales de salida',
            self::AlmacenInventariar => 'Hacer y cerrar los conteos de inventario',
            self::AlmacenAnular => 'Anular ingresos, vales y conteos',
            self::ComprasVer => 'Ver los vales de compra',
            self::ComprasSolicitar => 'Pedir compras y confirmar servicios de terceros',
            self::ComprasAprobar => 'Aprobar y rechazar compras',
            self::ComprasAprobarEmergencia => 'Aprobar compras de emergencia en ruta',
            self::ComprasParciales => 'Ver y activar las entregas parciales de un vale',
            self::ComprasAnular => 'Anular vales de compra',
            self::TallerVer => 'Ver los tickets por revisar',
            self::TallerRevisar => 'Dar el apto o no apto de salida',
            self::NovedadesRegistrar => 'Registrar novedades',
            self::NovedadesLevantar => 'Levantar novedades',
            self::ViajesVer => 'Ver viajes',
            self::ViajesRegistrar => 'Subir GR y registrar viajes',
            self::ViajesEmitir => 'Emitir GR en SUNAT',
            self::ViajesEditar => 'Corregir el tipo de carga',
            self::ViajesAnular => 'Anular y reactivar GR',
            self::ViajesEliminar => 'Eliminar viajes',
            self::ProgramacionVer => 'Ver la programación',
            self::ProgramacionCrear => 'Programar salidas',
            self::ProgramacionEditar => 'Editar salidas',
            self::ProgramacionEliminar => 'Quitar salidas',
            self::ProgramacionAvisar => 'Avisar salidas por WhatsApp',
            self::ProgramacionConfigurar => 'Configurar opciones de tramo, destinos y zonas',
            self::ProgramacionCerrar => 'Liquidar, cerrar y reabrir tickets',
            self::ProgramacionAsignarGr => 'Asignar las GR a los tickets (o marcarlas sin ticket)',
            self::ProgramacionLlegada => 'Registrar la llegada a la base',
            self::AbastecimientoVer => 'Ver el abastecimiento',
            self::AbastecimientoRegistrar => 'Confirmar la tarjeta, autorizar combustible y registrar el despacho',
            self::AbastecimientoConfigurar => 'Configurar puntos y reglas de dotación',
            self::AbastecimientoViaticos => 'Depositar, corregir y anular viáticos',
            self::AbastecimientoExcederTope => 'Autorizar combustible por encima del tope de la tarjeta',
            self::AbastecimientoCorregir => 'Editar y anular el combustible ya registrado y corregir su voucher',
            self::AsistenciaVer => 'Ver la asistencia',
            self::AsistenciaMarcar => 'Marcar y desmarcar días',
            self::AsistenciaAjustar => 'Ajustar días debidos y notas',
            self::ClientesVer => 'Ver clientes',
            self::ClientesCrear => 'Registrar clientes',
            self::ClientesEditar => 'Editar clientes',
            self::ClientesEliminar => 'Eliminar clientes',
            self::CotizacionesVer => 'Ver cotizaciones',
            self::CotizacionesCrear => 'Cotizar',
            self::CotizacionesEditar => 'Editar cotizaciones',
            self::CotizacionesEliminar => 'Eliminar cotizaciones',
            self::CostosEditar => 'Editar los costos de la flota',
            self::CobranzaVer => 'Ver la cobranza',
            self::CobranzaGestionar => 'Facturar y registrar cobros',
            self::CuentasVer => 'Ver las cuentas',
            self::CuentasGestionar => 'Administrar las cuentas',
            self::WhatsappAdministrar => 'Vincular el número y las áreas de aviso',
            self::AlertasConfigurar => 'Configurar qué alertas salen, por dónde y a quién',
            self::HistorialVer => 'Ver el historial de movimientos del sistema',
        };
    }

    /**
     * A qué roles base se les da el permiso la primera vez que aparece: los
     * que lo tienen en `RolBase`. Solo se aplica al crearlo: si después
     * alguien lo quita desde el panel, volver a sincronizar no lo repone. El
     * admin no figura porque siempre tiene todo.
     *
     * @return list<string>
     */
    public function rolesPorDefecto(): array
    {
        return array_values(array_map(
            fn (RolBase $rol): string => $rol->value,
            array_filter(RolBase::cases(), fn (RolBase $rol): bool => $rol !== RolBase::Admin && $rol->tiene($this)),
        ));
    }

    /**
     * @return list<string>
     */
    public static function valores(): array
    {
        return array_column(self::cases(), 'value');
    }
}
