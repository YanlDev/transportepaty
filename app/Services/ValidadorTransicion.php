<?php

namespace App\Services;

use App\Enums\TipoAlerta;
use App\Models\EstadoUnidad;

/**
 * Compara el estado de una unidad contra el suyo anterior. Es la capa que
 * detecta lo que ninguna fila delata por separado: dos filas que por sí solas
 * se ven bien pero que juntas describen algo que no pudo pasar.
 */
final class ValidadorTransicion
{
    /**
     * @return list<Alerta>
     */
    public function validar(EstadoUnidad $estado, ?EstadoUnidad $anterior): array
    {
        if ($anterior === null) {
            return [];
        }

        $alerta = $this->saltoDeFase($estado, $anterior);

        return $alerta === null ? [] : [$alerta];
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
}
