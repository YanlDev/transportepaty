<?php

namespace App\Services;

use App\Enums\SentidoTramo;
use App\Models\Abastecimiento;
use App\Models\ParadaVuelta;
use App\Models\Ruta;
use App\Models\RutaVuelta;
use App\Models\User;
use App\Models\VarianteRuta;
use App\Models\Viaje;
use App\Models\Viatico;
use App\Models\Vuelta;
use Illuminate\Database\Eloquent\Collection;

/**
 * Lo autorizado para cada tramo de un ticket contra lo que ya se le dio.
 *
 * Lo autorizado sale de las rutas elegidas al programar (`RutaVuelta`),
 * copiadas como estaban: si el catálogo cambia después, los tickets en ruta
 * no se mueven. El tope de un tramo es la suma de sus rutas; si a una le
 * falta elegir la variante (por peso), el tope no se conoce todavía.
 *
 * El combustible cuenta en el tramo que se eligió al registrarlo, nunca se
 * reparte solo.
 *
 * @phpstan-type Tramo array{sentido: string, confirmada: bool, rutas: list<array{id: int, nombre: string, variante: string|null, color: string|null, es_complemento: bool, variante_pendiente: bool, tope: float|null, variantes: list<array{id: int, nombre: string}>, sugerida: int|null}>, tarjeta: string|null, color: string|null, previsto: float|null, registrado: float, despachado: float, falta: float|null, puntos: list<array{punto: string, previsto: float|null}>, tanque_lleno: array{puntos: list<array{punto_id: int|null, punto: string}>, usado: bool, sin_voucher: bool, galones: float}, pendiente: string|null}
 * @phpstan-type FilaCombustible array{punto_id: int|null, punto: string, previsto: float|null, registrado: float, despachado: float, falta: float|null}
 */
class DotacionVuelta
{
    /**
     * El ticket tiene que venir con `paradas.viajes`; las rutas se cargan si
     * faltan.
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
        $vuelta->loadMissing('rutas.ruta.variantes');
        $vigentes = $registrados->reject(fn (Abastecimiento $abastecimiento): bool => $abastecimiento->estaAnulado())->values();
        $tramos = [];
        $lineasDeCombustible = [];
        $viaticos = [];
        $tarjetas = [];

        foreach (SentidoTramo::cases() as $sentido) {
            $rutas = $vuelta->rutasDe($sentido);
            $delTramo = $vigentes->where('sentido', $sentido)->values();

            if ($sentido === SentidoTramo::Regreso && $rutas->isEmpty() && $delTramo->isEmpty() && $vuelta->paradasDe($sentido)->isEmpty()) {
                continue;
            }

            foreach ($rutas as $ruta) {
                $tarjetas[] = ['sentido' => $sentido->value, 'id' => $ruta->ruta_id, 'nombre' => $ruta->nombre.($ruta->variante ? " · {$ruta->variante}" : ''), 'color' => $ruta->color];
                array_push($lineasDeCombustible, ...$ruta->lineasDeCombustible());

                foreach ($ruta->lineasDeViaticos() as $linea) {
                    $viaticos[] = ['linea_id' => $linea['linea_id'], 'concepto' => $linea['concepto'], 'tipo' => $linea['tipo'], 'monto' => (float) $linea['monto']];
                }
            }

            $tramos[] = $this->tramo($vuelta, $sentido, $rutas, $delTramo);
        }

        // El tanque full va aparte: no tiene previsto ni suma al tope.
        $combustible = $this->combustible($lineasDeCombustible, $vigentes->reject(fn (Abastecimiento $abastecimiento): bool => $abastecimiento->tanque_lleno));
        $previstos = array_column($combustible, 'previsto');

        return [
            'tarjetas' => $tarjetas,
            'combustible' => $combustible,
            'total_previsto' => in_array(null, $previstos, true) ? null : (float) array_sum($previstos),
            // En el tanque full lo autorizado es lo que entró, según el voucher.
            'total_registrado' => (float) $vigentes->sum(fn (Abastecimiento $abastecimiento): float => $abastecimiento->tanque_lleno
                ? (float) $abastecimiento->galones_despachados
                : (float) $abastecimiento->galones),
            'total_despachado' => (float) $vigentes->sum(fn (Abastecimiento $abastecimiento): float => (float) $abastecimiento->galones_despachados),
            'tramos' => $tramos,
            'viaticos' => $viaticos,
        ];
    }

    /**
     * Elige la variante de una ruta del ticket (la dotación por peso) y la
     * copia como está ahora.
     */
    public function elegirVariante(RutaVuelta $rutaVuelta, VarianteRuta $variante, User $usuario): void
    {
        $ruta = Ruta::query()->with(['lineas.punto', 'variantes'])->findOrFail($variante->ruta_id);

        $rutaVuelta->update([...RutaVuelta::copiaDe($ruta, $variante), 'user_id' => $usuario->id]);
    }

