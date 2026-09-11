<?php

namespace App\Services;

use App\Enums\EstadoDocumento;
use App\Enums\EstadoVehiculo;
use App\Enums\TipoCarga;
use App\Enums\TipoVehiculo;
use App\Models\Conductor;
use App\Models\ConductorDocumento;
use App\Models\Novedad;
use App\Models\Vehiculo;
use App\Models\VehiculoDocumento;
use App\Models\Viaje;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Lo que dice el tablero: cuánta flota hay, cómo está el papeleo, cómo viene
 * la meta del mes y qué se movió en el rango que se está mirando.
 *
 * Vive fuera del controlador porque nada de esto es HTTP —son recuentos sobre
 * viajes, vehículos y documentos— y porque son cuatrocientas líneas de reglas
 * de negocio que se leen mejor juntas y sin una petición alrededor.
 */
class ResumenTablero
{
    /**
     * El nombre de razón social de Minsur trae variantes de espaciado en las
     * GR reales («MINSUR S.A.» y «MINSUR S. A.»), así que se identifica por
     * prefijo en vez de comparar el texto exacto.
     */
    private const CLIENTE_MINSUR_PREFIJO = 'MINSUR';

    /**
     * El indicador principal del área: viajes reales de concentrado que debe
     * cerrar el mes en curso, sin importar qué rango esté mirando el resto
     * del tablero.
     */
    private const META_MENSUAL_CONCENTRADO = 120;

    /**
     * Cuántos clientes se listan uno por uno en el gráfico de viajes; el
     * resto se agrupa en «otros» para que la barra más larga siga siendo
     * legible en vez de aplastarse contra una lista interminable.
     */
    private const CLIENTES_EN_GRAFICO = 10;

    /**
     * Todo el tablero para un rango de fechas ya resuelto.
     *
     * @return array<string, mixed>
     */
    public function paraElRango(string $desde, string $hasta): array
    {
        $viajes = $this->viajesDelRango($desde, $hasta);
        $documentos = $this->documentosPorEstado();

        return [
            'resumen' => [
                'tractos' => Vehiculo::where('tipo', TipoVehiculo::Tracto)->count(),
                'carretas' => Vehiculo::where('tipo', TipoVehiculo::Carreta)->count(),
                'operativos' => Vehiculo::where('estado', EstadoVehiculo::Activo)->count(),
                'conductores' => Conductor::where('activo', true)->count(),
                'conductoresRegistrados' => Conductor::count(),
                'novedadesActivas' => Novedad::vigentes()->count(),
                'documentosVencidos' => $documentos['vencidos'],
            ],
            'metaConcentrado' => $this->metaConcentrado(),
            'documentos' => $documentos,
            'unidades' => $this->unidades(),
            'viajesPorCliente' => $this->viajesPorCliente($viajes),
            'cargaMinsur' => $this->cargaMinsur($viajes),
            'viajesPorTipoCliente' => $this->viajesPorTipoCliente($viajes),
            'topCargas' => $this->topCargas($viajes),
            'ultimosViajes' => $this->ultimosViajes(),
        ];
    }

    /**
     * Los viajes del rango, cargados una sola vez: todos los recuentos de
     * abajo salen de esta misma colección en vez de repetir la consulta por
     * cada gráfico.
     *
     * @return Collection<int, Viaje>
     */
    private function viajesDelRango(string $desde, string $hasta): Collection
    {
        return Viaje::query()
            // El alias del padrón es lo que se muestra; sin precargarlo,
            // `nombreCliente()` dispara una consulta por viaje.
            ->with('clienteDelPadron:id,alias')
            ->whereBetween('fecha_traslado', [$desde, $hasta])
            ->get(['cliente', 'cliente_id', 'fecha_traslado', 'tracto_id', 'placa_tracto', 'carreta_id', 'placa_carreta', 'conductor_id', 'conductor_dni', 'tipo_carga']);
    }

