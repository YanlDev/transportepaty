<?php

namespace App\Services;

use App\Enums\EstadoVuelta;
use App\Enums\OrigenVuelta;
use App\Enums\SentidoTramo;
use App\Enums\TipoCarga;
use App\Enums\TipoVuelta;
use App\Enums\ZonaRuta;
use App\Models\ParadaVuelta;
use App\Models\Viaje;
use App\Models\Vuelta;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Pone cada GR en la vuelta de su unidad, en el momento en que se registra.
 *
 * - Si la unidad tiene una vuelta **programada** vigente para esa fecha, la
 *   GR va a la parada que coincide en origen, destino y carga. Si ninguna
 *   coincide, entra igual como parada «no programada»: lo planeado no se
 *   corrige solo, se muestra la diferencia.
 * - Si no hay vuelta programada, la GR sigue armando el historial con la
 *   misma regla de siempre: las GR de una misma carga se juntan, donde la
 *   unidad no arranca en la zona en que terminó hay un trayecto vacío, y la
 *   vuelta se cierra cuando la unidad vuelve a subir después de haber bajado.
 *
 * La reconstrucción del historial (`ReconstructorVueltas`) es esto mismo
 * aplicado GR por GR, así que las dos nunca dan resultados distintos.
 */
class AsignadorVueltas
{
    /** Cuántos días puede haber entre dos GR de la misma parada. */
    private const DIAS_MISMA_PARADA = 1;

    public function asignar(Viaje $guia): ?ParadaVuelta
    {
        // Recién creada puede venir sin los valores que pone la base (el
        // importador deja que el tipo de carga tome el suyo por defecto).
        $guia = $guia->fresh() ?? $guia;

        if ($guia->tracto_id === null || $guia->parada_vuelta_id !== null || $guia->estaAnulada()) {
            return null;
        }

        return DB::transaction(function () use ($guia): ParadaVuelta {
            $vuelta = $this->vueltaVigente($guia);
            $origen = ZonaRuta::desdeDireccion($guia->origen);
            $destino = ZonaRuta::desdeDireccion($guia->destino);

            $parada = $vuelta?->origen === OrigenVuelta::Programacion
                ? $this->enProgramada($vuelta, $guia, $origen, $destino)
                : $this->enHistorial($vuelta, $guia, $origen, $destino);

            $guia->forceFill(['parada_vuelta_id' => $parada->id])->saveQuietly();
            $this->alLlegarLaGuia($parada, $guia);

            return $parada;
        });
    }

    /**
     * La vuelta en la que cae una GR por su fecha: la última que arrancó
     * hasta ese día. Un día de tolerancia, porque la GR de la salida puede
     * salir fechada la víspera.
     */
    private function vueltaVigente(Viaje $guia): ?Vuelta
    {
        return Vuelta::query()
            ->where('tracto_id', $guia->tracto_id)
            ->where('estado', '!=', EstadoVuelta::Anulada)
            ->where(fn ($query) => $query
                ->whereNull('inicio')
                ->orWhereDate('inicio', '<=', $guia->fecha_traslado->copy()->addDay()))
            ->orderByDesc('inicio')
            ->orderByDesc('id')
            ->first();
    }

    private function enProgramada(Vuelta $vuelta, Viaje $guia, ?ZonaRuta $origen, ?ZonaRuta $destino): ParadaVuelta
    {
        $coincide = $vuelta->paradas()
            ->where('tipo_carga', $guia->tipo_carga)
            ->where('zona_origen', $origen)
            ->where('zona_destino', $destino)
            ->get()
            ->first(fn (ParadaVuelta $parada): bool => $this->admiteLaGuia($parada, $guia));

        if ($coincide !== null) {
            return $coincide;
        }

        return $this->agregarParada($vuelta, $guia, $origen, $destino, fueraDePrograma: true);
    }

    private function enHistorial(?Vuelta $vuelta, Viaje $guia, ?ZonaRuta $origen, ?ZonaRuta $destino): ParadaVuelta
    {
        $ultima = $vuelta?->paradas()->reorder()->orderByDesc('orden')->first();

        if ($ultima !== null
            && $ultima->zona_origen === $origen
            && $ultima->zona_destino === $destino
            && $ultima->tipo_carga === $guia->tipo_carga
            && $this->admiteLaGuia($ultima, $guia)) {
            return $ultima;
        }

        // Donde la unidad no arranca en la zona en que terminó, hizo ese
        // trayecto vacía.
        if ($ultima?->zona_destino !== null && $origen !== null && $ultima->zona_destino !== $origen) {
            $vuelta = $this->vueltaParaElTramo($vuelta, $guia, SentidoTramo::hacia($origen));
            $this->crearParada($vuelta, [
                'sentido' => SentidoTramo::hacia($origen),
                'zona_origen' => $ultima->zona_destino,
                'zona_destino' => $origen,
                'lugar_origen' => $ultima->lugar_destino,
                'lugar_destino' => $this->lugar($guia->origen),
                'tipo_carga' => TipoCarga::Vacio,
            ]);
        }

        $sentido = $this->sentidoDe($vuelta, $destino);
        $vuelta = $this->vueltaParaElTramo($vuelta, $guia, $sentido);

        return $this->agregarParada($vuelta, $guia, $origen, $destino, sentido: $sentido);
    }