    /**
     * La variante que corresponde al peso de las GR de las paradas de esa
     * ruta, o null si todavía no hay GR o la ruta no tiene variantes.
     */
    public function varianteSugerida(Vuelta $vuelta, RutaVuelta $rutaVuelta): ?VarianteRuta
    {
        $ruta = $rutaVuelta->ruta;

        if ($ruta === null || $ruta->variantes->isEmpty()) {
            return null;
        }

        $toneladas = $this->toneladasDeLaRuta($vuelta, $rutaVuelta, $ruta);

        return $toneladas === null ? null : $ruta->varianteParaPeso($toneladas);
    }

    /**
     * Los viáticos de las rutas contra lo depositado: cada línea con su
     * depósito (o pendiente) y, aparte, los imprevistos. Si la misma línea
     * toca dos veces, se espera dos depósitos. Los anulados no cuentan.
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
                : array_find_key($libres, fn (Viatico $viatico): bool => $viatico->linea_ruta_id === $linea['linea_id']);
            $deposito = null;

            if ($clave !== null) {
                $deposito = $libres[$clave];
                unset($libres[$clave]);
            }

            $filas[] = ['linea_id' => $linea['linea_id'], 'concepto' => $linea['concepto'], 'previsto' => $linea['monto'], 'deposito' => $deposito];
        }

        // Lo que no calzó con las rutas: los imprevistos y los de una línea
        // que la ruta ya no tiene.
        foreach ($libres as $viatico) {
            $filas[] = [
                'linea_id' => $viatico->linea_ruta_id,
                'concepto' => $viatico->concepto,
                'previsto' => $viatico->monto_previsto === null ? null : (float) $viatico->monto_previsto,
                'deposito' => $viatico,
            ];
        }

        return $filas;
    }

    /**
     * @param  Collection<int, RutaVuelta>  $rutas
     * @param  Collection<int, Abastecimiento>  $delTramo  vigentes
     * @return Tramo
     */
    private function tramo(Vuelta $vuelta, SentidoTramo $sentido, Collection $rutas, Collection $delTramo): array
    {
        // El tanque full no tiene galones fijos: cuenta contra el tope con
        // los de su voucher (tope 180, tanquea 150, quedan 30). Mientras el
        // voucher no llega suma cero y el tramo queda en espera.
        $llenos = $delTramo->filter(fn (Abastecimiento $abastecimiento): bool => $abastecimiento->tanque_lleno);

        // Redondeado: sumar decimales (118.53 + 101.47…) deja restos que
        // harían que un tramo completo diga «faltan 0 gal».
        $registrado = round((float) $delTramo->sum(fn (Abastecimiento $abastecimiento): float => $abastecimiento->galonesQueCuentan()), 2);
        $previsto = $vuelta->topeDe($sentido);
        $puntos = [];

        foreach ($rutas as $ruta) {
            foreach ($ruta->lineasDeCombustible() as $linea) {
                $clave = $linea['punto_id'] ?? 'linea:'.($linea['punto'] ?? '');
                $puntos[$clave] ??= ['punto' => $linea['punto'] ?? 'Total del tramo', 'previsto' => 0.0];
                $puntos[$clave]['previsto'] = $puntos[$clave]['previsto'] === null || $linea['galones'] === null
                    ? null
                    : $puntos[$clave]['previsto'] + (float) $linea['galones'];
            }
        }

        $principal = $rutas->first(fn (RutaVuelta $ruta): bool => ! $ruta->es_complemento) ?? $rutas->first();

        return [
            'sentido' => $sentido->value,
            // Tiene lo autorizado: rutas, variantes elegidas y galones definidos.
            'confirmada' => $previsto !== null,
            'rutas' => array_values($rutas->map(fn (RutaVuelta $ruta): array => [
                'id' => $ruta->id,
                'nombre' => $ruta->nombre,
                'variante' => $ruta->variante,
                'color' => $ruta->color,
                'es_complemento' => $ruta->es_complemento,
                'variante_pendiente' => $ruta->variante_pendiente,
                'tope' => $ruta->tope(),
                'variantes' => array_values(($ruta->ruta->variantes ?? new Collection)
                    ->map(fn (VarianteRuta $variante): array => ['id' => $variante->id, 'nombre' => $variante->nombre])
                    ->all()),
                'sugerida' => $ruta->variante_pendiente ? $this->varianteSugerida($vuelta, $ruta)?->id : null,
            ])->all()),
            'tarjeta' => $principal?->nombre,
            'color' => $principal?->color,
            'previsto' => $previsto,
            'registrado' => $registrado,
            'despachado' => (float) $delTramo->sum(fn (Abastecimiento $abastecimiento): float => (float) $abastecimiento->galones_despachados),
            'falta' => $previsto === null ? null : max(0.0, round($previsto - $registrado, 2)),
            'puntos' => array_values($puntos),
            'tanque_lleno' => [
                'puntos' => array_values(array_map(
                    fn (array $linea): array => ['punto_id' => $linea['punto_id'], 'punto' => $linea['punto'] ?? $linea['concepto']],
                    $rutas->flatMap(fn (RutaVuelta $ruta): array => $ruta->lineasDeTanqueLleno())->all(),
                )),
                'usado' => $llenos->isNotEmpty(),
                // Con un full sin voucher no se autoriza nada más al tramo.
                'sin_voucher' => $llenos->contains(fn (Abastecimiento $abastecimiento): bool => ! $abastecimiento->tieneDespacho()),
                'galones' => round((float) $llenos->sum(fn (Abastecimiento $abastecimiento): float => (float) $abastecimiento->galones_despachados), 2),
            ],
            'pendiente' => $this->pendiente($rutas),
        ];
    }