    private function esDeMinsur(Viaje $viaje): bool
    {
        return str_starts_with($viaje->cliente, self::CLIENTE_MINSUR_PREFIJO);
    }

    /**
     * Avance del mes en curso contra la meta mensual de concentrado: cuántos
     * viajes reales van, cuántos faltan, y a qué ritmo diario hay que cerrar
     * los días que quedan para llegar. La proyección asume que el ritmo de lo
     * que va del mes se mantiene igual hasta el cierre —una estimación, no
     * una promesa— para poder reaccionar a tiempo si el mes viene flojo.
     *
     * Siempre es el mes en curso, sin importar el rango elegido arriba: es un
     * compromiso mensual, no una lectura del período que se esté mirando.
     *
     * @return array{
     *     meta: int,
     *     realizados: int,
     *     faltantes: int,
     *     diasRestantes: int,
     *     proyeccion: int,
     *     ritmoNecesario: float|null,
     * }
     */
    private function metaConcentrado(): array
    {
        // El día del mes sale del calendario de Lima y no del servidor: de
        // noche, en UTC ya es el día siguiente, y eso adelantaba un día tanto
        // los días transcurridos como los que quedan para cerrar la meta.
        $hoy = RelojOperativo::ahora();

        $viajesDelMes = Viaje::query()
            ->where('cliente', 'like', self::CLIENTE_MINSUR_PREFIJO.'%')
            ->where('tipo_carga', TipoCarga::Concentrado)
            ->whereBetween('fecha_traslado', [
                RelojOperativo::inicioDelMes()->toDateString(),
                RelojOperativo::finDelMes()->toDateString(),
            ])
            ->get(['fecha_traslado', 'tracto_id', 'placa_tracto', 'carreta_id', 'placa_carreta', 'conductor_id', 'conductor_dni']);

        $realizados = Viaje::contarViajesReales($viajesDelMes);
        $faltantes = max(0, self::META_MENSUAL_CONCENTRADO - $realizados);
        $diasTranscurridos = $hoy->day;
        $diasRestantes = $hoy->daysInMonth - $diasTranscurridos;

        return [
            'meta' => self::META_MENSUAL_CONCENTRADO,
            'realizados' => $realizados,
            'faltantes' => $faltantes,
            'diasRestantes' => $diasRestantes,
            'proyeccion' => (int) round($realizados / $diasTranscurridos * $hoy->daysInMonth),
            'ritmoNecesario' => $diasRestantes > 0 ? round($faltantes / $diasRestantes, 1) : null,
        ];
    }

    /**
     * Cómo está el papeleo de toda la flota, fierros y conductores juntos,
     * contado por documento. El estado de cada uno sale del mismo criterio
     * que usan las fichas (`TieneVencimiento::estado()`), así que un «por
     * vencer» de acá es el mismo ámbar que se ve en la ficha del vehículo.
     *
     * @return array{vigentes: int, vencidos: int, por_vencer: int, sin_fecha: int, total: int}
     */
    private function documentosPorEstado(): array
    {
        $documentos = VehiculoDocumento::query()
            ->whereHas('vehiculo')
            ->get(['fecha_vencimiento'])
            ->concat(
                ConductorDocumento::query()
                    ->whereHas('conductor')
                    ->get(['fecha_vencimiento'])
            );

        $porEstado = $documentos->countBy(
            fn (VehiculoDocumento|ConductorDocumento $documento): string => $documento->estado()->value
        );

        // Un documento sin fecha cuenta como vigente en las fichas —basta
        // con tenerlo cargado—, pero acá se separa: no es lo mismo un
        // papel con vigencia comprobada que uno del que no se sabe.
        $sinFecha = $documentos
            ->filter(fn (VehiculoDocumento|ConductorDocumento $documento): bool => $documento->fecha_vencimiento === null)
            ->count();

        return [
            'vigentes' => $porEstado->get(EstadoDocumento::Vigente->value, 0) - $sinFecha,
            'vencidos' => $porEstado->get(EstadoDocumento::Vencido->value, 0),
            'por_vencer' => $porEstado->get(EstadoDocumento::PorVencer->value, 0),
            'sin_fecha' => $sinFecha,
            'total' => $documentos->count(),
        ];
    }

