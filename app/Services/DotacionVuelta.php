<?php

namespace App\Services;

use App\Enums\TipoLineaAbastecimiento;
use App\Models\Abastecimiento;
use App\Models\Ajuste;
use App\Models\LineaReglaAbastecimiento;
use App\Models\ParadaVuelta;
use App\Models\ReglaAbastecimiento;
use App\Models\Viaje;
use App\Models\Vuelta;
use Illuminate\Database\Eloquent\Collection;

/**
 * Lo que le toca a una vuelta según la pizarra, contra lo que ya se le
 * autorizó: a cada parada se le busca su tarjeta, y de las tarjetas salen
 * los galones por punto y los viáticos.
 *
 * Es una lectura: no guarda nada. Si mañana cambia la pizarra, cambia lo
 * previsto de las vueltas que siguen abiertas.
 */
class DotacionVuelta
{
    /** @var Collection<int, ReglaAbastecimiento>|null */
    private ?Collection $reglas = null;

    private ?float $galonesTanqueLleno = null;

    private bool $tanqueLeido = false;

    /**
     * La vuelta tiene que venir con `paradas.viajes` cargadas.
     *
     * @param  Collection<int, Abastecimiento>  $registrados  los abastecimientos de la vuelta
     * @return array{
     *     tarjetas: list<array{id: int, nombre: string, color: string|null, parada_id: int}>,
     *     combustible: list<array{punto_id: int|null, punto: string, previsto: float|null, lleno: bool, registrado: float, falta: float|null}>,
     *     viaticos: list<array{concepto: string, tipo: string, monto: float}>,
     *     total_previsto: float|null,
     *     total_registrado: float
     * }
     */
    public function de(Vuelta $vuelta, Collection $registrados): array
    {
        $tarjetas = [];
        $lineas = [];

        foreach ($vuelta->paradas as $parada) {
            $regla = $this->reglaDe($parada);

            if ($regla === null) {
                continue;
            }

            $tarjetas[] = ['id' => $regla->id, 'nombre' => $regla->nombre, 'color' => $regla->color, 'parada_id' => $parada->id];
            array_push($lineas, ...$regla->lineas->all());
        }

        return [
            'tarjetas' => $tarjetas,
            ...$this->combustible($lineas, $registrados),
            'viaticos' => array_values(collect($lineas)
                ->reject(fn (LineaReglaAbastecimiento $linea): bool => $linea->tipo === TipoLineaAbastecimiento::Combustible)
                ->map(fn (LineaReglaAbastecimiento $linea): array => [
                    'concepto' => $linea->concepto,
                    'tipo' => $linea->tipo->value,
                    'monto' => (float) $linea->monto,
                ])
                ->all()),
        ];
    }

    /**
     * La tarjeta más específica que calza con la parada, o ninguna.
     */
    public function reglaDe(ParadaVuelta $parada): ?ReglaAbastecimiento
    {
        $texto = implode(' ', array_filter([
            $parada->lugar_origen,
            $parada->lugar_destino,
            ...$parada->viajes->flatMap(fn (Viaje $viaje): array => [$viaje->origen, $viaje->destino])->all(),
        ]));
        $toneladas = $this->toneladas($parada);

        return $this->reglas()
            ->filter(fn (ReglaAbastecimiento $regla): bool => $regla->aplicaA($parada, $texto, $toneladas))
            ->sortByDesc(fn (ReglaAbastecimiento $regla): int => $regla->especificidad())
            ->first();
    }

    /**
     * Galones previstos por punto contra los registrados. Lo registrado en
     * un punto que la pizarra no nombra también se muestra, con lo previsto
     * vacío.
     *
     * @param  list<LineaReglaAbastecimiento>  $lineas
     * @param  Collection<int, Abastecimiento>  $registrados
     * @return array{combustible: list<array{punto_id: int|null, punto: string, previsto: float|null, lleno: bool, registrado: float, falta: float|null}>, total_previsto: float|null, total_registrado: float}
     */
    private function combustible(array $lineas, Collection $registrados): array
    {
        $porPunto = [];

        foreach ($lineas as $linea) {
            if ($linea->tipo !== TipoLineaAbastecimiento::Combustible) {
                continue;
            }

            $clave = $linea->punto_abastecimiento_id ?? 0;
            $porPunto[$clave] ??= [
                'punto_id' => $linea->punto_abastecimiento_id,
                'punto' => $linea->punto->nombre ?? 'Total del tramo',
                'previsto' => 0.0,
                'lleno' => false,
            ];

            if ($linea->tanque_lleno) {
                $porPunto[$clave]['lleno'] = true;
                $lleno = $this->galonesTanqueLleno();
                $porPunto[$clave]['previsto'] = $lleno === null || $porPunto[$clave]['previsto'] === null
                    ? null
                    : $porPunto[$clave]['previsto'] + $lleno;

                continue;
            }

            if ($porPunto[$clave]['previsto'] !== null) {
                $porPunto[$clave]['previsto'] += (float) $linea->galones;
            }
        }

        foreach ($registrados as $abastecimiento) {
            $clave = $abastecimiento->punto_abastecimiento_id ?? 0;
            $porPunto[$clave] ??= [
                'punto_id' => $abastecimiento->punto_abastecimiento_id,
                'punto' => $abastecimiento->punto->nombre ?? 'Sin punto',
                'previsto' => null,
                'lleno' => false,
            ];
        }

        $filas = [];

        foreach ($porPunto as $clave => $fila) {
            $registrado = (float) $registrados
                ->filter(fn (Abastecimiento $abastecimiento): bool => ($abastecimiento->punto_abastecimiento_id ?? 0) === $clave)
                ->sum(fn (Abastecimiento $abastecimiento): float => (float) $abastecimiento->galones);

            $filas[] = [
                ...$fila,
                'registrado' => $registrado,
                'falta' => $fila['previsto'] === null ? null : max(0.0, $fila['previsto'] - $registrado),
            ];
        }

        $previstos = array_column($filas, 'previsto');

        return [
            'combustible' => $filas,
            // Un «tanque lleno» sin galones configurados deja el total sin
            // calcular: sumar sin él daría un número engañoso.
            'total_previsto' => in_array(null, $previstos, true) ? null : (float) array_sum($previstos),
            'total_registrado' => (float) array_sum(array_column($filas, 'registrado')),
        ];
    }

    /**
     * El peso de las GR de la parada, en toneladas. Las GR vienen unas en
     * toneladas y otras en kilos. Null si todavía no tiene GR.
     */
    private function toneladas(ParadaVuelta $parada): ?float
    {
        if ($parada->viajes->isEmpty()) {
            return null;
        }

        return (float) $parada->viajes->sum(fn (Viaje $viaje): float => strtoupper($viaje->unidad_peso) === 'KGM'
            ? (float) $viaje->peso / 1000
            : (float) $viaje->peso);
    }

    /**
     * @return Collection<int, ReglaAbastecimiento>
     */
    private function reglas(): Collection
    {
        return $this->reglas ??= ReglaAbastecimiento::query()
            ->where('activa', true)
            ->enOrden()
            ->with('lineas.punto')
            ->get();
    }

    private function galonesTanqueLleno(): ?float
    {
        if (! $this->tanqueLeido) {
            $valor = Ajuste::valor(Ajuste::GALONES_TANQUE_LLENO);
            $this->galonesTanqueLleno = $valor === null ? null : (float) $valor;
            $this->tanqueLeido = true;
        }

        return $this->galonesTanqueLleno;
    }
}