    /**
     * Por qué el tramo todavía no tiene tope, en una frase; null si lo tiene.
     *
     * @param  Collection<int, RutaVuelta>  $rutas
     */
    private function pendiente(Collection $rutas): ?string
    {
        if ($rutas->isEmpty()) {
            return 'Sin ruta: se elige al programar';
        }

        $sinVariante = $rutas->first(fn (RutaVuelta $ruta): bool => $ruta->variante_pendiente);

        if ($sinVariante !== null) {
            return "Falta elegir la variante de «{$sinVariante->nombre}» (según el peso)";
        }

        $porDefinir = $rutas->first(fn (RutaVuelta $ruta): bool => $ruta->tope() === null);

        return $porDefinir === null ? null : "«{$porDefinir->nombre}» tiene galones por definir en el catálogo de rutas";
    }

    /**
     * Galones previstos por punto contra los autorizados y despachados. Lo
     * autorizado en un punto que la ruta no nombra también se muestra, con
     * lo previsto vacío.
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
            $registrado = round((float) $delPunto->sum(fn (Abastecimiento $abastecimiento): float => $abastecimiento->galonesQueCuentan()), 2);

            $filas[] = [
                ...$fila,
                'registrado' => $registrado,
                'despachado' => (float) $delPunto->sum(fn (Abastecimiento $abastecimiento): float => (float) $abastecimiento->galones_despachados),
                'falta' => $fila['previsto'] === null ? null : max(0.0, round($fila['previsto'] - $registrado, 2)),
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
            : 'linea:';
    }

    /**
     * El peso de las GR de las paradas que hace esa ruta (mismo trayecto y
     * carga que su recorrido), en toneladas. Las GR vienen unas en
     * toneladas y otras en kilos. Null si todavía no hay GR.
     */
    private function toneladasDeLaRuta(Vuelta $vuelta, RutaVuelta $rutaVuelta, Ruta $ruta): ?float
    {
        $guias = $vuelta->paradasDe($rutaVuelta->sentido)
            ->filter(fn (ParadaVuelta $parada): bool => collect($ruta->paradas)->contains(
                fn (array $plantilla): bool => $plantilla['zona_origen'] === $parada->zona_origen?->value
                    && ($plantilla['zona_destino'] ?? null) === $parada->zona_destino?->value
                    && $plantilla['tipo_carga'] === $parada->tipo_carga->value,
            ))
            ->flatMap(fn (ParadaVuelta $parada) => $parada->viajes);

        if ($guias->isEmpty()) {
            return null;
        }

        return (float) $guias->sum(fn (Viaje $viaje): float => strtoupper($viaje->unidad_peso) === 'KGM'
            ? (float) $viaje->peso / 1000
            : (float) $viaje->peso);
    }
}
