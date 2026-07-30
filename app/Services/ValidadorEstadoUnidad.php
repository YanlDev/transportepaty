<?php

namespace App\Services;

use App\Enums\TipoAlerta;
use App\Models\Asignacion;
use App\Models\EstadoUnidad;

/**
 * Comprueba lo que se puede juzgar mirando un solo estado diario, sin compararlo
 * con nada anterior: que la ruta corresponda a la carga, que la unidad esté
 * donde dice estar, y que los fierros y el conductor sean los de la asignación
 * vigente.
 *
 * Vive sobre el estado canónico y no dentro del importador a propósito: un error
 * de ruta lo es igual venga del Excel, del GPS o escrito a mano.
 */
final class ValidadorEstadoUnidad
{
    /**
     * @return list<Alerta>
     */
    public function validar(EstadoUnidad $estado): array
    {
        return array_values(array_filter([
            $this->ubicacionSinResolver($estado),
            $this->rutaIncompleta($estado),
            $this->rutaIncompatibleConLaCarga($estado),
            $this->ubicacionFueraDeLaRuta($estado),
            $this->sinConductor($estado),
            $this->conductorDistintoAlAsignado($estado),
            $this->carretaDistintaALaAsignada($estado),
        ]));
    }

    /**
     * El texto llegó pero no se reconoció ningún punto del catálogo. La fila
     * queda válida y en la cola por resolver.
     */
    private function ubicacionSinResolver(EstadoUnidad $estado): ?Alerta
    {
        if (! $estado->tieneUbicacionSinResolver()) {
            return null;
        }

        return new Alerta(
            TipoAlerta::UbicacionSinResolver,
            "«{$estado->ubicacion_texto}» no corresponde a ningún punto conocido.",
        );
    }

    /**
     * Declara carga pero le falta un extremo de la ruta. Es el caso de las
     * celdas en «0» o sin llenar.
     */
    private function rutaIncompleta(EstadoUnidad $estado): ?Alerta
    {
        if ($estado->tipo_carga === null) {
            return null;
        }

        if ($estado->origen_id !== null && $estado->destino_id !== null) {
            return null;
        }

        $falta = $estado->origen_id === null ? 'el origen' : 'el destino';

        return new Alerta(
            TipoAlerta::RutaIncompleta,
            "La carga es {$estado->tipo_carga->label()} pero falta {$falta}.",
        );
    }

    /**
     * La combinación de carga y ruta no existe en el circuito. Atrapa los dos
     * errores más frecuentes del reporte: anotar la carga futura en vez de
     * «vacío» cuando la unidad sube a mina, y arrastrar la ruta del viaje
     * anterior junto con la carga nueva.
     */
    private function rutaIncompatibleConLaCarga(EstadoUnidad $estado): ?Alerta
    {
        if ($estado->tipo_carga === null || $estado->origen === null || $estado->destino === null) {
            return null;
        }

        if ($estado->tipo_carga->permiteRuta($estado->origen->codigo, $estado->destino->codigo)) {
            return null;
        }

        return new Alerta(
            TipoAlerta::RutaIncompatibleConCarga,
            "{$estado->tipo_carga->label()} no circula de {$estado->origen->nombre} a {$estado->destino->nombre}.",
        );
    }

    /**
     * La unidad está en un punto que no pertenece al tramo que declara. Es lo
     * que delata a la que dice llevar concentrado hacia Pisco estando en
     * Arequipa, que es destino válido pero queda fuera del corredor.
     */
    private function ubicacionFueraDeLaRuta(EstadoUnidad $estado): ?Alerta
    {
        if ($estado->ubicacion === null || $estado->origen === null || $estado->destino === null) {
            return null;
        }

        // Si algún extremo queda fuera del corredor no hay tramo contra el cual
        // comparar, y afirmar que está fuera sería inventar.
        if (! $estado->origen->estaEnCorredor() || ! $estado->destino->estaEnCorredor()) {
            return null;
        }

        if ($estado->ubicacion->estaEntre($estado->origen, $estado->destino)) {
            return null;
        }

        return new Alerta(
            TipoAlerta::UbicacionFueraDeRuta,
            "{$estado->ubicacion->nombre} no está entre {$estado->origen->nombre} y {$estado->destino->nombre}.",
        );
    }

    /**
     * Sin conductor la unidad no puede entrar a la programación de subida.
     */
    private function sinConductor(EstadoUnidad $estado): ?Alerta
    {
        return $estado->conductor_id === null
            ? new Alerta(TipoAlerta::SinConductor)
            : null;
    }

    private function conductorDistintoAlAsignado(EstadoUnidad $estado): ?Alerta
    {
        $asignacion = $this->asignacionVigente($estado);

        if ($asignacion === null || $estado->conductor_id === null) {
            return null;
        }

        if ($asignacion->conductor_id === $estado->conductor_id) {
            return null;
        }

        return new Alerta(
            TipoAlerta::ConductorDistintoAlAsignado,
            "La asignación vigente es de {$asignacion->conductor->nombres} {$asignacion->conductor->apellidos}.",
        );
    }

    private function carretaDistintaALaAsignada(EstadoUnidad $estado): ?Alerta
    {
        $asignacion = $this->asignacionVigente($estado);

        if ($asignacion === null || $estado->carreta_id === null || $asignacion->carreta_id === null) {
            return null;
        }

        if ($asignacion->carreta_id === $estado->carreta_id) {
            return null;
        }

        return new Alerta(
            TipoAlerta::CarretaDistintaALaAsignada,
            "La asignación vigente lleva la carreta {$asignacion->carreta->placa}.",
        );
    }

    /**
     * Asignación en curso del tracto. Se lee de la relación ya definida en el
     * vehículo para que un listado pueda precargarla y no caer en N+1.
     */
    private function asignacionVigente(EstadoUnidad $estado): ?Asignacion
    {
        return $estado->tracto->asignacionVigenteComoTracto;
    }
}
