<?php

namespace App\Services;

use App\Enums\EstadoVuelta;
use App\Enums\OrigenVuelta;
use App\Enums\Permiso;
use App\Enums\ResultadoTaller;
use App\Enums\SentidoTramo;
use App\Models\User;
use App\Models\Viaje;
use App\Models\Vuelta;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * Lo que le toca hacer hoy a cada quien, según lo que puede hacer: taller ve
 * lo que espera revisión, abastecimiento lo que sale sin combustible,
 * programación las unidades en base sin ticket, facturación las GR sin
 * facturar. Cada pendiente dice cuántos son, muestra los primeros y lleva
 * directo a donde se resuelve.
 *
 * Un pendiente en cero también se muestra («al día»): así cada quien sabe
 * qué le corresponde mirar.
 *
 * @phpstan-import-type GastosTicket from GastosDelTicket
 *
 * @phpstan-type Item array{texto: string, detalle: string|null, href: string}
 * @phpstan-type Pendiente array{area: string, clave: string, titulo: string, ayuda: string, cantidad: int, tono: 'alerta'|'aviso'|'info', href: string, items: list<Item>}
 */
class PendientesDelInicio
{
    /** Cuántos se muestran de cada pendiente; el resto, en su pantalla. */
    private const MOSTRADOS = 5;

    /** Hasta cuántos días en base se muestra una unidad sin ticket. */
    private const DIAS_EN_BASE = 30;

    private ?User $usuario = null;

    /** @var Collection<int, Vuelta>|null */
    private ?Collection $abiertas = null;

    /** @var array<int, GastosTicket>|null */
    private ?array $gastos = null;

    public function __construct(private readonly GastosDelTicket $gastosDelTicket) {}

    /**
     * @return list<Pendiente>
     */
    public function para(User $usuario): array
    {
        $this->usuario = $usuario;
        $puede = fn (Permiso $permiso): bool => $usuario->checkPermissionTo($permiso);
        $pendientes = [];

        if ($puede(Permiso::TallerRevisar)) {
            $pendientes[] = $this->porRevisar();
            $pendientes[] = $this->noAptos();
        }

        if ($puede(Permiso::AbastecimientoRegistrar)) {
            $pendientes[] = $this->combustible(SentidoTramo::Ida);
            $pendientes[] = $this->combustible(SentidoTramo::Regreso);
        }

        // Depositar es de abastecimiento; facturación solo los corrige.
        if ($puede(Permiso::AbastecimientoViaticos) && $puede(Permiso::AbastecimientoRegistrar)) {
            $pendientes[] = $this->viaticos();
        }

        if ($puede(Permiso::ProgramacionCrear)) {
            $pendientes[] = $this->enBaseSinTicket();
            $pendientes[] = $this->regresosPorDefinir();
            $pendientes[] = $this->salidasAtrasadas();
        }

        if ($puede(Permiso::ProgramacionCerrar)) {
            $pendientes[] = $this->porCerrar();
        }

        if ($puede(Permiso::CobranzaGestionar)) {
            $pendientes[] = $this->sinFacturar();
        }

        return $pendientes;
    }

    /**
     * @return Pendiente
     */
    private function porRevisar(): array
    {
        $tickets = $this->abiertas()
            ->filter(fn (Vuelta $vuelta): bool => $vuelta->revision === null && $vuelta->etapa() !== 'en_base');

        return $this->pendiente('Taller', 'por_revisar', 'Por revisar', 'Tickets que esperan el apto para salir.', 'aviso', route('taller.index'), $tickets,
            fn (Vuelta $vuelta): ?string => $vuelta->inicio ? 'Sale el '.$vuelta->inicio->format('d/m') : null);
    }

    /**
     * @return Pendiente
     */
    private function noAptos(): array
    {
        $ahora = RelojOperativo::ahora();
        $tickets = $this->abiertas()
            ->filter(fn (Vuelta $vuelta): bool => $vuelta->revision?->resultado === ResultadoTaller::NoApto);
        $vencidos = $tickets->contains(fn (Vuelta $vuelta): bool => $vuelta->revision?->disponible_estimado?->lt($ahora) ?? false);

        return $this->pendiente('Taller', 'no_aptos', 'No aptos en taller',
            $vencidos ? 'Alguna ya pasó la hora en que iba a estar lista.' : 'Unidades en taller, con la hora en que estarán listas.',
            $vencidos ? 'alerta' : 'info', route('taller.index'), $tickets,
            fn (Vuelta $vuelta): ?string => $vuelta->revision?->disponible_estimado
                ? 'Lista '.$vuelta->revision->disponible_estimado->format('d/m H:i')
                : null);
    }

