<?php

namespace App\Services;

use App\Enums\EstadoVuelta;
use App\Enums\OrigenVuelta;
use App\Enums\SentidoTramo;
use App\Enums\TipoCarga;
use App\Enums\TipoVuelta;
use App\Enums\ZonaRuta;
use App\Models\ParadaVuelta;
use App\Models\User;
use App\Models\Viaje;
use App\Models\Vuelta;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Pone una GR en un ticket, siempre por decisión de una persona.
 *
 * Al registrarse, la GR queda en la bandeja «GR por asignar»: el sistema solo
 * sugiere el ticket abierto de su unidad y la parada que coincide en
 * trayecto y carga, y alguien confirma. Si la GR no calza con lo programado,
 * esa persona decide si entra como parada «no programada». Lo que no va en
 * ningún ticket se marca «sin ticket» con su motivo.
 *
 * Las zonas se deducen de la dirección solo para sugerir: nunca deciden.
 */
class AsignadorVueltas
{
    /**
     * Los tickets abiertos de la unidad de la GR: a esos se puede asignar.
     *
     * @return Collection<int, Vuelta>
     */
    public function ticketsPara(Viaje $guia): Collection
    {
        if ($guia->tracto_id === null) {
            return new Collection;
        }

        return Vuelta::query()
            ->where('tracto_id', $guia->tracto_id)
            ->whereIn('estado', [EstadoVuelta::Programada, EstadoVuelta::EnCurso])
            ->with(['paradas' => fn ($query) => $query->withCount('viajes')])
            ->orderBy('inicio')
            ->orderBy('id')
            ->get();
    }

    /**
     * Lo que el sistema propone: el ticket (el único abierto o, si hay más,
     * el último que ya salió para la fecha de la GR), la parada que coincide
     * en trayecto y carga, y si no hay parada, en qué tramo iría.
     *
     * @param  Collection<int, Vuelta>  $tickets  los de `ticketsPara()`
     * @return array{vuelta_id: int|null, parada_id: int|null, sentido: string}
     */
    public function sugerencia(Viaje $guia, Collection $tickets): array
    {
        $origen = ZonaRuta::desdeDireccion($guia->origen);
        $destino = ZonaRuta::desdeDireccion($guia->destino);

        $vuelta = $tickets->count() === 1
            ? $tickets->first()
            : $tickets
                ->filter(fn (Vuelta $vuelta): bool => $vuelta->inicio === null || $vuelta->inicio->lte($guia->fecha_traslado->copy()->addDay()))
                ->last();

        if ($vuelta === null) {
            return ['vuelta_id' => null, 'parada_id' => null, 'sentido' => SentidoTramo::entre($origen, $destino, false)->value];
        }

        $delTrayecto = $vuelta->paradas
            // La subida con la carga del ticket anterior ya tiene su GR.
            ->filter(fn (ParadaVuelta $parada): bool => ! $parada->esContinuacion()
                && $parada->zona_origen === $origen
                && $parada->zona_destino === $destino);
        $conCarga = $delTrayecto->reject(fn (ParadaVuelta $parada): bool => $parada->esVacio());

        $parada = $conCarga->first(fn (ParadaVuelta $parada): bool => $parada->tipo_carga === $guia->tipo_carga)
            ?? $conCarga->first(fn (ParadaVuelta $parada): bool => (int) $parada->viajes_count === 0)
            // Se programó vacía y consiguió carga: la GR reemplaza ese vacío.
            ?? $delTrayecto->first(fn (ParadaVuelta $parada): bool => $this->esVacioReemplazable($parada, $guia));

        $yaEmpezoElRegreso = $vuelta->paradas
            ->contains(fn (ParadaVuelta $parada): bool => $parada->sentido === SentidoTramo::Regreso && (int) $parada->viajes_count > 0);

        return [
            'vuelta_id' => $vuelta->id,
            'parada_id' => $parada?->id,
            'sentido' => ($parada->sentido ?? SentidoTramo::entre($origen, $destino, $yaEmpezoElRegreso))->value,
        ];
    }

    /**
     * Asigna la GR al ticket: a una parada suya o, si no se elige ninguna,
     * a una parada nueva «no programada» en el tramo indicado. Quien llama
     * ya validó que el ticket es de la misma unidad y está abierto.
     */
    public function asignar(Viaje $guia, Vuelta $vuelta, ?ParadaVuelta $parada, SentidoTramo $sentido, User $usuario): ParadaVuelta
    {
        return DB::transaction(function () use ($guia, $vuelta, $parada, $sentido, $usuario): ParadaVuelta {
            if ($parada === null) {
                $parada = $this->paradaNoProgramada($vuelta, $guia, $sentido);
            } elseif ($parada->tipo_carga !== $guia->tipo_carga && ! $parada->viajes()->exists()) {
                // Se programó «con carga» sin saber cuál (la subida cargada
                // de Juliaca a la mina) o vacía y consiguió carga: manda la
                // que dice la GR.
                $parada->update(['tipo_carga' => $guia->tipo_carga]);
            }

            $esLaPrimera = ! $parada->viajes()->exists();

            $guia->forceFill([
                'parada_vuelta_id' => $parada->id,
                'asignada_por' => $usuario->id,
                'asignada_at' => now(),
                'sin_ticket_at' => null,
                'sin_ticket_por' => null,
                'motivo_sin_ticket' => null,
            ])->save();

            $this->alLlegarLaGuia($parada, $vuelta, $guia, $esLaPrimera);

            return $parada;
        });
    }

