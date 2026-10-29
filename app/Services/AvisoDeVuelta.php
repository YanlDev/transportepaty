<?php

namespace App\Services;

use App\Enums\SentidoTramo;
use App\Enums\TipoCarga;
use App\Models\AreaAviso;
use App\Models\ParadaVuelta;
use App\Models\Vuelta;
use Illuminate\Database\Eloquent\Collection as ColeccionEloquent;
use Illuminate\Support\Collection;

/**
 * Los avisos por WhatsApp de una vuelta: al conductor, con su ticket y todo
 * el recorrido, y a las áreas de la casa (abastecimiento, facturación,
 * centro de control…), que además ven lo que la pizarra le asigna de
 * combustible y viáticos.
 *
 * El texto se arma acá y no en el navegador para que sea uno solo: es la
 * constancia de lo que se avisó. Los números se normalizan con
 * `ContactoWhatsapp`.
 */
class AvisoDeVuelta
{
    /** @var Collection<int, AreaAviso>|null */
    private ?Collection $areas = null;

    public function __construct(
        private readonly ContactoWhatsapp $contacto,
        private readonly DotacionVuelta $dotacion,
    ) {}

    public function mensajeParaConductor(Vuelta $vuelta): string
    {
        return implode("\n", [
            '*PROGRAMACIÓN — TRANSPORTES PATY*',
            ...$this->cabecera($vuelta),
            '',
            ...$this->recorrido($vuelta),
            '',
            '*No inicies el viaje sin documentación validada.*',
        ]);
    }

    /**
     * Lo mismo que el conductor, más lo que le toca según la pizarra: con
     * eso abastecimiento programa el QR y prepara los viáticos sin preguntar.
     */
    public function mensajeParaArea(Vuelta $vuelta): string
    {
        return implode("\n", array_filter([
            '*TICKET PROGRAMADO*',
            ...$this->cabecera($vuelta),
            '',
            ...$this->recorrido($vuelta),
            ...$this->dotacionEnTexto($vuelta),
        ], fn (?string $linea): bool => $linea !== null));
    }

    /**
     * A quién mandarle el aviso de la vuelta: el celular del conductor y su
     * alterno, sin repetidos.
     *
     * @return list<array{etiqueta: string, numero: string}>
     */
    public function destinatarios(Vuelta $vuelta): array
    {
        $destinatarios = [];

        foreach (['Conductor' => $vuelta->conductor?->telefono, 'Alterno' => $vuelta->conductor?->telefono_alterno] as $etiqueta => $telefono) {
            $numero = $this->contacto->numeroWhatsapp($telefono);

            if ($numero !== null && ! in_array($numero, array_column($destinatarios, 'numero'), true)) {
                $destinatarios[] = ['etiqueta' => $etiqueta, 'numero' => $numero];
            }
        }

        return $destinatarios;
    }

    /**
     * Las áreas activas con número válido y el aviso armado para cada una.
     *
     * @return list<array{id: int, area: string, numero: string, mensaje: string}>
     */
    public function avisosDeArea(Vuelta $vuelta): array
    {
        $mensaje = null;
        $avisos = [];

        foreach ($this->areas() as $area) {
            $numero = $this->contacto->numeroWhatsapp($area->numero);

            if ($numero === null) {
                continue;
            }

            $avisos[] = [
                'id' => $area->id,
                'area' => $area->nombre,
                'numero' => $numero,
                'mensaje' => $mensaje ??= $this->mensajeParaArea($vuelta),
            ];
        }

        return $avisos;
    }

    public function numeroWhatsapp(?string $telefono): ?string
    {
        return $this->contacto->numeroWhatsapp($telefono);
    }

    /**
     * @return list<string>
     */
    private function cabecera(Vuelta $vuelta): array
    {
        $conductor = $vuelta->conductor;

        return array_values(array_filter([
            "Ticket: *{$vuelta->codigo}*",
            $vuelta->inicio ? "Salida: {$vuelta->inicio->format('d/m/Y')}" : null,
            "Unidad: *{$vuelta->tracto->placa}*".($vuelta->carreta ? " · carreta {$vuelta->carreta->placa}" : ''),
            $conductor ? "Conductor: {$conductor->nombres} {$conductor->apellidos}" : null,
        ]));
    }

    /**
     * @return list<string>
     */
    private function recorrido(Vuelta $vuelta): array
    {
        $lineas = [];

        foreach ([[SentidoTramo::Ida, '*Ida*'], [SentidoTramo::Regreso, '*Vuelta*']] as [$sentido, $titulo]) {
            $paradas = $vuelta->paradasDe($sentido);

            if ($paradas->isEmpty()) {
                continue;
            }

            $lineas[] = $titulo;

            foreach ($paradas as $parada) {
                $lineas[] = '• '.$this->parada($parada);
            }
        }

        return $lineas;
    }

    private function parada(ParadaVuelta $parada): string
    {
        $desde = $parada->lugar_origen ?? $parada->zona_origen?->label() ?? '¿?';
        $hasta = $parada->lugar_destino ?? $parada->zona_destino?->label() ?? '¿?';
        $carga = $parada->tipo_carga === TipoCarga::Vacio ? 'vacío' : $parada->tipo_carga->label();

        return "{$desde} → {$hasta} · {$carga}".($parada->cliente ? " · {$parada->cliente}" : '');
    }

    /**
     * @return list<string|null>
     */
    private function dotacionEnTexto(Vuelta $vuelta): array
    {
        $dotacion = $this->dotacion->de($vuelta, new ColeccionEloquent);

        if ($dotacion['combustible'] === [] && $dotacion['viaticos'] === []) {
            return [];
        }

        $galones = fn (?float $valor): string => $valor === null
            ? 'full'
            : rtrim(rtrim(number_format($valor, 1, '.', ''), '0'), '.').' gal';

        // El total del tramo es el tope; los puntos, una sugerencia de dónde
        // cargar.
        $combustible = array_map(
            fn (array $tramo): string => ($tramo['sentido'] === SentidoTramo::Ida->value ? 'ida' : 'vuelta')
                .' '.$galones($tramo['previsto'])
                .' ('.implode(' · ', array_map(fn (array $punto): string => $punto['punto'].' '.$galones($punto['previsto']), $tramo['puntos'])).')',
            $dotacion['tramos'],
        );
        $viaticos = array_map(
            fn (array $viatico): string => "{$viatico['concepto']} S/ ".number_format($viatico['monto'], 2),
            $dotacion['viaticos'],
        );

        return [
            '',
            $combustible === [] ? null : 'Combustible: '.implode(' · ', $combustible),
            $viaticos === [] ? null : 'Viáticos: '.implode(' · ', $viaticos),
        ];
    }

    /**
     * @return Collection<int, AreaAviso>
     */
    private function areas(): Collection
    {
        return $this->areas ??= AreaAviso::query()->activas()->get();
    }
}