    /**
     * Los tickets que pueden recibir combustible y a los que todavía les falta
     * el de ese tramo: el de ida antes de salir o en la ida; el de vuelta
     * cuando el regreso ya está definido y no llegó a la base.
     *
     * @return Pendiente
     */
    private function combustible(SentidoTramo $sentido): array
    {
        $ida = $sentido === SentidoTramo::Ida;
        $tramoBuscado = $ida ? 'ida' : 'regreso';
        $etapas = $ida ? ['programada', 'en_ida'] : ['en_ida', 'en_vuelta'];

        $tickets = $this->abiertas()->filter(function (Vuelta $vuelta) use ($tramoBuscado, $etapas): bool {
            if ($vuelta->bloqueoDeGastos() !== null || ! in_array($vuelta->etapa(), $etapas, true)) {
                return false;
            }

            $tramo = collect($this->gastos()[$vuelta->id]['tramos'])->firstWhere('sentido', $tramoBuscado);

            return $tramo !== null && ($tramo['falta'] === null ? $tramo['registrado'] === 0.0 : $tramo['falta'] > 0);
        });

        return $this->pendiente('Abastecimiento', $ida ? 'combustible_ida' : 'combustible_vuelta',
            $ida ? 'Combustible de ida' : 'Combustible de vuelta',
            $ida ? 'Con el apto de taller y sin todo el combustible de la ida.' : 'Con el regreso definido y sin todo el combustible de la vuelta.',
            'aviso', route('abastecimiento.index'), $tickets,
            function (Vuelta $vuelta) use ($tramoBuscado): ?string {
                $tramo = collect($this->gastos()[$vuelta->id]['tramos'])->firstWhere('sentido', $tramoBuscado);

                return $tramo && $tramo['falta'] !== null ? 'Faltan '.round($tramo['falta'], 1).' gal' : null;
            },
            fn (Vuelta $vuelta): string => route('abastecimiento.index', ['ticket' => $vuelta->id]));
    }

    /**
     * @return Pendiente
     */
    private function viaticos(): array
    {
        $tickets = $this->abiertas()->filter(fn (Vuelta $vuelta): bool => $vuelta->bloqueoDeGastos() === null
            && $this->gastos()[$vuelta->id]['viatico_pendiente'] > 0);

        return $this->pendiente('Abastecimiento', 'viaticos', 'Viáticos por depositar', 'Lo que dice la pizarra y todavía no se depositó.', 'aviso',
            route('abastecimiento.index'), $tickets,
            fn (Vuelta $vuelta): string => 'S/ '.number_format($this->gastos()[$vuelta->id]['viatico_pendiente'], 2),
            fn (Vuelta $vuelta): string => route('abastecimiento.index', ['ticket' => $vuelta->id]));
    }

    /**
     * Las unidades cuyo último ticket ya volvió a la base y que no tienen el
     * siguiente programado: son las que hay que programar.
     *
     * @return Pendiente
     */
    private function enBaseSinTicket(): array
    {
        $hoy = RelojOperativo::fechaDeHoy();
        $ultimas = Vuelta::query()->selectRaw('max(id)')->where('estado', '!=', EstadoVuelta::Anulada)->groupBy('tracto_id');

        $tickets = Vuelta::query()
            ->whereIn('id', $ultimas)
            ->whereIn('estado', [EstadoVuelta::EnCurso, EstadoVuelta::Cerrada])
            ->with(['tracto:id,placa', 'conductor:id,nombres,apellidos', 'paradas.viajes:id,parada_vuelta_id,fecha_traslado'])
            ->get()
            ->filter(fn (Vuelta $vuelta): bool => $vuelta->etapa() === 'en_base'
                && ($this->llegada($vuelta)?->greaterThanOrEqualTo($hoy->subDays(self::DIAS_EN_BASE)) ?? false))
            ->sortBy(fn (Vuelta $vuelta): string => $this->llegada($vuelta)?->toDateString() ?? '');

        return $this->pendiente('Programación', 'en_base', 'En base sin ticket', 'Unidades que volvieron y esperan su próximo viaje.', 'info',
            route('vueltas.index'), $tickets,
            function (Vuelta $vuelta) use ($hoy): ?string {
                $llegada = $this->llegada($vuelta);

                return $llegada ? 'En base hace '.(int) $llegada->diffInDays($hoy).' d' : null;
            });
    }

    /**
     * @return Pendiente
     */
    private function regresosPorDefinir(): array
    {
        $tickets = $this->abiertas()->filter(fn (Vuelta $vuelta): bool => $vuelta->etapa() === 'en_ida'
            && $vuelta->paradasDe(SentidoTramo::Regreso)->isEmpty());

        return $this->pendiente('Programación', 'regresos', 'Regresos por definir', 'Ya van de ida y no se sabe con qué vuelven.', 'aviso',
            route('vueltas.index'), $tickets,
            fn (Vuelta $vuelta): ?string => $vuelta->paradas->last()->lugar_destino ?? $vuelta->paradas->last()?->zona_destino?->label());
    }