    /**
     * Saca la GR de su ticket y la devuelve a la bandeja. Si estaba sola en
     * una parada no programada, la parada se va con ella; si el ticket se
     * queda sin GR, vuelve a «por salir».
     */
    public function quitar(Viaje $guia): void
    {
        DB::transaction(function () use ($guia): void {
            $parada = $guia->loadMissing('parada.vuelta')->parada;

            $guia->forceFill(['parada_vuelta_id' => null, 'asignada_por' => null, 'asignada_at' => null])->save();

            if ($parada === null) {
                return;
            }

            $vuelta = $parada->vuelta;

            if ($parada->fuera_de_programa && ! $parada->viajes()->exists()) {
                $parada->delete();
            }

            if ($vuelta->estado === EstadoVuelta::EnCurso && ! $vuelta->viajes()->exists()) {
                $vuelta->update(['estado' => EstadoVuelta::Programada]);
            }
        });
    }

    public function marcarSinTicket(Viaje $guia, string $motivo, User $usuario): void
    {
        $guia->forceFill([
            'sin_ticket_at' => now(),
            'sin_ticket_por' => $usuario->id,
            'motivo_sin_ticket' => $motivo,
        ])->save();
    }

    public function devolverABandeja(Viaje $guia): void
    {
        $guia->forceFill(['sin_ticket_at' => null, 'sin_ticket_por' => null, 'motivo_sin_ticket' => null])->save();
    }

    /**
     * Cierra el ticket en el día de su última GR (o de su salida, si no tuvo).
     */
    public function cerrar(Vuelta $vuelta): void
    {
        $ultimaGuia = $vuelta->viajes()->max('fecha_traslado');

        $vuelta->update([
            'estado' => EstadoVuelta::Cerrada,
            'fin' => $vuelta->llegada_el ?? $ultimaGuia ?? $vuelta->inicio,
        ]);
    }

    private function paradaNoProgramada(Vuelta $vuelta, Viaje $guia, SentidoTramo $sentido): ParadaVuelta
    {
        // Va al final de su tramo: las de ida antes que las de regreso.
        $paradas = $vuelta->paradas()->get();
        $ultimaDelTramo = $paradas->where('sentido', $sentido)->max('orden');
        $orden = $ultimaDelTramo === null
            ? ($sentido === SentidoTramo::Ida ? 1 : (int) $paradas->max('orden') + 1)
            : (int) $ultimaDelTramo + 1;

        $vuelta->paradas()->where('orden', '>=', $orden)->increment('orden');

        return $vuelta->paradas()->create([
            'sentido' => $sentido,
            'orden' => $orden,
            'zona_origen' => ZonaRuta::desdeDireccion($guia->origen),
            'zona_destino' => ZonaRuta::desdeDireccion($guia->destino),
            'lugar_origen' => $this->lugar($guia->origen),
            'lugar_destino' => $this->lugar($guia->destino),
            'tipo_carga' => $guia->tipo_carga,
            'cliente_id' => $guia->cliente_id,
            'cliente' => $guia->cliente,
            'fecha' => $guia->fecha_traslado,
            'fuera_de_programa' => true,
        ]);
    }

    /**
     * Un tramo vacío del mismo trayecto que la GR, todavía sin GR: la unidad
     * consiguió carga y esa parada pasa a llevarla.
     */
    public function esVacioReemplazable(ParadaVuelta $parada, Viaje $guia): bool
    {
        return $parada->esVacio()
            && ! $parada->esContinuacion()
            && $parada->zona_origen === ZonaRuta::desdeDireccion($guia->origen)
            && $parada->zona_destino === ZonaRuta::desdeDireccion($guia->destino)
            && (int) ($parada->viajes_count ?? $parada->viajes()->count()) === 0;
    }

    /**
     * Lo que la GR le dice a la parada y al ticket: el lugar real, la fecha
     * y que la unidad ya salió. El lugar de la primera GR manda sobre el que
     * puso la ruta al programar («Arequipa» pasa a ser «Uraca»); las
     * siguientes ya no lo cambian.
     */
    private function alLlegarLaGuia(ParadaVuelta $parada, Vuelta $vuelta, Viaje $guia, bool $esLaPrimera): void
    {
        $origen = $this->lugar($guia->origen);
        $destino = $this->lugar($guia->destino);

        $parada->update([
            'lugar_origen' => $esLaPrimera ? ($origen ?? $parada->lugar_origen) : ($parada->lugar_origen ?? $origen),
            'lugar_destino' => $esLaPrimera ? ($destino ?? $parada->lugar_destino) : ($parada->lugar_destino ?? $destino),
            'cliente_id' => $parada->cliente_id ?? $guia->cliente_id,
            'cliente' => $parada->cliente ?? $guia->cliente,
            'fecha' => $parada->fecha ?? $guia->fecha_traslado,
        ]);

        $salio = $vuelta->estado === EstadoVuelta::Programada && $vuelta->origen === OrigenVuelta::Programacion;
        $cambios = [];

        if ($vuelta->estado === EstadoVuelta::Programada) {
            $cambios['estado'] = EstadoVuelta::EnCurso;
        }

        if (! in_array($guia->tipo_carga, [TipoCarga::Particular, TipoCarga::Vacio], true) && $vuelta->tipo !== TipoVuelta::Mina) {
            $cambios['tipo'] = TipoVuelta::Mina;
        }

        if ($cambios !== []) {
            $vuelta->update($cambios);
        }

        if ($salio) {
            app(AlertasDelTicket::class)->salio($vuelta);
        }
    }

    /**
     * El distrito de una dirección de GR («… - PARACAS - PISCO - ICA» →
     * Paracas). Las GR terminan siempre en distrito, provincia y departamento.
     */
    public function lugar(string $direccion): ?string
    {
        $partes = array_map('trim', explode(' - ', $direccion));

        if (count($partes) < 3) {
            return null;
        }

        return Str::limit(Str::title(mb_strtolower($partes[count($partes) - 3])), 100, '');
    }
}
