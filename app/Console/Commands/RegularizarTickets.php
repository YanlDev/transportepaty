<?php

namespace App\Console\Commands;

use App\Enums\EstadoVuelta;
use App\Enums\ResultadoTaller;
use App\Enums\SentidoTramo;
use App\Enums\TipoCarga;
use App\Enums\ZonaRuta;
use App\Models\ParadaVuelta;
use App\Models\Ruta;
use App\Models\RutaVuelta;
use App\Models\User;
use App\Models\Viaje;
use App\Models\Vuelta;
use App\Services\Alertas;
use App\Services\AlertasDelTicket;
use App\Services\AsignadorVueltas;
use App\Services\ProgramadorVueltas;
use Carbon\CarbonInterface;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Pone al día los tickets de los viajes que ya se hicieron y no se
 * registraron: arma cada viaje desde las GR de la unidad (uno nuevo cada vez
 * que vuelve a bajar después de haber subido), crea el ticket que falte o
 * completa el que ya existe, le pone las rutas de las que sale el tope de
 * combustible, asigna las GR y da el OK de taller (la unidad ya salió).
 *
 * No registra combustible despachado ni viáticos, y no cierra nada: eso se
 * revisa a mano. Sin `--aplicar` solo muestra lo que haría.
 */
#[Signature('transpaty:regularizar-tickets
    {--desde=2026-11-05 : Solo los viajes con alguna GR desde este día}
    {--mirar-desde= : Desde cuándo se leen las GR para hallar el inicio de cada viaje (20 días antes, si no se dice)}
    {--usuario=admin : Usuario que figura como quien regularizó}
    {--placa=* : Solo estas placas}
    {--trayecto=* : El trayecto de una GR que no lo dice, como NUMERO:origen:destino (EG03-00013150:lima:juliaca)}
    {--aplicar : Guarda los cambios; sin esto solo simula}')]
#[Description('Arma los tickets de los viajes ya hechos desde sus GR, con rutas, tope de combustible y OK de taller')]
class RegularizarTickets extends Command
{
    private const NOTA_TALLER = 'Regularización: la unidad ya había salido cuando se registró el ticket.';

    /** Días entre dos bajadas (o dos subidas) para contarlas como viajes distintos. */
    private const DIAS_ENTRE_VIAJES = 3;

    /** Días sin GR dentro de un mismo viaje a partir de los cuales conviene revisarlo. */
    private const DIAS_SIN_GUIAS = 7;

    /** @var Collection<string, Ruta> */
    private Collection $catalogo;

    private User $usuario;

    /** @var array<string, array{0: ZonaRuta, 1: ZonaRuta}> El trayecto dicho a mano, por número de GR. */
    private array $trayectos = [];

    /** @var list<string> Lo que una persona tiene que mirar del ticket que se está armando. */
    private array $porRevisar = [];

    /** @var list<int> Los tickets que ya tomó un viaje en esta corrida. */
    private array $ticketsTomados = [];

