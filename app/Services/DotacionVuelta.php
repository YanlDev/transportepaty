<?php

namespace App\Services;

use App\Enums\SentidoTramo;
use App\Enums\ZonaRuta;
use App\Models\Abastecimiento;
use App\Models\DotacionTramo;
use App\Models\ParadaVuelta;
use App\Models\ReglaAbastecimiento;
use App\Models\User;
use App\Models\Viaje;
use App\Models\Viatico;
use App\Models\Vuelta;
use Illuminate\Database\Eloquent\Collection;

/**
 * Lo autorizado para cada tramo de un ticket contra lo que ya se le dio.
 *
 * Lo autorizado es la tarjeta de la pizarra que alguien confirmó para ese
 * tramo, copiada como estaba (`DotacionTramo`): si la pizarra cambia después,
 * los tickets en ruta no se mueven. Mientras no se confirme, el sistema solo
 * sugiere la tarjeta que calza con las paradas; no cuenta como autorizada.
 *
 * El combustible cuenta en el tramo que se eligió al registrarlo, nunca se
 * reparte solo.
 *
 * @phpstan-type Tramo array{sentido: string, confirmada: bool, tarjeta: string|null, color: string|null, confirmada_por: string|null, confirmada_at: string|null, previsto: float|null, registrado: float, despachado: float, falta: float|null, puntos: list<array{punto: string, previsto: float|null}>, sugerida: array{id: int, nombre: string}|null}
 * @phpstan-type FilaCombustible array{punto_id: int|null, punto: string, previsto: float|null, registrado: float, despachado: float, falta: float|null}
 */
class DotacionVuelta
{
    /** @var Collection<int, ReglaAbastecimiento>|null */
    private ?Collection $reglas = null;

    /**
     * El ticket tiene que venir con `paradas.viajes`; las tarjetas
     * confirmadas se cargan si faltan.
     *
     * @param  Collection<int, Abastecimiento>  $registrados  los del ticket, anulados incluidos
     * @return array{
     *     tarjetas: list<array{sentido: string, id: int|null, nombre: string, color: string|null}>,
     *     combustible: list<FilaCombustible>,
     *     total_previsto: float|null,
     *     total_registrado: float,
     *     total_despachado: float,
     *     tramos: list<Tramo>,
     *     viaticos: list<array{linea_id: int|null, concepto: string, tipo: string, monto: float}>
     * }
     */
    public function de(Vuelta $vuelta, Collection $registrados): array
    {
        $vuelta->loadMissing('dotaciones.usuario:id,name');
        $vigentes = $registrados->reject(fn (Abastecimiento $abastecimiento): bool => $abastecimiento->estaAnulado())->values();
        $tramos = [];
        $lineasDeCombustible = [];
        $viaticos = [];
        $tarjetas = [];

        foreach (SentidoTramo::cases() as $sentido) {
            $dotacion = $vuelta->dotacionDe($sentido);
            $delTramo = $vigentes->where('sentido', $sentido)->values();

            if ($sentido === SentidoTramo::Regreso && $dotacion === null && $delTramo->isEmpty() && $vuelta->paradasDe($sentido)->isEmpty()) {
                continue;
            }

            if ($dotacion !== null) {
                $tarjetas[] = ['sentido' => $sentido->value, 'id' => $dotacion->regla_abastecimiento_id, 'nombre' => $dotacion->nombre, 'color' => $dotacion->color];
                array_push($lineasDeCombustible, ...$dotacion->lineasDeCombustible());

                foreach ($dotacion->lineasDeViaticos() as $linea) {
                    $viaticos[] = ['linea_id' => $linea['linea_id'], 'concepto' => $linea['concepto'], 'tipo' => $linea['tipo'], 'monto' => (float) $linea['monto']];
                }
            }

            $tramos[] = $this->tramo($vuelta, $sentido, $dotacion, $delTramo);
        }

        $combustible = $this->combustible($lineasDeCombustible, $vigentes);
        $previstos = array_column($combustible, 'previsto');

        return [
            'tarjetas' => $tarjetas,
            'combustible' => $combustible,
            'total_previsto' => in_array(null, $previstos, true) ? null : (float) array_sum($previstos),
            'total_registrado' => (float) $vigentes->sum(fn (Abastecimiento $abastecimiento): float => (float) $abastecimiento->galones),
            'total_despachado' => (float) $vigentes->sum(fn (Abastecimiento $abastecimiento): float => (float) $abastecimiento->galones_despachados),
            'tramos' => $tramos,
            'viaticos' => $viaticos,
        ];
    }

