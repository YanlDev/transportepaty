<?php

namespace App\Services;

use App\Enums\TipoCarga;
use App\Enums\TipoLineaAbastecimiento;
use App\Enums\ZonaRuta;
use App\Models\GalonesPorTramo;
use App\Models\ParadaVuelta;
use Illuminate\Support\Collection;

/**
 * Los galones de cada tramo, de la tabla «Galones por tramo»: una ruta por
 * tramos suma los de su recorrido, y el ticket los de sus paradas reales
 * (si una GR hace que un tramo vaya cargado, se cuenta cargado). Se lee una
 * vez por pedido.
 *
 * @phpstan-import-type LineaCopiada from \App\Models\RutaVuelta
 */
class TablaDeTramos
{
    /** @var Collection<string, GalonesPorTramo>|null */
    private ?Collection $filas = null;

    public function olvidar(): void
    {
        $this->filas = null;
    }

    /**
     * Los galones de un tramo. Dentro de la misma zona (Huaral → Callao) no
     * hay tramo que contar: 0. Null si la tabla no lo tiene o le falta ese
     * número.
     */
    public function galones(ZonaRuta $origen, ZonaRuta $destino, bool $cargado): ?float
    {
        if ($origen === $destino) {
            return 0.0;
        }

        $fila = $this->filas()->get($origen->value.'>'.$destino->value);
        $galones = $cargado ? $fila?->galones_cargado : $fila?->galones_vacio;

        return $galones === null ? null : (float) $galones;
    }

    /**
     * Una línea de combustible sin grifo por cada parada del recorrido:
     * «Lima → Arequipa · cargado», con los galones de la tabla (null si
     * falta: el tramo queda por definir).
     *
     * @param  iterable<ParadaVuelta|array{zona_origen: ZonaRuta|string|null, zona_destino: ZonaRuta|string|null, tipo_carga: TipoCarga|string}>  $paradas
     * @return list<LineaCopiada>
     */
    public function lineas(iterable $paradas): array
    {
        $lineas = [];

        foreach ($paradas as $parada) {
            $origen = $this->zona($parada instanceof ParadaVuelta ? $parada->zona_origen : ($parada['zona_origen'] ?? null));
            $destino = $this->zona($parada instanceof ParadaVuelta ? $parada->zona_destino : ($parada['zona_destino'] ?? null));
            $carga = $parada instanceof ParadaVuelta ? $parada->tipo_carga : TipoCarga::tryFrom((string) ($parada['tipo_carga'] ?? ''));
            $cargado = $carga !== TipoCarga::Vacio;

            $lineas[] = $this->linea($origen, $destino, $cargado);
        }

        return $lineas;
    }

    /**
     * Los tramos de una ruta en el ticket: cada tramo de la ruta toma la
     * parada real que le corresponde (mismas zonas), cargada si una GR la
     * hizo ir cargada. Las paradas que agregan los complementos (Ilo →
     * Juliaca con harina) no son de esta ruta y no se cuentan. Un tramo sin
     * parada todavía se cuenta como se programó.
     *
     * @param  list<LineaCopiada>  $tramos  los de la ruta, con sus zonas
     * @param  iterable<ParadaVuelta>  $paradas
     * @return list<LineaCopiada>
     */
    public function lineasDelTicket(array $tramos, iterable $paradas): array
    {
        $paradas = collect($paradas);

        return array_map(function (array $tramo) use ($paradas): array {
            $origen = $this->zona($tramo['zona_origen'] ?? null);
            $destino = $this->zona($tramo['zona_destino'] ?? null);
            $suyas = $paradas->filter(fn (ParadaVuelta $parada): bool => $origen !== null
                && $parada->zona_origen === $origen
                && ($destino === null || $parada->zona_destino === $destino));

            if ($suyas->isEmpty()) {
                return $this->linea($origen, $destino, (bool) ($tramo['cargado'] ?? true));
            }

            $parada = $suyas->first(fn (ParadaVuelta $parada): bool => $parada->tipo_carga !== TipoCarga::Vacio) ?? $suyas->first();

            return $this->linea($parada->zona_origen, $parada->zona_destino, $parada->tipo_carga !== TipoCarga::Vacio);
        }, $tramos);
    }

    /**
     * @return LineaCopiada
     */
    private function linea(?ZonaRuta $origen, ?ZonaRuta $destino, bool $cargado): array
    {
        return [
            'linea_id' => null,
            'tipo' => TipoLineaAbastecimiento::Combustible->value,
            'concepto' => ($origen?->label() ?? '¿?').' → '.($destino?->label() ?? '¿?').' · '.($cargado ? 'cargado' : 'vacío'),
            'punto_id' => null,
            'punto' => null,
            'galones' => $origen === null || $destino === null ? null : $this->galones($origen, $destino, $cargado),
            'tanque_lleno' => false,
            'monto' => null,
            'tramo' => true,
            'zona_origen' => $origen?->value,
            'zona_destino' => $destino?->value,
            'cargado' => $cargado,
        ];
    }

    private function zona(ZonaRuta|string|null $zona): ?ZonaRuta
    {
        return $zona instanceof ZonaRuta ? $zona : ZonaRuta::tryFrom((string) $zona);
    }

    /**
     * @return Collection<string, GalonesPorTramo>
     */
    private function filas(): Collection
    {
        return $this->filas ??= GalonesPorTramo::query()->get()
            ->keyBy(fn (GalonesPorTramo $fila): string => $fila->zona_origen->value.'>'.$fila->zona_destino->value);
    }
}