    public function handle(ProgramadorVueltas $programador, AsignadorVueltas $asignador): int
    {
        $usuario = User::query()->where('username', $this->option('usuario'))->first();

        if ($usuario === null) {
            $this->error("No existe el usuario «{$this->option('usuario')}».");

            return self::FAILURE;
        }

        $this->usuario = $usuario;
        $this->catalogo = Ruta::query()->with(['lineas.punto', 'variantes'])->get()->keyBy('nombre');
        $this->silenciarAlertas();

        foreach ((array) $this->option('trayecto') as $trayecto) {
            [$numero, $origen, $destino] = array_pad(explode(':', (string) $trayecto), 3, '');
            $zonas = [ZonaRuta::tryFrom($origen), ZonaRuta::tryFrom($destino)];

            if ($numero === '' || $zonas[0] === null || $zonas[1] === null) {
                $this->error("Trayecto mal escrito: «{$trayecto}». Va como NUMERO:origen:destino.");

                return self::FAILURE;
            }

            $this->trayectos[$numero] = [$zonas[0], $zonas[1]];
        }

        $desde = Carbon::parse((string) $this->option('desde'))->startOfDay();
        $mirarDesde = $this->option('mirar-desde') !== null
            ? Carbon::parse((string) $this->option('mirar-desde'))->startOfDay()
            : $desde->copy()->subDays(20);
        $placas = array_map(fn (mixed $placa): string => $this->sinGuion((string) $placa), (array) $this->option('placa'));

        $porUnidad = Viaje::query()
            ->with('tracto:id,placa')
            ->whereNotNull('tracto_id')
            ->where('fecha_traslado', '>=', $mirarDesde)
            ->orderBy('fecha_traslado')
            ->orderBy('id')
            ->get()
            ->groupBy('tracto_id')
            ->sortBy(fn (Collection $guias): string => (string) $guias->first()->tracto?->placa);

        DB::beginTransaction();

        try {
            foreach ($porUnidad as $guias) {
                $placa = (string) $guias->first()->tracto?->placa;

                if ($placas !== [] && ! in_array($this->sinGuion($placa), $placas, true)) {
                    continue;
                }

                $this->regularizarUnidad($placa, $guias->values(), $desde, $programador, $asignador);
            }
        } catch (\Throwable $error) {
            DB::rollBack();

            throw $error;
        }

        if ($this->option('aplicar')) {
            DB::commit();
            $this->info('Listo: cambios guardados.');
        } else {
            DB::rollBack();
            $this->warn('Simulación: no se guardó nada. Con --aplicar se guarda.');
        }

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, Viaje>  $guias
     */
    private function regularizarUnidad(string $placa, Collection $guias, Carbon $desde, ProgramadorVueltas $programador, AsignadorVueltas $asignador): void
    {
        $viajes = $this->partirEnViajes($guias);
        /** @var array<int, Vuelta|null> $tickets */
        $tickets = [];

        foreach ($viajes as $indice => $viaje) {
            $tickets[$indice] = null;
            $this->porRevisar = [];
            $delViaje = $viaje['guias'];
            $deIda = $delViaje->filter(fn (Viaje $guia): bool => $this->esDeIda($guia));
            // El corte: si toda su ida es anterior, ese día ya estaba
            // subiendo y el ticket lleva solo la vuelta.
            $soloVuelta = $deIda->isNotEmpty() && $deIda->every(fn (Viaje $guia): bool => $guia->fecha_traslado->lt($desde));

            if ($soloVuelta) {
                $delViaje = $delViaje->reject(fn (Viaje $guia): bool => $this->esDeIda($guia))->values();
            }

            $todas = $delViaje->concat($viaje['manuales']);
            $pendientes = $delViaje->filter(fn (Viaje $guia): bool => $guia->parada_vuelta_id === null);

            if ($delViaje->isEmpty() || $todas->every(fn (Viaje $guia): bool => $guia->fecha_traslado->lt($desde))) {
                continue;
            }

            $notas = array_values($viaje['manuales']
                ->filter(fn (Viaje $guia): bool => $guia->parada_vuelta_id === null)
                ->map(fn (Viaje $guia): string => $this->paraRevisar("GR {$guia->numero_gr} sin trayecto reconocible: asignarla a mano"))
                ->all());

            if ($soloVuelta) {
                $notas[] = 'El '.$desde->format('d/m').' ya estaba de vuelta: el ticket lleva solo la vuelta';
            }

            $fechas = $delViaje->map(fn (Viaje $guia): CarbonInterface => $guia->fecha_traslado)->values();

            foreach ($fechas as $posicion => $fecha) {
                if ($posicion > 0 && $fechas[$posicion - 1]->diffInDays($fecha) > self::DIAS_SIN_GUIAS) {
                    $notas[] = $this->paraRevisar('Pasan '.(int) $fechas[$posicion - 1]->diffInDays($fecha).' días entre dos de sus GR: puede faltar un viaje en medio');
                }
            }

            $ticket = $this->ticketDelViaje($delViaje, $notas);

            if ($ticket === false) {
                $this->informar($placa, null, $delViaje, $notas, 'SALTADO');

                continue;
            }

            $tickets[$indice] = $ticket;

            if ($pendientes->isEmpty() && $this->motivosPorRevisar() === []) {
                continue;
            }

            $anterior = $indice > 0 ? $viajes[$indice - 1] : null;
            $eraNuevo = $ticket === null;

            if ($pendientes->isNotEmpty()) {
                $plan = $this->plan($delViaje, $anterior, $soloVuelta, $notas);
                $ticket = $this->guardar($ticket, $delViaje, $plan, $programador, $notas);
            }

            if ($ticket === null) {
                continue;
            }

            $tickets[$indice] = $ticket;
            $this->ticketsTomados[] = $ticket->id;

            foreach ($pendientes as $guia) {
                $ticket->load(['paradas' => fn ($query) => $query->withCount('viajes')]);
                $sugerencia = $asignador->sugerencia($guia, new EloquentCollection([$ticket]));
                $parada = $ticket->paradas->firstWhere('id', $sugerencia['parada_id']);
                $sentido = SentidoTramo::from($sugerencia['sentido']);

                if (isset($this->trayectos[$guia->numero_gr])) {
                    // La GR no dice su trayecto: va en la parada del que se indicó.
                    [$origen, $destino] = $this->trayectos[$guia->numero_gr];
                    $sentido = SentidoTramo::entre($origen, $destino, false);
                    $parada = $ticket->paradas->first(fn (ParadaVuelta $otra): bool => $otra->sentido === $sentido
                        && ! $otra->esContinuacion()
                        && $otra->zona_origen === $origen
                        && $otra->zona_destino === $destino);

                    if ($parada === null) {
                        $parada = $ticket->paradas()->create([
                            'sentido' => $sentido,
                            'orden' => (int) $ticket->paradas->max('orden') + 1,
                            'zona_origen' => $origen,
                            'zona_destino' => $destino,
                            'tipo_carga' => $guia->tipo_carga,
                            'cliente_id' => $guia->cliente_id,
                            'cliente' => $guia->cliente,
                            'fecha' => $guia->fecha_traslado,
                            'fuera_de_programa' => true,
                        ]);
                    }

                    $notas[] = "GR {$guia->numero_gr} sin trayecto en la guía: se puso como {$origen->label()} → {$destino->label()}";
                } elseif ($parada === null) {
                    $notas[] = "GR {$guia->numero_gr} entra como parada no programada";
                }

                $lugares = $parada?->only(['lugar_origen', 'lugar_destino']);
                $parada = $asignador->asignar($guia, $ticket, $parada, $sentido, $this->usuario);

                // Con el trayecto dicho a mano, la dirección de la GR no es
                // el lugar real: la parada conserva los suyos.
                if (isset($this->trayectos[$guia->numero_gr]) && $lugares !== null) {
                    $parada->update($lugares);
                }

                $ticket->refresh();
            }

            if (! $ticket->tieneOkDeTaller()) {
                $ticket->revisiones()->create([
                    'resultado' => ResultadoTaller::Apto,
                    'trabajos' => self::NOTA_TALLER,
                    'user_id' => $this->usuario->id,
                ]);
            }

            if ($this->motivosPorRevisar() !== []) {
                $ticket->update(['por_revisar' => implode("\n", array_unique(array_filter([$ticket->por_revisar, ...$this->motivosPorRevisar()])))]);
            }

            $this->informar($placa, $ticket->fresh(['rutas', 'paradas']), $delViaje, $notas, $eraNuevo ? 'NUEVO' : ($pendientes->isEmpty() ? 'MARCADO' : 'COMPLETADO'));
        }

        $this->marcarLlegadas($viajes, $tickets);
    }

    /**
     * Las GR de la unidad, partidas en viajes: empieza uno cada vez que la
     * unidad vuelve a salir (una GR de ida) después de haber subido, o
     * cuando baja o sube de nuevo varios días después sin GR en medio (fue
     * o volvió vacía). Las GR sin trayecto reconocible, o dentro de la misma
     * zona, no parten nada: quedan anotadas para asignarlas a mano.
     *
     * @param  Collection<int, Viaje>  $guias
     * @return list<array{guias: Collection<int, Viaje>, manuales: Collection<int, Viaje>, fase: SentidoTramo}>
     */
    private function partirEnViajes(Collection $guias): array
    {
        $viajes = [];
        /** @var Collection<int, Viaje>|null $delViaje */
        $delViaje = null;
        $manuales = new Collection;
        $fase = SentidoTramo::Ida;
        $cerrar = function () use (&$viajes, &$delViaje, &$manuales, &$fase): void {
            if ($delViaje !== null) {
                $viajes[] = ['guias' => $delViaje, 'manuales' => $manuales, 'fase' => $fase];
            }
        };

        foreach ($guias as $guia) {
            [$origen, $destino] = $this->zonas($guia);

            if ($origen === null || $destino === null || $origen === $destino) {
                if ($delViaje !== null) {
                    $manuales->push($guia);
                }

                continue;
            }

            $sentido = SentidoTramo::entre($origen, $destino, $fase === SentidoTramo::Regreso);
            $baja = $origen->esSierra() && ! $destino->esSierra();
            $sube = ! $origen->esSierra() && $destino->esSierra();

            $repite = $delViaje !== null && $delViaje->contains(function (Viaje $otra) use ($guia, $baja, $sube): bool {
                [$otroOrigen, $otroDestino] = $this->zonas($otra);
                $mismoMovimiento = ($baja && $otroOrigen?->esSierra() && ! $otroDestino?->esSierra())
                    || ($sube && ! $otroOrigen?->esSierra() && $otroDestino?->esSierra());

                return $mismoMovimiento && $otra->fecha_traslado->diffInDays($guia->fecha_traslado) >= self::DIAS_ENTRE_VIAJES;
            });

            if ($delViaje === null || $repite || ($sentido === SentidoTramo::Ida && $fase === SentidoTramo::Regreso)) {
                // Una GR sin trayecto de los días previos es de este viaje, no del que terminó.
                $ultima = $delViaje?->max('fecha_traslado');
                $cercanas = fn (Viaje $manual): bool => $ultima !== null
                    && $manual->fecha_traslado->gt($ultima)
                    && $manual->fecha_traslado->diffInDays($guia->fecha_traslado) <= self::DIAS_ENTRE_VIAJES;
                $arrastradas = $manuales->filter($cercanas)->values();
                $manuales = $manuales->reject($cercanas)->values();
                $cerrar();
                $delViaje = new Collection;
                $manuales = $arrastradas;
                $sentido = SentidoTramo::entre($origen, $destino, false);
            }

            $delViaje->push($guia);
            $fase = $sentido;
        }

        $cerrar();

        return $viajes;
    }

    /**
     * El ticket que ya tiene este viaje: el de sus GR ya asignadas o, si no
     * hay, el programado de la unidad que todavía espera sus GR. Null si hay
     * que crearlo; false si no se puede decidir solo.
     *
     * @param  Collection<int, Viaje>  $guias
     * @param  list<string>  $notas
     */
    private function ticketDelViaje(Collection $guias, array &$notas): Vuelta|false|null
    {
        $ids = ParadaVuelta::query()
            ->whereIn('id', $guias->pluck('parada_vuelta_id')->filter()->all())
            ->pluck('vuelta_id')->unique()->values();

        if ($ids->count() > 1) {
            $notas[] = 'Sus GR están repartidas en más de un ticket: revisar a mano';

            return false;
        }

        if ($ids->count() === 1) {
            $ticket = Vuelta::query()->whereKey($ids->first())->sole();

            if (! $ticket->estaAbierta() && $guias->contains(fn (Viaje $guia): bool => $guia->parada_vuelta_id === null)) {
                $notas[] = "El ticket {$ticket->codigo} está cerrado: reabrirlo para completarlo";

                return false;
            }

            return $ticket;
        }

        $primera = $guias->min('fecha_traslado');
        $ultima = $guias->max('fecha_traslado');

        return Vuelta::query()
            ->where('tracto_id', $guias->first()->tracto_id)
            ->where('estado', EstadoVuelta::Programada)
            ->whereNotIn('id', $this->ticketsTomados)
            ->whereDoesntHave('viajes')
            ->whereBetween('inicio', [Carbon::parse($primera)->subDays(7), Carbon::parse($ultima)->addDay()])
            ->orderBy('inicio')
            ->first();
    }

    /**
     * Las paradas y las rutas que le tocan al viaje según sus GR.
     *
     * @param  Collection<int, Viaje>  $guias
     * @param  array{guias: Collection<int, Viaje>, manuales: Collection<int, Viaje>, fase: SentidoTramo}|null  $anterior
     * @param  list<string>  $notas
     * @return array{paradas: list<array<string, mixed>>, rutas: list<array{sentido: string, ruta: Ruta, variante_ruta_id: int|null}>}
     */
    private function plan(Collection $guias, ?array $anterior, bool $soloVuelta, array &$notas): array
    {
        $paradas = [];
        $rutas = [];
        $tipo = fn (Viaje $guia, TipoCarga $carga): bool => $guia->tipo_carga === $carga;
        $trayecto = fn (Viaje $guia, ZonaRuta $origen, ZonaRuta $destino): bool => $this->zonas($guia) === [$origen, $destino];
        $sube = fn (Viaje $guia): bool => ! $this->zonas($guia)[0]->esSierra() && $this->zonas($guia)[1]->esSierra();

        $concentrado = $guias->first(fn (Viaje $guia): bool => $trayecto($guia, ZonaRuta::Mina, ZonaRuta::Pisco));
        $metalico = $guias->first(fn (Viaje $guia): bool => $trayecto($guia, ZonaRuta::Pisco, ZonaRuta::Lima));
        $aLima = $guias->first(fn (Viaje $guia): bool => $trayecto($guia, ZonaRuta::Juliaca, ZonaRuta::Lima));
        // La carga que manda en la vuelta es la más pesada: una entrega
        // chica de paso (Ilo) no define la ruta de la que va a Juliaca.
        $subida = $guias->filter($sube)
            ->sortByDesc(fn (Viaje $guia): float => $guias
                ->filter(fn (Viaje $otra): bool => $this->zonas($otra) === $this->zonas($guia))
                ->sum(fn (Viaje $otra): float => $this->toneladas($otra)))
            ->first();

        // La ida: por concentrado (con metálico, o vacía de Pisco a Lima si
        // después carga en Lima) o con carga particular a Lima.
        if ($soloVuelta) {
            // Su ida es anterior al corte: no se arma.
        } elseif ($concentrado !== null || $metalico !== null) {
            $trae = $this->cargaQueSube($anterior);
            $rutas[] = $this->ruta(SentidoTramo::Ida, 'Concentrado a Pisco');
            $paradas[] = $this->parada(SentidoTramo::Ida, ZonaRuta::Juliaca, ZonaRuta::Mina, $trae === null ? TipoCarga::Vacio : $trae->tipo_carga, $trae?->id);
            $paradas[] = $this->parada(SentidoTramo::Ida, ZonaRuta::Mina, ZonaRuta::Pisco, TipoCarga::Concentrado);

            if ($trae?->tipo_carga === TipoCarga::Escoria) {
                $rutas[] = $this->ruta(SentidoTramo::Ida, 'Sube con escoria');
            }

            if ($concentrado === null) {
                $notas[] = 'El concentrado de este viaje es anterior a las GR leídas: la ida va sin su GR';
            }

            if ($metalico !== null) {
                $rutas[] = $this->ruta(SentidoTramo::Ida, 'Metálico al Callao');
                $paradas[] = $this->parada(SentidoTramo::Ida, ZonaRuta::Pisco, ZonaRuta::Lima, TipoCarga::Metalico);
            } elseif ($subida !== null && $this->zonas($subida)[0] === ZonaRuta::Lima) {
                $rutas[] = $this->ruta(SentidoTramo::Ida, 'Vacía de Pisco a Lima');
                $paradas[] = $this->parada(SentidoTramo::Ida, ZonaRuta::Pisco, ZonaRuta::Lima, TipoCarga::Vacio);
            }
        } elseif ($aLima !== null) {
            $rutas[] = $this->ruta(SentidoTramo::Ida, 'Particular a Lima');
            $paradas[] = $this->parada(SentidoTramo::Ida, ZonaRuta::Juliaca, ZonaRuta::Lima, TipoCarga::Particular);
        } elseif ($subida !== null && $anterior !== null) {
            $notas[] = $this->paraRevisar('Sin GR de bajada: se asumió que bajó vacía a Lima');
            $rutas[] = $this->ruta(SentidoTramo::Ida, 'Vacía a Lima');
            $paradas[] = $this->parada(SentidoTramo::Ida, ZonaRuta::Juliaca, ZonaRuta::Lima, TipoCarga::Vacio);
        }

        if ($subida === null) {
            return ['paradas' => $paradas, 'rutas' => array_values(array_filter($rutas))];
        }

        // La vuelta: la ruta de la primera carga que sube.
        [$origen, $destino] = $this->zonas($subida);
        $toneladas = $guias->filter(fn (Viaje $guia): bool => $this->zonas($guia) === [$origen, $destino])->sum(fn (Viaje $guia): float => $this->toneladas($guia));
        $nombre = match ($destino) {
            ZonaRuta::Mina => match (true) {
                $tipo($subida, TipoCarga::Escoria) => 'Escoria a mina',
                in_array($subida->tipo_carga, [TipoCarga::Sacos, TipoCarga::SacosNuevos, TipoCarga::SacosUsados], true) => 'Sacos a mina',
                $tipo($subida, TipoCarga::Particular) => 'Particular a mina',
                default => 'Materiales a mina (Ransa)',
            },
            ZonaRuta::Juliaca => str_contains(mb_strtoupper($subida->origen), 'CHILCA') ? 'Particular de Chilca a Juliaca' : 'Particular a Juliaca',
            ZonaRuta::Arequipa => 'Particular a Arequipa',
            ZonaRuta::Cusco => 'Particular a Cusco',
            ZonaRuta::Moquegua => 'Particular a Moquegua – Ilo',
            ZonaRuta::Tacna => 'Particular a Tacna (Pucamarca)',
            default => null,
        };
        $principal = $nombre === null ? null : $this->ruta(SentidoTramo::Regreso, $nombre, $toneladas);

        if ($principal === null) {
            $notas[] = $this->paraRevisar('No hay ruta de vuelta para '.$origen->label().' → '.$destino->label().': elegirla a mano');
        } else {
            if ($origen === ZonaRuta::Pisco && $metalico !== null) {
                $rutas[] = $this->ruta(SentidoTramo::Regreso, 'Retorno vacío Kio – Pisco');
                $paradas[] = $this->parada(SentidoTramo::Regreso, ZonaRuta::Lima, ZonaRuta::Pisco, TipoCarga::Vacio);
            }

            $rutas[] = $principal;

            foreach ($principal['ruta']->paradas as $parada) {
                if (($parada['zona_destino'] ?? null) !== null) {
                    $paradas[] = [...$parada, 'sentido' => SentidoTramo::Regreso->value];
                }
            }

            if ($guias->contains(fn (Viaje $guia): bool => $sube($guia) && str_contains(mb_strtoupper($guia->origen), 'HUARAL'))) {
                $rutas[] = $this->ruta(SentidoTramo::Regreso, 'Ida y vuelta Huaral');
            }

            // La carga que consigue de paso al subir: harina de Ilo a Juliaca.
            if ($guias->contains(fn (Viaje $guia): bool => $trayecto($guia, ZonaRuta::Moquegua, ZonaRuta::Juliaca))) {
                $rutas[] = $this->ruta(SentidoTramo::Regreso, 'Ilo a Juliaca con harina');
                $paradas[] = $this->parada(SentidoTramo::Regreso, ZonaRuta::Moquegua, ZonaRuta::Juliaca, TipoCarga::Particular);
            }
        }

        return ['paradas' => $paradas, 'rutas' => array_values(array_filter($rutas))];
    }

    /**
     * Crea el ticket o completa el que ya había: conserva sus paradas y sus
     * rutas, y suma lo que le falta. La ruta principal de un tramo solo se
     * cambia si ese tramo todavía no tiene gastos ni GR.
     *
     * @param  Collection<int, Viaje>  $guias
     * @param  array{paradas: list<array<string, mixed>>, rutas: list<array{sentido: string, ruta: Ruta, variante_ruta_id: int|null}>}  $plan
     * @param  list<string>  $notas
     */
    private function guardar(?Vuelta $ticket, Collection $guias, array $plan, ProgramadorVueltas $programador, array &$notas): Vuelta
    {
        $primera = $guias->first();
        $inicio = Carbon::parse($guias->min('fecha_traslado'));
        $elegidas = fn (array $rutas): array => $this->elegidas($rutas);

        if ($ticket === null) {
            return $programador->programar([
                'tracto_id' => (int) $primera->tracto_id,
                'carreta_id' => $primera->carreta_id,
                'conductor_id' => $primera->conductor_id,
                'inicio' => $inicio->toDateString(),
                'paradas' => $plan['paradas'],
                'rutas' => $elegidas($plan['rutas']),
            ], $this->usuario);
        }

        $ticket->load(['paradas' => fn ($query) => $query->withCount('viajes'), 'rutas']);
        $paradas = $ticket->paradas->map(fn (ParadaVuelta $parada): array => [
            'id' => $parada->id,
            'sentido' => $parada->sentido->value,
            'zona_origen' => $parada->zona_origen?->value,
            'zona_destino' => $parada->zona_destino?->value,
            'lugar_origen' => $parada->lugar_origen,
            'lugar_destino' => $parada->lugar_destino,
            'tipo_carga' => $parada->tipo_carga->value,
            'cliente_id' => $parada->cliente_id,
            'cliente' => $parada->cliente,
            'viaje_continuado_id' => $parada->viaje_continuado_id,
            'con_guias' => $parada->viajes_count > 0,
        ])->all();
        $rutas = $ticket->rutas->filter(fn (RutaVuelta $ruta): bool => ! $ruta->esAdicional() && $ruta->ruta_id !== null)
            ->map(fn (RutaVuelta $ruta): array => ['sentido' => $ruta->sentido->value, 'ruta_id' => $ruta->ruta_id, 'variante_ruta_id' => $ruta->variante_ruta_id])
            ->values()->all();

        foreach (SentidoTramo::cases() as $sentido) {
            $delPlan = array_values(array_filter($plan['rutas'], fn (array $ruta): bool => $ruta['sentido'] === $sentido->value));
            $paradasDelPlan = array_values(array_filter($plan['paradas'], fn (array $parada): bool => $parada['sentido'] === $sentido->value));
            $principalDelPlan = collect($delPlan)->first(fn (array $ruta): bool => ! $ruta['ruta']->es_complemento);
            $principal = $ticket->rutas->first(fn (RutaVuelta $ruta): bool => $ruta->sentido === $sentido && ! $ruta->es_complemento && ! $ruta->esAdicional());
            $libre = ! $ticket->tramoConGastos($sentido)
                && ! $ticket->paradas->contains(fn (ParadaVuelta $parada): bool => $parada->sentido === $sentido && $parada->viajes_count > 0);

            if ($principal !== null && $principalDelPlan !== null && $principal->ruta_id !== $principalDelPlan['ruta']->id) {
                if ($libre) {
                    // Se programó otra cosa y todavía no pasó nada: manda lo que dicen las GR.
                    $notas[] = "La {$sentido->label()} estaba programada como «{$principal->nombre}»: pasa a «{$principalDelPlan['ruta']->nombre}»";
                    $paradas = array_values(array_filter($paradas, fn (array $parada): bool => $parada['sentido'] !== $sentido->value));
                    $rutas = array_values(array_filter($rutas, fn (array $ruta): bool => $ruta['sentido'] !== $sentido->value));
                } else {
                    $notas[] = $this->paraRevisar("La {$sentido->label()} ya tiene «{$principal->nombre}» con GR o gastos: no se cambió a «{$principalDelPlan['ruta']->nombre}»");
                    $delPlan = array_values(array_filter($delPlan, fn (array $ruta): bool => $ruta['ruta']->es_complemento));
                    $paradasDelPlan = [];
                }
            }

            foreach ($elegidas($delPlan) as $elegida) {
                $yaEsta = collect($rutas)->search(fn (array $ruta): bool => $ruta['sentido'] === $sentido->value && $ruta['ruta_id'] === $elegida['ruta_id']);

                if ($yaEsta === false) {
                    $rutas[] = $elegida;
                } elseif ($rutas[$yaEsta]['variante_ruta_id'] === null && $elegida['variante_ruta_id'] !== null) {
                    $rutas[$yaEsta]['variante_ruta_id'] = $elegida['variante_ruta_id'];
                }
            }

            foreach ($paradasDelPlan as $parada) {
                $yaEsta = collect($paradas)->contains(fn (array $otra): bool => $otra['sentido'] === $parada['sentido']
                    && $otra['zona_origen'] === $parada['zona_origen']
                    && $otra['zona_destino'] === $parada['zona_destino']);

                if (! $yaEsta) {
                    $paradas[] = $parada;
                }
            }
        }

        return $programador->actualizar($ticket, [
            'tracto_id' => $ticket->tracto_id,
            'carreta_id' => $ticket->carreta_id ?? $primera->carreta_id,
            'conductor_id' => $ticket->conductor_id ?? $primera->conductor_id,
            'inicio' => ($ticket->inicio === null || $ticket->inicio->gt($inicio) ? $inicio : $ticket->inicio)->toDateString(),
            'paradas' => array_values($paradas),
            'rutas' => array_values($rutas),
        ], $this->usuario);
    }

    /**
     * Si la unidad volvió a salir es que llegó: al ticket anterior que sigue
     * en ruta se le anota la llegada el día de la primera GR del siguiente
     * (sin km: nadie lo anotó).
     *
     * @param  list<array{guias: Collection<int, Viaje>, manuales: Collection<int, Viaje>, fase: SentidoTramo}>  $viajes
     * @param  array<int, Vuelta|null>  $tickets
     */
    private function marcarLlegadas(array $viajes, array $tickets): void
    {
        foreach ($viajes as $indice => $viaje) {
            $ticket = $tickets[$indice] ?? null;
            $siguiente = $viajes[$indice + 1] ?? null;

            if ($ticket === null || $siguiente === null || ! in_array($ticket->id, $this->ticketsTomados, true)) {
                continue;
            }

            $ticket->refresh();

            if ($ticket->estado === EstadoVuelta::EnCurso && $ticket->llegada_el === null) {
                $llegada = Carbon::parse($siguiente['guias']->min('fecha_traslado'));
                $ticket->update(['llegada_el' => $llegada->toDateString(), 'llegada_por' => $this->usuario->id]);
                $this->line("     ↳ {$ticket->codigo}: llegada deducida el {$llegada->format('d/m')} (volvió a salir)");
            }
        }
    }

    /**
     * La carga con que la unidad sube de Juliaca a la mina: la que trajo de
     * la costa para la mina en el viaje anterior (escoria, materiales).
     *
     * @param  array{guias: Collection<int, Viaje>, manuales: Collection<int, Viaje>, fase: SentidoTramo}|null  $anterior
     */
    private function cargaQueSube(?array $anterior): ?Viaje
    {
        return $anterior === null ? null : $anterior['guias']->last(function (Viaje $guia): bool {
            [$origen, $destino] = $this->zonas($guia);

            return ! $origen->esSierra() && $destino === ZonaRuta::Mina;
        });
    }

    /**
     * @return array{sentido: string, ruta: Ruta, variante_ruta_id: int|null}|null
     */
    private function ruta(SentidoTramo $sentido, string $nombre, ?float $toneladas = null): ?array
    {
        $ruta = $this->catalogo->get($nombre);

        if ($ruta === null) {
            $this->warn("     La ruta «{$nombre}» no está en el catálogo.");

            return null;
        }

        return [
            'sentido' => $sentido->value,
            'ruta' => $ruta,
            'variante_ruta_id' => $toneladas !== null && $toneladas > 0 ? $ruta->varianteParaPeso($toneladas)?->id : null,
        ];
    }

    /**
     * Las rutas del plan como las espera el programador.
     *
     * @param  array<int, array{sentido: string, ruta: Ruta, variante_ruta_id: int|null}>  $rutas
     * @return list<array{sentido: string, ruta_id: int, variante_ruta_id: int|null}>
     */
    private function elegidas(array $rutas): array
    {
        return array_values(array_map(fn (array $ruta): array => [
            'sentido' => $ruta['sentido'], 'ruta_id' => $ruta['ruta']->id, 'variante_ruta_id' => $ruta['variante_ruta_id'],
        ], $rutas));
    }

    /**
     * @return array<string, mixed>
     */
    private function parada(SentidoTramo $sentido, ZonaRuta $origen, ZonaRuta $destino, TipoCarga $carga, ?int $continuaLaGuia = null): array
    {
        return [
            'sentido' => $sentido->value,
            'zona_origen' => $origen->value,
            'zona_destino' => $destino->value,
            'tipo_carga' => $carga->value,
            'viaje_continuado_id' => $continuaLaGuia,
        ];
    }

    /**
     * @return array{0: ZonaRuta|null, 1: ZonaRuta|null}
     */
    private function zonas(Viaje $guia): array
    {
        if (isset($this->trayectos[$guia->numero_gr])) {
            return $this->trayectos[$guia->numero_gr];
        }

        return [
            blank($guia->origen) ? null : ZonaRuta::desdeDireccion($guia->origen),
            blank($guia->destino) ? null : ZonaRuta::desdeDireccion($guia->destino),
        ];
    }

    /**
     * Una GR de la ida: sale de Juliaca o termina en la costa (concentrado,
     * metálico al Callao, carga particular a Lima).
     */
    private function esDeIda(Viaje $guia): bool
    {
        [$origen, $destino] = $this->zonas($guia);

        return $origen !== null && $destino !== null && ($origen->esBase() || ! $destino->esSierra());
    }

    /**
     * Anota algo que una persona tiene que mirar: el ticket queda marcado
     * «por revisar» con ese motivo.
     */
    private function paraRevisar(string $motivo): string
    {
        $this->porRevisar[] = $motivo;

        return $motivo;
    }

    /**
     * @return list<string>
     */
    private function motivosPorRevisar(): array
    {
        return $this->porRevisar;
    }

    /**
     * El peso de la GR en toneladas: viene en kilos o en toneladas según
     * quién la emitió.
     */
    private function toneladas(Viaje $guia): float
    {
        $peso = (float) $guia->peso;

        return $guia->unidad_peso === 'KGM' || $peso > 1000 ? $peso / 1000 : $peso;
    }

    /**
     * @param  Collection<int, Viaje>  $guias
     * @param  list<string>  $notas
     */
    private function informar(string $placa, ?Vuelta $ticket, Collection $guias, array $notas, string $accion): void
    {
        $tope = fn (SentidoTramo $sentido): string => $ticket === null || $ticket->rutasDe($sentido)->isEmpty()
            ? '—'
            : (($ticket->topeDe($sentido) === null ? 'por definir' : rtrim(rtrim(number_format($ticket->topeDe($sentido), 2, '.', ''), '0'), '.').' gal')
                .' ('.$ticket->rutasDe($sentido)->pluck('nombre')->implode(' + ').')');

        $this->line(sprintf('<info>%s</info> · %s %s · sale %s', $placa, $accion, $ticket === null ? '' : (string) $ticket->codigo, $ticket?->inicio?->format('d/m') ?? '—'));

        foreach ($guias as $guia) {
            [$origen, $destino] = $this->zonas($guia);
            $this->line(sprintf('     %s  %s  %-11s %s → %s  %s', $guia->fecha_traslado->format('d/m'), $guia->numero_gr, $guia->tipo_carga->value, $origen?->label() ?? '¿?', $destino?->label() ?? '¿?', mb_substr((string) $guia->cliente, 0, 28)));
        }

        if ($ticket !== null) {
            $this->line('     Ida:    '.$tope(SentidoTramo::Ida));
            $this->line('     Vuelta: '.$tope(SentidoTramo::Regreso));
        }

        foreach ($notas as $nota) {
            $this->line("     <comment>! {$nota}</comment>");
        }
    }

    /**
     * Regularizar no avisa a nadie: son viajes que ya pasaron.
     */
    private function silenciarAlertas(): void
    {
        app()->instance(AlertasDelTicket::class, new class(app(Alertas::class)) extends AlertasDelTicket
        {
            public function salio(Vuelta $vuelta): void {}
        });
    }

    private function sinGuion(string $placa): string
    {
        return mb_strtoupper(str_replace('-', '', $placa));
    }
}