    /**
     * Confirma la tarjeta de un tramo: la copia al ticket como está ahora.
     * Confirmar otra reemplaza la anterior (queda en el historial).
     */
    public function confirmar(Vuelta $vuelta, SentidoTramo $sentido, ReglaAbastecimiento $regla, User $usuario): DotacionTramo
    {
        $regla->loadMissing('lineas.punto');

        return DotacionTramo::query()->updateOrCreate(
            ['vuelta_id' => $vuelta->id, 'sentido' => $sentido],
            [
                'regla_abastecimiento_id' => $regla->id,
                'nombre' => $regla->nombre,
                'color' => $regla->color,
                'lineas' => DotacionTramo::lineasDe($regla),
                'user_id' => $usuario->id,
            ],
        );
    }

    /**
     * La tarjeta que el sistema propone para un tramo: la más específica que
     * calza con alguna de sus paradas. Es solo una sugerencia.
     */
    public function sugerida(Vuelta $vuelta, SentidoTramo $sentido): ?ReglaAbastecimiento
    {
        $paradas = $vuelta->paradasDe($sentido);
        $inicio = $paradas->first()?->zona_origen;

        foreach ($paradas as $parada) {
            if (($regla = $this->reglaDe($parada, $inicio)) !== null) {
                return $regla;
            }
        }

        return null;
    }

    /**
     * Los viáticos de las tarjetas confirmadas contra lo depositado: cada
     * línea con su depósito (o pendiente) y, aparte, los imprevistos. Si la
     * misma línea toca dos veces, se espera dos depósitos. Los anulados no
     * cuentan.
     *
     * @param  list<array{linea_id: int|null, concepto: string, tipo: string, monto: float}>  $dePizarra
     * @param  Collection<int, Viatico>  $depositados  vigentes, del más viejo al más nuevo
     * @return list<array{linea_id: int|null, concepto: string, previsto: float|null, deposito: Viatico|null}>
     */
    public function viaticosContraDepositos(array $dePizarra, Collection $depositados): array
    {
        $libres = $depositados->all();
        $filas = [];

        foreach ($dePizarra as $linea) {
            $clave = $linea['linea_id'] === null
                ? null
                : array_find_key($libres, fn (Viatico $viatico): bool => $viatico->linea_regla_abastecimiento_id === $linea['linea_id']);
            $deposito = null;

            if ($clave !== null) {
                $deposito = $libres[$clave];
                unset($libres[$clave]);
            }

            $filas[] = ['linea_id' => $linea['linea_id'], 'concepto' => $linea['concepto'], 'previsto' => $linea['monto'], 'deposito' => $deposito];
        }

        // Lo que no calzó con la pizarra: los imprevistos y los de una línea
        // que la tarjeta ya no tiene.
        foreach ($libres as $viatico) {
            $filas[] = [
                'linea_id' => $viatico->linea_regla_abastecimiento_id,
                'concepto' => $viatico->concepto,
                'previsto' => $viatico->monto_previsto === null ? null : (float) $viatico->monto_previsto,
                'deposito' => $viatico,
            ];
        }

        return $filas;
    }

    /**
     * La tarjeta más específica que calza con la parada, o ninguna.
     */
    public function reglaDe(ParadaVuelta $parada, ?ZonaRuta $inicioDelTramo = null): ?ReglaAbastecimiento
    {
        $texto = implode(' ', array_filter([
            $parada->lugar_origen,
            $parada->lugar_destino,
            ...$parada->viajes->flatMap(fn (Viaje $viaje): array => [$viaje->origen, $viaje->destino])->all(),
        ]));
        $toneladas = $this->toneladas($parada);

        return $this->reglas()
            ->filter(fn (ReglaAbastecimiento $regla): bool => $regla->aplicaA($parada, $texto, $toneladas, $inicioDelTramo))
            ->sortByDesc(fn (ReglaAbastecimiento $regla): int => $regla->especificidad())
            ->first();
    }

