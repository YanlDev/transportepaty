<?php

namespace App\Services;

use App\Enums\EstadoEnvio;
use App\Enums\EstadoVuelta;
use App\Enums\ResultadoTaller;
use App\Jobs\EnviarAvisoWhatsapp;
use App\Models\AreaAviso;
use App\Models\EnvioWhatsapp;
use App\Models\User;
use App\Models\Vuelta;
use App\Services\Imagenes\ImagenesDeAviso;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Los avisos de un ticket por el número de la empresa: se anotan, se ponen
 * en la cola y un worker los manda. Así el botón responde al instante y un
 * envío lento de WhatsApp no ocupa la app de nadie.
 */
class AvisosPorWhatsapp
{
    public function __construct(
        private readonly ImagenesDeAviso $imagenes,
        private readonly WhatsappServicio $whatsapp,
        private readonly AvisoDeVuelta $avisoDeVuelta,
    ) {}

    /** Anota el aviso de una vuelta al conductor y lo pone en la cola. */
    public function encolarVueltaAlConductor(Vuelta $vuelta, string $numero, string $destino, ?User $usuario): EnvioWhatsapp
    {
        return $this->encolar([
            'vuelta_id' => $vuelta->id,
            'tipo' => EnvioWhatsapp::TIPO_CONDUCTOR,
            'destino' => $destino,
            'numero' => $numero,
            'enviado_por' => $usuario?->id,
        ]);
    }

    /** Anota el aviso de una vuelta a un área, a su número guardado. */
    public function encolarVueltaAlArea(Vuelta $vuelta, AreaAviso $area, string $numero, ?User $usuario): EnvioWhatsapp
    {
        return $this->encolar([
            'vuelta_id' => $vuelta->id,
            'area_aviso_id' => $area->id,
            'tipo' => EnvioWhatsapp::TIPO_AREA,
            'destino' => $area->nombre,
            'numero' => $numero,
            'enviado_por' => $usuario?->id,
        ]);
    }

    /** Anota el recordatorio de las unidades sin GR a un área y lo encola. */
    public function encolarRecordatorio(AreaAviso $area, string $numero): EnvioWhatsapp
    {
        return $this->encolar([
            'area_aviso_id' => $area->id,
            'tipo' => EnvioWhatsapp::TIPO_RECORDATORIO,
            'destino' => $area->nombre,
            'numero' => $numero,
        ]);
    }

    /**
     * Los tickets que ya debían salir (hasta hoy) y siguen sin GR, por
     * placa. Los que taller dio por no aptos no van: esa unidad no sale.
     *
     * @return Collection<int, array{placa: string, detalle: string}>
     */
    public function unidadesSinGr(CarbonImmutable $hoy): Collection
    {
        return Vuelta::query()
            ->where('estado', EstadoVuelta::Programada)
            ->whereDate('inicio', '<=', $hoy->toDateString())
            ->with(['tracto:id,placa', 'conductor:id,nombres,apellidos', 'paradas', 'revision'])
            ->get()
            ->reject(fn (Vuelta $vuelta): bool => $vuelta->revision?->resultado === ResultadoTaller::NoApto)
            ->sortBy(fn (Vuelta $vuelta): string => $vuelta->tracto->placa)
            ->map(fn (Vuelta $vuelta): array => [
                'placa' => $vuelta->tracto->placa,
                'detalle' => implode(' · ', array_filter([
                    $vuelta->codigo,
                    $this->primerTramo($vuelta),
                    $vuelta->conductor ? "{$vuelta->conductor->nombres} {$vuelta->conductor->apellidos}" : null,
                ])),
            ])
            ->values();
    }

    private function primerTramo(Vuelta $vuelta): ?string
    {
        $parada = $vuelta->paradas->first();

        return $parada === null
            ? null
            : ($parada->lugar_origen ?? $parada->zona_origen?->label() ?? '¿?').' → '.($parada->lugar_destino ?? $parada->zona_destino?->label() ?? '¿?');
    }

    /**
     * Lo que hace el worker: arma la imagen con los datos de ese momento, la
     * manda y anota el id que le dio WhatsApp. Si falla, la excepción sube
     * para que la cola reintente.
     */
    public function mandar(EnvioWhatsapp $envio): void
    {
        if ($envio->tipo === EnvioWhatsapp::TIPO_RECORDATORIO) {
            $this->mandarRecordatorio($envio);

            return;
        }

        if ($envio->vuelta_id !== null) {
            $this->mandarDeVuelta($envio);

            return;
        }

        throw new RuntimeException('Este aviso era de la pantalla de salidas de antes: ya no se manda.');
    }

    /**
     * Las unidades se vuelven a mirar al mandar: si justo se cargó una GR,
     * esa unidad ya no va; si ya no queda ninguna, no se manda nada.
     */
    private function mandarRecordatorio(EnvioWhatsapp $envio): void
    {
        $hoy = RelojOperativo::fechaDeHoy();
        $sinGr = $this->unidadesSinGr($hoy);

        if ($sinGr->isEmpty()) {
            $envio->update(['estado' => EstadoEnvio::Fallido, 'error' => 'No hizo falta: ya todas las unidades tienen GR.']);

            return;
        }

        $mensajeId = $this->whatsapp->enviar(
            $envio->numero,
            sprintf('%d %s sin GR', $sinGr->count(), $sinGr->count() === 1 ? 'unidad' : 'unidades'),
            $this->imagenes->recordatorioSinGr($hoy, $sinGr),
        );

        $envio->update(['estado' => EstadoEnvio::Enviado, 'mensaje_id' => $mensajeId, 'error' => null, 'enviado_at' => now()]);
    }

    /**
     * El aviso de una vuelta va como texto: el recorrido de ida y regreso se
     * lee mejor así que en una imagen. Se arma con los datos de ese momento.
     */
    private function mandarDeVuelta(EnvioWhatsapp $envio): void
    {
        $vuelta = $envio->vuelta ?? throw new RuntimeException('La vuelta ya no existe.');
        $vuelta->loadMissing(['tracto', 'carreta', 'conductor', 'paradas.viajes']);

        $texto = $envio->tipo === EnvioWhatsapp::TIPO_AREA
            ? $this->avisoDeVuelta->mensajeParaArea($vuelta)
            : $this->avisoDeVuelta->mensajeParaConductor($vuelta);

        $envio->update([
            'estado' => EstadoEnvio::Enviado,
            'mensaje_id' => $this->whatsapp->enviar($envio->numero, $texto),
            'error' => null,
            'enviado_at' => now(),
        ]);
    }

    /** Cuando la cola se rinde después de los reintentos. */
    public function marcarFallido(EnvioWhatsapp $envio, string $error): void
    {
        $envio->update(['estado' => EstadoEnvio::Fallido, 'error' => $error]);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function encolar(array $datos): EnvioWhatsapp
    {
        $envio = EnvioWhatsapp::query()->create([...$datos, 'estado' => EstadoEnvio::Pendiente]);

        EnviarAvisoWhatsapp::dispatch($envio);

        return $envio;
    }
}
