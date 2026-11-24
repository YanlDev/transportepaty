<?php

namespace App\Services;

use App\Enums\EstadoValeCompra;
use App\Enums\TipoAlerta;
use App\Models\User;
use App\Models\ValeGasto;

/**
 * Los avisos de un vale de gasto: a gerencia cuando hay uno por aprobar, y a
 * quien lo pidió cuando se resuelve.
 */
class AlertasDeGastos
{
    public function __construct(private readonly Alertas $alertas) {}

    public function porAprobar(ValeGasto $vale, ?User $autor): void
    {
        $vale->loadMissing(['categoria', 'vehiculo:id,placa', 'solicitante:id,name']);

        $this->alertas->avisar(
            TipoAlerta::ValeGastoPorAprobar,
            "Vale de gasto {$vale->numero()} por aprobar: {$this->monto($vale)}",
            implode("\n", array_filter([
                "Área: {$vale->area->label()}".($vale->solicitante === null ? '' : " ({$vale->solicitante->name})"),
                "Categoría: {$vale->categoria->nombre}",
                "Qué: {$vale->concepto}",
                "Para qué: {$vale->motivo}",
                "Se paga a: {$vale->beneficiario}",
                $vale->vehiculo === null ? null : "Unidad: {$vale->vehiculo->placa}",
                'Abre el enlace para aprobarlo o rechazarlo.',
            ])),
            route('gastos.show', $vale),
            $autor,
        );
    }

    public function resuelto(ValeGasto $vale, ?User $autor): void
    {
        if ($vale->solicitado_por === null) {
            return;
        }

        $aprobado = $vale->estado === EstadoValeCompra::Aprobado;

        $this->alertas->avisar(
            TipoAlerta::ValeGastoResuelto,
            $aprobado
                ? "Vale de gasto {$vale->numero()} aprobado: {$this->monto($vale)}"
                : "Vale de gasto {$vale->numero()} rechazado",
            $aprobado
                ? "Ya se puede hacer el gasto: {$vale->concepto}."
                : 'Motivo: '.($vale->motivo_rechazo ?? '—'),
            route('gastos.show', $vale),
            $autor,
            [$vale->solicitado_por],
        );
    }

    private function monto(ValeGasto $vale): string
    {
        return 'S/ '.number_format((float) $vale->monto, 2);
    }
}
