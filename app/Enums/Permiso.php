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

    /**
     * Los módulos en el orden en que los muestra el panel, con su nombre.
     *
     * @var array<string, string>
     */
    public const MODULOS = [
        'tablero' => 'Tablero',
        'vehiculos' => 'Vehículos',
        'conductores' => 'Conductores',
        'novedades' => 'Novedades de unidades',
        'viajes' => 'Viajes',
        'programacion' => 'Programación',
        'asistencia' => 'Asistencia',
        'clientes' => 'Clientes',
        'cotizaciones' => 'Cotizaciones',
        'costos' => 'Parámetros de costo',
        'cobranza' => 'Cobranza',
        'cuentas' => 'Cuentas bancarias',
        'whatsapp' => 'WhatsApp',
    ];

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
        };
    }

    /**
     * A qué roles base se les da el permiso la primera vez que aparece.
     *
     * Solo se aplica al crearlo: si después alguien lo quita desde el panel,
     * volver a sincronizar no lo repone. El admin no figura porque siempre
     * tiene todo.
     *
     * @return list<string>
     */
    public function rolesPorDefecto(): array
    {
        return match ($this) {
            self::TableroVer,
            self::VehiculosVer,
            self::ViajesVer => ['visor', 'contador'],
            self::ConductoresVer,
            self::ProgramacionVer,
            self::ClientesVer,
            self::CotizacionesVer => ['visor'],
            self::CobranzaVer,
            self::CobranzaGestionar,
            self::CuentasVer,
            self::CuentasGestionar => ['contador'],
            default => [],
        };
    }

    /**
     * @return list<string>
     */
    public static function valores(): array
    {
        return array_column(self::cases(), 'value');
    }
}