    /**
     * @return Pendiente
     */
    private function salidasAtrasadas(): array
    {
        $hoy = RelojOperativo::fechaDeHoy();
        $tickets = $this->abiertas()->filter(fn (Vuelta $vuelta): bool => $vuelta->estado === EstadoVuelta::Programada
            && $vuelta->inicio !== null && $vuelta->inicio->lt($hoy));

        return $this->pendiente('Programación', 'atrasadas', 'Salidas atrasadas', 'Debían salir y todavía no tienen GR.', 'alerta',
            route('vueltas.index'), $tickets,
            fn (Vuelta $vuelta): string => 'Salía el '.$vuelta->inicio?->format('d/m'));
    }

    /**
     * @return Pendiente
     */
    private function porCerrar(): array
    {
        $tickets = $this->abiertas()->filter(fn (Vuelta $vuelta): bool => $vuelta->origen === OrigenVuelta::Programacion
            && $vuelta->estado === EstadoVuelta::EnCurso
            && $vuelta->etapa() === 'en_base');

        return $this->pendiente('Programación', 'por_cerrar', 'Por cerrar', 'Ya llegaron a la base: revisa sus gastos y ponles el candado.', 'info',
            route('vueltas.index'), $tickets,
            fn (Vuelta $vuelta): ?string => ($llegada = $this->llegada($vuelta)) ? 'Llegó el '.$llegada->format('d/m') : null);
    }

    /**
     * Solo la cantidad: la lista entera está en contabilidad.
     *
     * @return Pendiente
     */
    private function sinFacturar(): array
    {
        $cantidad = Viaje::query()->whereDoesntHave('facturas')->whereNull('no_facturable_at')->count();

        return [
            'area' => 'Facturación',
            'clave' => 'sin_facturar',
            'titulo' => 'GR sin facturar',
            'ayuda' => 'Guías que todavía no tienen factura.',
            'cantidad' => $cantidad,
            'tono' => 'info',
            'href' => route('contabilidad.index', ['estado' => 'sin_facturar']),
            'items' => [],
        ];
    }

    /**
     * @param  'alerta'|'aviso'|'info'  $tono
     * @param  \Illuminate\Support\Collection<int, Vuelta>  $tickets
     * @param  callable(Vuelta): (string|null)  $detalle
     * @param  (callable(Vuelta): string)|null  $enlace  a dónde lleva cada ticket; si no, a su expediente
     * @return Pendiente
     */
    private function pendiente(string $area, string $clave, string $titulo, string $ayuda, string $tono, string $href, \Illuminate\Support\Collection $tickets, callable $detalle, ?callable $enlace = null): array
    {
        $verExpediente = $this->usuario?->checkPermissionTo(Permiso::ProgramacionVer) ?? false;

        return [
            'area' => $area,
            'clave' => $clave,
            'titulo' => $titulo,
            'ayuda' => $ayuda,
            'cantidad' => $tickets->count(),
            'tono' => $tono,
            'href' => $href,
            'items' => array_values($tickets
                ->take(self::MOSTRADOS)
                ->map(fn (Vuelta $vuelta): array => [
                    'texto' => $vuelta->tracto->placa.' · '.$vuelta->codigo,
                    'detalle' => $detalle($vuelta),
                    'href' => $enlace !== null
                        ? $enlace($vuelta)
                        : ($verExpediente ? route('vueltas.show', $vuelta) : $href),
                ])
                ->all()),
        ];
    }

    /**
     * Los tickets abiertos, cargados una sola vez para todos los pendientes.
     *
     * @return Collection<int, Vuelta>
     */
    private function abiertas(): Collection
    {
        return $this->abiertas ??= Vuelta::query()
            ->whereIn('estado', [EstadoVuelta::Programada, EstadoVuelta::EnCurso])
            ->with([
                'tracto:id,placa',
                'conductor:id,nombres,apellidos',
                'paradas.viajes:id,parada_vuelta_id,numero_gr,origen,destino,peso,unidad_peso,fecha_traslado',
                'revision',
            ])
            ->orderBy('inicio')
            ->orderBy('id')
            ->get();
    }

    /**
     * Combustible y viáticos de los tickets abiertos, solo si algún pendiente
     * los necesita.
     *
     * @return array<int, GastosTicket>
     */
    private function gastos(): array
    {
        return $this->gastos ??= $this->gastosDelTicket->de($this->abiertas());
    }

    private function llegada(Vuelta $vuelta): ?CarbonImmutable
    {
        $ultima = $vuelta->paradas->last(fn ($parada): bool => $parada->viajes->isNotEmpty());

        return $ultima === null ? null : $vuelta->llegadaEstimada($ultima);
    }
}
