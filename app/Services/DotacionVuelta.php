<?php

namespace App\Services;

use App\Enums\SentidoTramo;
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
     *     total_registrado: float,
     *     tramos: list<array{sentido: string, previsto: float|null, registrado: float, falta: float|null, puntos: list<array{punto: string, previsto: float|null, lleno: bool}>}>
     * }
     */
    public function de(Vuelta $vuelta, Collection $registrados): array
    {
        $tarjetas = [];
        $lineas = [];
        $porTramo = [SentidoTramo::Ida->value => [], SentidoTramo::Regreso->value => []];

        foreach ($vuelta->paradas as $parada) {
            $regla = $this->reglaDe($parada);

            if ($regla === null) {
                continue;
            }

            $tarjetas[] = ['id' => $regla->id, 'nombre' => $regla->nombre, 'color' => $regla->color, 'parada_id' => $parada->id];
            array_push($lineas, ...$regla->lineas->all());
            array_push($porTramo[$parada->sentido->value], ...$regla->lineas->all());
        }

        $combustible = $this->combustible($lineas, $registrados);

        return [
            'tarjetas' => $tarjetas,
            ...$combustible,
            'tramos' => $this->porTramo($porTramo, $combustible['combustible']),
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
     * El combustible de la ida y de la vuelta por separado, para dibujar una
     * barra por tramo. Lo autorizado se registra por punto y no por tramo,
     * así que se reparte en orden: primero cubre lo previsto de la ida en
     * ese punto y lo que sobra va a la vuelta.
     *
     * @param  array<string, list<LineaReglaAbastecimiento>>  $porTramo
     * @param  list<array{punto_id: int|null, punto: string, previsto: float|null, lleno: bool, registrado: float, falta: float|null}>  $porPunto
     * @return list<array{sentido: string, previsto: float|null, registrado: float, falta: float|null, puntos: list<array{punto: string, previsto: float|null, lleno: bool}>}>
     */
    private function porTramo(array $porTramo, array $porPunto): array
    {
        $disponible = [];

        foreach ($porPunto as $fila) {
            $disponible[$fila['punto_id'] ?? 0] = $fila['registrado'];
        }

        $tramos = [];

        foreach ($porTramo as $sentido => $lineas) {
            $puntos = [];

            foreach ($lineas as $linea) {
                if ($linea->tipo !== TipoLineaAbastecimiento::Combustible) {
                    continue;
                }

                $clave = $linea->punto_abastecimiento_id ?? 0;
                $puntos[$clave] ??= ['punto' => $linea->punto->nombre ?? 'Total del tramo', 'previsto' => 0.0, 'lleno' => false];

                if ($linea->tanque_lleno) {
                    $lleno = $this->galonesTanqueLleno();
                    $puntos[$clave]['lleno'] = true;
                    $puntos[$clave]['previsto'] = $lleno === null || $puntos[$clave]['previsto'] === null
                        ? null
                        : $puntos[$clave]['previsto'] + $lleno;
                } elseif ($puntos[$clave]['previsto'] !== null) {
                    $puntos[$clave]['previsto'] += (float) $linea->galones;
                }
            }

            if ($puntos === []) {
                continue;
            }

            $registrado = 0.0;

            foreach ($puntos as $clave => $punto) {
                $toma = $punto['previsto'] === null
                    ? ($disponible[$clave] ?? 0.0)
                    : min($disponible[$clave] ?? 0.0, $punto['previsto']);
                $registrado += $toma;
                $disponible[$clave] = ($disponible[$clave] ?? 0.0) - $toma;
            }

            $previstos = array_column($puntos, 'previsto');
            $previsto = in_array(null, $previstos, true) ? null : (float) array_sum($previstos);

            $tramos[] = [
                'sentido' => $sentido,
                'previsto' => $previsto,
                'registrado' => $registrado,
                'falta' => $previsto === null ? null : max(0.0, $previsto - $registrado),
                'puntos' => array_values($puntos),
            ];
        }

        // Lo autorizado en un punto que la pizarra no nombra se suma al
        // último tramo, para que no quede fuera de la barra.
        $sobrante = array_sum($disponible);

        if ($sobrante > 0 && $tramos !== []) {
            $ultimo = array_key_last($tramos);
            $tramos[$ultimo]['registrado'] += $sobrante;
            $tramos[$ultimo]['falta'] = $tramos[$ultimo]['previsto'] === null
                ? null
                : max(0.0, $tramos[$ultimo]['previsto'] - $tramos[$ultimo]['registrado']);
        }

        return $tramos;
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