    /**
     * La misma vuelta, o una nueva si la unidad vuelve a subir después de
     * haber bajado: ahí se cierra la anterior.
     */
    private function vueltaParaElTramo(?Vuelta $vuelta, Viaje $guia, SentidoTramo $sentido): Vuelta
    {
        $empiezaOtra = $vuelta === null
            || ($sentido === SentidoTramo::Ida && $vuelta->paradas()->where('sentido', SentidoTramo::Regreso)->exists());

        if (! $empiezaOtra) {
            return $vuelta;
        }

        if ($vuelta !== null) {
            $this->cerrar($vuelta);
        }

        return Vuelta::query()->create([
            'tracto_id' => $guia->tracto_id,
            'carreta_id' => $guia->carreta_id,
            'conductor_id' => $guia->conductor_id,
            'tipo' => TipoVuelta::Particular,
            'estado' => EstadoVuelta::EnCurso,
            'origen' => OrigenVuelta::Historial,
        ]);
    }

    /**
     * Cierra una vuelta en el día de su última GR.
     */
    public function cerrar(Vuelta $vuelta): void
    {
        $ultimaGuia = $vuelta->viajes()->max('fecha_traslado');

        $vuelta->update([
            'estado' => EstadoVuelta::Cerrada,
            'fin' => $ultimaGuia ?? $vuelta->inicio,
        ]);
    }

    private function agregarParada(
        Vuelta $vuelta,
        Viaje $guia,
        ?ZonaRuta $origen,
        ?ZonaRuta $destino,
        ?SentidoTramo $sentido = null,
        bool $fueraDePrograma = false,
    ): ParadaVuelta {
        return $this->crearParada($vuelta, [
            'sentido' => $sentido ?? $this->sentidoDe($vuelta, $destino),
            'zona_origen' => $origen,
            'zona_destino' => $destino,
            'lugar_origen' => $this->lugar($guia->origen),
            'lugar_destino' => $this->lugar($guia->destino),
            'tipo_carga' => $guia->tipo_carga,
            'cliente_id' => $guia->cliente_id,
            'cliente' => $guia->cliente,
            'fecha' => $guia->fecha_traslado,
            'fuera_de_programa' => $fueraDePrograma,
        ]);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function crearParada(Vuelta $vuelta, array $datos): ParadaVuelta
    {
        $orden = (int) $vuelta->paradas()->reorder()->max('orden') + 1;

        return $vuelta->paradas()->create([...$datos, 'orden' => $orden]);
    }

    /**
     * De la ida si termina en la sierra y del regreso si termina en la costa.
     * Sin zona reconocida se queda en el tramo en que va la vuelta.
     */
    private function sentidoDe(?Vuelta $vuelta, ?ZonaRuta $destino): SentidoTramo
    {
        if ($destino !== null) {
            return SentidoTramo::hacia($destino);
        }

        return $vuelta?->paradas()->where('sentido', SentidoTramo::Regreso)->exists()
            ? SentidoTramo::Regreso
            : SentidoTramo::Ida;
    }

    /**
     * Una parada recibe la GR si todavía no tiene ninguna o si la suya es de
     * la misma carga (los materiales salen con varias GR el mismo día).
     */
    private function admiteLaGuia(ParadaVuelta $parada, Viaje $guia): bool
    {
        $ultima = $parada->viajes()->max('fecha_traslado');

        if ($ultima === null) {
            return ! $parada->esVacio();
        }

        return Carbon::parse($ultima)->diffInDays($guia->fecha_traslado, absolute: true) <= self::DIAS_MISMA_PARADA;
    }

    /**
     * Lo que la GR le dice a la parada y a la vuelta: el lugar real, la fecha
     * en que se hizo y que la vuelta ya está andando.
     */
    private function alLlegarLaGuia(ParadaVuelta $parada, Viaje $guia): void
    {
        $parada->update([
            'lugar_origen' => $parada->lugar_origen ?? $this->lugar($guia->origen),
            'lugar_destino' => $parada->lugar_destino ?? $this->lugar($guia->destino),
            'cliente_id' => $parada->cliente_id ?? $guia->cliente_id,
            'cliente' => $parada->cliente ?? $guia->cliente,
            'fecha' => $parada->fecha ?? $guia->fecha_traslado,
        ]);

        $vuelta = $parada->vuelta;
        $cambios = [];

        if ($vuelta->estado === EstadoVuelta::Programada) {
            $cambios['estado'] = EstadoVuelta::EnCurso;
        }

        if ($guia->tipo_carga !== TipoCarga::Particular && $vuelta->tipo !== TipoVuelta::Mina) {
            $cambios['tipo'] = TipoVuelta::Mina;
        }

        // La fecha de salida de una programada es la planeada; la del
        // historial es la de su primera GR.
        if ($vuelta->origen === OrigenVuelta::Historial
            && ($vuelta->inicio === null || $guia->fecha_traslado->lt($vuelta->inicio))) {
            $cambios['inicio'] = $guia->fecha_traslado;
        }

        if ($cambios !== []) {
            $vuelta->update($cambios);
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