    /**
     * @param  Collection<int, Abastecimiento>  $delTramo  vigentes
     * @return Tramo
     */
    private function tramo(Vuelta $vuelta, SentidoTramo $sentido, ?DotacionTramo $dotacion, Collection $delTramo): array
    {
        $registrado = (float) $delTramo->sum(fn (Abastecimiento $abastecimiento): float => (float) $abastecimiento->galones);
        $previsto = $dotacion?->tope();
        $sugerida = $dotacion === null ? $this->sugerida($vuelta, $sentido) : null;

        $puntos = [];

        foreach ($dotacion?->lineasDeCombustible() ?? [] as $linea) {
            $clave = $linea['punto_id'] ?? 'linea:'.($linea['punto'] ?? '');
            $puntos[$clave] ??= ['punto' => $linea['punto'] ?? 'Total del tramo', 'previsto' => 0.0];
            $puntos[$clave]['previsto'] = $puntos[$clave]['previsto'] === null || $linea['galones'] === null
                ? null
                : $puntos[$clave]['previsto'] + (float) $linea['galones'];
        }

        return [
            'sentido' => $sentido->value,
            'confirmada' => $dotacion !== null,
            'tarjeta' => $dotacion?->nombre,
            'color' => $dotacion?->color,
            'confirmada_por' => $dotacion?->usuario?->name,
            'confirmada_at' => $dotacion?->updated_at->format('Y-m-d H:i'),
            'previsto' => $previsto,
            'registrado' => $registrado,
            'despachado' => (float) $delTramo->sum(fn (Abastecimiento $abastecimiento): float => (float) $abastecimiento->galones_despachados),
            'falta' => $previsto === null ? null : max(0.0, $previsto - $registrado),
            'puntos' => array_values($puntos),
            'sugerida' => $sugerida === null ? null : ['id' => $sugerida->id, 'nombre' => $sugerida->nombre],
        ];
    }

    /**
     * Galones previstos por punto contra los autorizados y despachados. Lo
     * autorizado en un punto que la tarjeta no nombra también se muestra,
     * con lo previsto vacío.
     *
     * @param  list<array{punto_id: int|null, punto: string|null, galones: float|null}>  $lineas
     * @param  Collection<int, Abastecimiento>  $vigentes
     * @return list<FilaCombustible>
     */
    private function combustible(array $lineas, Collection $vigentes): array
    {
        $porPunto = [];

        foreach ($lineas as $linea) {
            $clave = $linea['punto_id'] ?? 'linea:'.($linea['punto'] ?? '');
            $porPunto[$clave] ??= ['punto_id' => $linea['punto_id'], 'punto' => $linea['punto'] ?? 'Total del tramo', 'previsto' => 0.0];
            $porPunto[$clave]['previsto'] = $porPunto[$clave]['previsto'] === null || $linea['galones'] === null
                ? null
                : $porPunto[$clave]['previsto'] + (float) $linea['galones'];
        }

        foreach ($vigentes as $abastecimiento) {
            $porPunto[$this->claveDePunto($abastecimiento)] ??= [
                'punto_id' => $abastecimiento->punto_abastecimiento_id,
                'punto' => $abastecimiento->nombrePunto() ?? 'Sin punto',
                'previsto' => null,
            ];
        }

        $filas = [];

        foreach ($porPunto as $clave => $fila) {
            $delPunto = $vigentes->filter(fn (Abastecimiento $abastecimiento): bool => $this->claveDePunto($abastecimiento) === $clave);
            $registrado = (float) $delPunto->sum(fn (Abastecimiento $abastecimiento): float => (float) $abastecimiento->galones);

            $filas[] = [
                ...$fila,
                'registrado' => $registrado,
                'despachado' => (float) $delPunto->sum(fn (Abastecimiento $abastecimiento): float => (float) $abastecimiento->galones_despachados),
                'falta' => $fila['previsto'] === null ? null : max(0.0, $fila['previsto'] - $registrado),
            ];
        }

        return $filas;
    }

    /**
     * Dónde se junta lo autorizado: por punto de la lista; lo cargado en un
     * grifo fuera de la lista, por el nombre que se escribió; y sin nada,
     * con el total del tramo.
     */
    private function claveDePunto(Abastecimiento $abastecimiento): int|string
    {
        if ($abastecimiento->punto_abastecimiento_id !== null) {
            return $abastecimiento->punto_abastecimiento_id;
        }

        return filled($abastecimiento->punto_libre)
            ? 'libre:'.mb_strtolower(trim($abastecimiento->punto_libre))
            : 0;
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
}
