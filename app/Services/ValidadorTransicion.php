<?php

namespace App\Services;

use App\Enums\TipoAlerta;
use App\Enums\TipoCarga;
use App\Models\EstadoUnidad;

/**
 * Compara el estado de una unidad contra el suyo anterior. Es la capa que
 * detecta lo que ninguna fila delata por separado: dos filas que por sí solas
 * se ven bien pero que juntas describen algo que no pudo pasar.
 *
 * Todas las comprobaciones se juzgan con los días realmente transcurridos y no
 * con «ayer»: si hubo fin de semana o un día sin reporte, un salto que en un día
 * sería imposible en tres es normal.
 */
final class ValidadorTransicion
{
    /**
     * Techo de avance en línea recta por día, en kilómetros. Está calibrado
     * contra los dos tramos largos del circuito, medidos sobre el catálogo:
     *
     * - San Rafael a Pisco son 636 km en línea recta y toma de dos a tres días,
     *   o sea 318 km por día en el caso más rápido que se da de verdad.
     * - Juliaca a Lima son 839 km, que en dos días dan 420 por día.
     *
     * Quinientos deja pasar ambos con margen y marca los mismos trayectos hechos
     * en un día, que es lo que interesa señalar. Va sobre distancia geodésica,
     * siempre menor que la de carretera, así que lo que cruza este techo es
     * imposible de verdad y no solamente rápido.
     *
     * En la fase de estimación de llegadas este número deja de ser una constante
     * y pasa a calibrarse con el histórico de recorridos.
     */
    public const KILOMETROS_POR_DIA_IMPOSIBLE = 500;

    /**
     * @return list<Alerta>
     */
    public function validar(EstadoUnidad $estado, ?EstadoUnidad $anterior): array
    {
        if ($anterior === null) {
            return [];
        }

        $dias = max(1, $estado->diasDesde($anterior));

        return array_values(array_filter([
            $this->saltoDeFase($estado, $anterior),
            $this->cargaCambioFueraDePunto($estado, $anterior),
            $this->avanceImposible($estado, $anterior, $dias),
            $this->unidadDetenida($estado, $anterior, $dias),
        ]));
    }

    /**
     * La unidad apareció en una etapa a la que no se llega desde la anterior.
     * Es el caso de la que baja con concentrado y al reporte siguiente figura
     * con carga particular sin haber pasado nunca por el retorno de Pisco.
     */
    private function saltoDeFase(EstadoUnidad $estado, EstadoUnidad $anterior): ?Alerta
    {
        if ($estado->fase === null || $anterior->fase === null) {
            return null;
        }

        if ($anterior->fase->puedeTransicionarA($estado->fase)) {
            return null;
        }

        return new Alerta(
            TipoAlerta::SaltoDeFase,
            "Venía en «{$anterior->fase->label()}» y aparece en «{$estado->fase->label()}».",
        );
    }

    /**
     * La carga cambió en un punto donde no hay nada que cargar ni dónde
     * descargar. Los puntos válidos salen de las propias reglas de carga-ruta,
     * así que no pueden quedar desfasados de ellas.
     */
    private function cargaCambioFueraDePunto(EstadoUnidad $estado, EstadoUnidad $anterior): ?Alerta
    {
        if ($estado->tipo_carga === null || $anterior->tipo_carga === null) {
            return null;
        }

        if ($estado->tipo_carga === $anterior->tipo_carga || $estado->ubicacion === null) {
            return null;
        }

        if (in_array($estado->ubicacion->codigo, TipoCarga::puntosDeCambio(), true)) {
            return null;
        }

        return new Alerta(
            TipoAlerta::CargaCambioFueraDePunto,
            "Pasó de {$anterior->tipo_carga->label()} a {$estado->tipo_carga->label()} estando en {$estado->ubicacion->nombre}.",
        );
    }

    /**
     * Recorrió más de lo que se puede recorrer en el tiempo transcurrido. Como
     * la distancia se mide en línea recta, lo que pasa este filtro es siempre
     * imposible de verdad: por carretera habría sido todavía más lejos.
     */
    private function avanceImposible(EstadoUnidad $estado, EstadoUnidad $anterior, int $dias): ?Alerta
    {
        if ($estado->ubicacion === null || $anterior->ubicacion === null) {
            return null;
        }

        $distancia = $anterior->ubicacion->distanciaKmA($estado->ubicacion);

        if ($distancia === null || $distancia / $dias <= self::KILOMETROS_POR_DIA_IMPOSIBLE) {
            return null;
        }

        $kilometros = round($distancia);
        $jornada = $dias === 1 ? 'un día' : "{$dias} días";

        return new Alerta(
            TipoAlerta::AvanceImposible,
            "De {$anterior->ubicacion->nombre} a {$estado->ubicacion->nombre} hay {$kilometros} km en línea recta y pasó {$jornada}.",
        );
    }

    /**
     * Lleva días en el mismo punto sin que ese punto admita esa permanencia. En
     * Juliaca uno o dos días es lo normal, porque es la base y ahí está el
     * taller; en un punto de paso del corredor, no.
     */
    private function unidadDetenida(EstadoUnidad $estado, EstadoUnidad $anterior, int $dias): ?Alerta
    {
        if ($estado->ubicacion === null || $estado->ubicacion_id !== $anterior->ubicacion_id) {
            return null;
        }

        if ($estado->ubicacion->permanenciaEsNormal($dias)) {
            return null;
        }

        $jornada = $dias === 1 ? 'un día' : "{$dias} días";

        return new Alerta(
            TipoAlerta::UnidadDetenida,
            "Lleva {$jornada} en {$estado->ubicacion->nombre} sin moverse.",
        );
    }
}