    /**
     * Cuántas unidades hay realmente disponibles hoy: las operativas, las que
     * una novedad vigente saca de la programación, y las que tienen algún
     * papel vencido —esas tres cosas se miran juntas antes de programar.
     *
     * @return array{operativas: int, no_programables: int, con_documentos_vencidos: int, total: int}
     */
    private function unidades(): array
    {
        $hoy = RelojOperativo::hoy();

        return [
            'operativas' => Vehiculo::where('estado', EstadoVehiculo::Activo)->count(),
            'no_programables' => Novedad::vigentes()->distinct()->count('tracto_id'),
            'con_documentos_vencidos' => Vehiculo::query()
                ->whereHas('documentos', function (Builder $query) use ($hoy): void {
                    $query->whereNotNull('fecha_vencimiento')->where('fecha_vencimiento', '<', $hoy);
                })
                ->count(),
            'total' => Vehiculo::count(),
        ];
    }

    /**
     * Cuántos viajes reales —no GR— tiene cada cliente en el rango, con el
     * porcentaje que representa. Se listan los más grandes uno por uno y el
     * resto se junta en una sola barra de «otros».
     *
     * @param  Collection<int, Viaje>  $viajes
     * @return list<array{cliente: string, valor: int, porcentaje: float, es_minsur: bool, es_otros: bool}>
     */
    private function viajesPorCliente(Collection $viajes): array
    {
        $porCliente = $viajes
            ->groupBy(fn (Viaje $viaje): string => $viaje->nombreCliente())
            ->map(fn (Collection $delCliente): int => Viaje::contarViajesReales($delCliente))
            ->sortDesc();

        $total = $porCliente->sum();

        if ($total === 0) {
            return [];
        }

        $principales = $porCliente->take(self::CLIENTES_EN_GRAFICO);
        $resto = $porCliente->slice(self::CLIENTES_EN_GRAFICO);

        $filas = $principales
            ->map(fn (int $valor, string $cliente): array => [
                'cliente' => $cliente,
                'valor' => $valor,
                'porcentaje' => round($valor / $total * 100, 1),
                'es_minsur' => str_starts_with($cliente, self::CLIENTE_MINSUR_PREFIJO),
                'es_otros' => false,
            ])
            ->values()
            ->all();

        if ($resto->isNotEmpty()) {
            $valorResto = $resto->sum();

            $filas[] = [
                'cliente' => sprintf('Otros (%d clientes)', $resto->count()),
                'valor' => $valorResto,
                'porcentaje' => round($valorResto / $total * 100, 1),
                'es_minsur' => false,
                'es_otros' => true,
            ];
        }

        return array_values($filas);
    }

    /**
     * Qué llevaron las unidades de Minsur en el rango, contado por viaje real
     * —no por GR—: un mismo camión puede salir una vez con dos GR, incluso
     * cruzando a un segundo día (ver `Viaje::contarViajesReales()`), y ahí
     * solo debe contar una carga. Se agrupa por tipo primero para que el
     * conteo tolerante no funda viajes de tipos distintos entre sí. En el
     * orden fijo del enum, incluidos los tipos en cero, para que la mezcla se
     * lea completa de un vistazo.
     *
     * @param  Collection<int, Viaje>  $viajes
     * @return list<array{tipo: string, label: string, valor: int, porcentaje: float}>
     */
    private function cargaMinsur(Collection $viajes): array
    {
        $porTipo = $viajes
            ->filter(fn (Viaje $viaje): bool => $this->esDeMinsur($viaje))
            ->groupBy(fn (Viaje $viaje): string => $viaje->tipo_carga->value);

        $conteos = array_map(
            fn (TipoCarga $tipo): array => [
                'tipo' => $tipo->value,
                'label' => $tipo->label(),
                'valor' => Viaje::contarViajesReales($porTipo->get($tipo->value, collect())),
            ],
            $this->tiposDeCarga(),
        );

        $total = array_sum(array_column($conteos, 'valor'));

        return array_map(
            fn (array $conteo): array => [
                ...$conteo,
                'porcentaje' => $total > 0 ? round($conteo['valor'] / $total * 100, 1) : 0.0,
            ],
            $conteos,
        );
    }

