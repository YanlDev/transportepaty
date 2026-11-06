<?php

namespace App\Enums;

/**
 * Los roles base de la empresa, uno por área de trabajo del circuito del
 * ticket, con lo que le corresponde a cada uno. Viven en código (como los
 * permisos) para que todos los ambientes tengan los mismos y cada permiso
 * nuevo le llegue a quien le toca.
 *
 * Desde el panel se pueden ajustar y crear roles propios; lo de acá es el
 * punto de partida: se aplica al crear un permiso nuevo y al reordenar los
 * roles con una migración.
 *
 * Borrar (unidades, conductores, GR, clientes, cotizaciones) es solo del
 * administrador: deja huecos en el historial.
 */
enum RolBase: string
{
    case Admin = 'admin';
    case Gerencia = 'gerencia';
    case Operaciones = 'operaciones';
    case Taller = 'taller';
    case Abastecimiento = 'abastecimiento';
    case Contador = 'contador';
    case Rrhh = 'rrhh';

    /**
     * @return list<Permiso>
     */
    public function permisos(): array
    {
        return match ($this) {
            self::Admin => Permiso::cases(),

            // Ve todo y no modifica nada, salvo aprobar las excepciones:
            // autorizar combustible por encima del tope.
            self::Gerencia => [
                ...array_values(array_filter(Permiso::cases(), fn (Permiso $permiso): bool => str_ends_with($permiso->value, '.ver'))),
                Permiso::AbastecimientoExcederTope,
            ],

            // Programa los tickets, sube y asigna las GR, registra la llegada
            // y avisa. Mantiene el padrón de unidades, sin borrar.
            self::Operaciones => [
                Permiso::TableroVer,
                Permiso::ProgramacionVer, Permiso::ProgramacionCrear, Permiso::ProgramacionEditar,
                Permiso::ProgramacionEliminar, Permiso::ProgramacionAvisar,
                Permiso::ProgramacionAsignarGr, Permiso::ProgramacionLlegada,
                Permiso::ViajesVer, Permiso::ViajesRegistrar, Permiso::ViajesEmitir, Permiso::ViajesEditar, Permiso::ViajesAnular,
                Permiso::TallerVer, Permiso::AbastecimientoVer,
                Permiso::VehiculosVer, Permiso::VehiculosCrear, Permiso::VehiculosEditar,
                Permiso::ConductoresVer,
                Permiso::NovedadesRegistrar, Permiso::NovedadesLevantar,
                Permiso::ClientesVer, Permiso::CotizacionesVer,
            ],

            // El OK de salida de cada ticket y el mantenimiento de la flota.
            self::Taller => [
                Permiso::TableroVer, Permiso::ProgramacionVer,
                Permiso::TallerVer, Permiso::TallerRevisar,
                Permiso::VehiculosVer,
                Permiso::MantenimientoVer, Permiso::MantenimientoRegistrarKm, Permiso::MantenimientoGestionar,
                Permiso::MantenimientoLlantas, Permiso::MantenimientoEquipamiento,
                Permiso::NovedadesRegistrar, Permiso::NovedadesLevantar,
            ],

            // Autoriza el combustible, registra los vouchers, deposita los
            // viáticos y liquida los tickets. Lo ya registrado lo corrige el
            // admin; abastecimiento solo corrige el precio del voucher.
            self::Abastecimiento => [
                Permiso::TableroVer, Permiso::ProgramacionVer, Permiso::ProgramacionCerrar,
                Permiso::TallerVer, Permiso::VehiculosVer,
                Permiso::AbastecimientoVer, Permiso::AbastecimientoRegistrar,
                Permiso::AbastecimientoConfigurar, Permiso::AbastecimientoViaticos,
            ],

            // Factura, cobra, corrige viáticos y liquida los tickets.
            self::Contador => [
                Permiso::TableroVer, Permiso::ProgramacionVer, Permiso::ProgramacionCerrar,
                Permiso::ViajesVer, Permiso::VehiculosVer,
                Permiso::AbastecimientoVer, Permiso::AbastecimientoViaticos,
                Permiso::CobranzaVer, Permiso::CobranzaGestionar,
                Permiso::CuentasVer, Permiso::CuentasGestionar,
            ],

            // El padrón de conductores y la asistencia, sin borrar.
            self::Rrhh => [
                Permiso::TableroVer,
                Permiso::ConductoresVer, Permiso::ConductoresCrear, Permiso::ConductoresEditar,
                Permiso::AsistenciaVer, Permiso::AsistenciaMarcar, Permiso::AsistenciaAjustar,
            ],
        };
    }

    public function tiene(Permiso $permiso): bool
    {
        return in_array($permiso, $this->permisos(), true);
    }

    /**
     * @return list<string>
     */
    public static function valores(): array
    {
        return array_column(self::cases(), 'value');
    }
}