    /**
     * Minsur contra el resto: es el corte que define la operación —el
     * concentrado es el contrato principal y todo lo demás es relleno de
     * retorno.
     *
     * @param  Collection<int, Viaje>  $viajes
     * @return array{minsur: int, particulares: int, total: int}
     */
    private function viajesPorTipoCliente(Collection $viajes): array
    {
        [$deMinsur, $particulares] = $viajes->partition(
            fn (Viaje $viaje): bool => $this->esDeMinsur($viaje)
        );

        $minsur = Viaje::contarViajesReales($deMinsur);
        $otros = Viaje::contarViajesReales($particulares);

        return [
            'minsur' => $minsur,
            'particulares' => $otros,
            'total' => $minsur + $otros,
        ];
    }

    /**
     * Los tipos de carga más movidos del rango, de todos los clientes —no
     * solo Minsur—, para ver la mezcla completa del período.
     *
     * @param  Collection<int, Viaje>  $viajes
     * @return list<array{tipo: string, label: string, valor: int}>
     */
    private function topCargas(Collection $viajes): array
    {
        $porTipo = $viajes->groupBy(fn (Viaje $viaje): string => $viaje->tipo_carga->value);

        $conteos = array_map(
            fn (TipoCarga $tipo): array => [
                'tipo' => $tipo->value,
                'label' => $tipo->label(),
                'valor' => Viaje::contarViajesReales($porTipo->get($tipo->value, collect())),
            ],
            $this->tiposDeCarga(),
        );

        usort($conteos, fn (array $a, array $b): int => $b['valor'] <=> $a['valor']);

        return array_values(array_filter(
            array_slice($conteos, 0, 5),
            fn (array $conteo): bool => $conteo['valor'] > 0,
        ));
    }

    /**
     * Los tipos de carga que puede tener un viaje cerrado, en el orden del
     * enum.
     *
     * @return list<TipoCarga>
     */
    private function tiposDeCarga(): array
    {
        $excluidos = TipoCarga::excluidosDeViaje();

        return array_values(array_filter(
            TipoCarga::cases(),
            fn (TipoCarga $tipo): bool => ! in_array($tipo, $excluidos, true),
        ));
    }

    /**
     * Las últimas GR que entraron, sin filtrar por rango: es el pulso de lo
     * que se está registrando ahora mismo, no una lectura del período.
     *
     * @return list<array{id: int, numero_gr: string, fecha_traslado: string, placa_tracto: string, placa_carreta: string|null, cliente: string, tipo_carga: string, tipo_carga_label: string, archivo_url: string|null}>
     */
    private function ultimosViajes(): array
    {
        return array_values(Viaje::query()
            ->with(['media', 'clienteDelPadron:id,alias'])
            ->orderByDesc('fecha_traslado')
            ->orderByDesc('numero_gr')
            ->limit(5)
            ->get()
            ->map(fn (Viaje $viaje): array => [
                'id' => $viaje->id,
                'numero_gr' => $viaje->numero_gr,
                'fecha_traslado' => $viaje->fecha_traslado->toDateString(),
                'placa_tracto' => $viaje->placa_tracto,
                'placa_carreta' => $viaje->placa_carreta,
                'cliente' => $viaje->nombreCliente(),
                'tipo_carga' => $viaje->tipo_carga->value,
                'tipo_carga_label' => $viaje->tipo_carga->label(),
                'archivo_url' => $viaje->getFirstMediaUrl('archivo') ?: null,
            ])
            ->all());
    }
}
