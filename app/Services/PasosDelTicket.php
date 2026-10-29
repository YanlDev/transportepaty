<?php

namespace App\Services;

use App\Enums\EstadoVuelta;
use App\Enums\OrigenVuelta;
use App\Enums\ResultadoTaller;
use App\Enums\SentidoTramo;
use App\Models\HistorialCambio;
use App\Models\ParadaVuelta;
use App\Models\Viaje;
use App\Models\Vuelta;

/**
 * El circuito del ticket como una fila de pasos: programado, OK de taller,
 * abastecimiento de ida, en ida, regreso definido, abastecimiento de vuelta,
 * en vuelta, llegó y cerrado. Cada paso dice si ya se hizo, quién y cuándo.
 *
 * Los pasos se miran cada uno por su lado: si la GR de ida llegó sin que se
 * registrara el combustible, el paso de combustible sigue pendiente y se ve.
 *
 * @phpstan-type Paso array{clave: string, titulo: string, estado: 'hecho'|'parcial'|'pendiente'|'alerta', detalle: string|null, fecha: string|null, por: string|null}
 */
class PasosDelTicket
{
    /**
     * El ticket tiene que venir con `paradas.viajes` (con fecha y número) y
     * `revisiones.usuario`.
     *
     * @param  array{tramos: list<array{sentido: string, previsto: float|null, registrado: float, falta: float|null}>, viatico_depositado: float, viatico_pendiente: float}  $gastos
     * @return array{pasos: list<Paso>, actual: string|null}
     */
    public function de(Vuelta $vuelta, array $gastos): array
    {
        $pasos = [
            $this->programado($vuelta),
            $this->taller($vuelta),
            $this->abastecimiento($gastos, SentidoTramo::Ida, conViaticos: true),
            $this->enRuta($vuelta, SentidoTramo::Ida),
            $this->regreso($vuelta),
            $this->abastecimiento($gastos, SentidoTramo::Regreso, conViaticos: false),
            $this->enRuta($vuelta, SentidoTramo::Regreso),
            $this->llego($vuelta),
            $this->cerrado($vuelta),
        ];

        // El paso en el que está: el primero sin terminar. Uno cerrado ya no
        // espera nada.
        $actual = $vuelta->estado === EstadoVuelta::Cerrada
            ? null
            : collect($pasos)->first(fn (array $paso): bool => $paso['estado'] !== 'hecho')['clave'] ?? null;

        return ['pasos' => $pasos, 'actual' => $actual];
    }

    /**
     * @return Paso
     */
    private function programado(Vuelta $vuelta): array
    {
        $creado = HistorialCambio::query()
            ->where('modelo', class_basename(Vuelta::class))
            ->where('modelo_id', $vuelta->id)
            ->where('accion', 'creado')
            ->with('usuario:id,name')
            ->first();

        return $this->paso('programado', 'Programado', 'hecho',
            $vuelta->origen === OrigenVuelta::Programacion
                ? 'Sale el '.$vuelta->inicio?->format('d/m')
                : 'Se armó solo, desde la GR',
            $creado?->created_at->format('Y-m-d H:i'),
            $creado?->usuario?->name,
        );
    }

    /**
     * @return Paso
     */
    private function taller(Vuelta $vuelta): array
    {
        $revision = $vuelta->revisiones->first();

        if ($revision === null) {
            return $this->paso('taller', 'OK de taller', 'pendiente', 'Esperando la revisión');
        }

        if ($revision->resultado === ResultadoTaller::NoApto) {
            return $this->paso('taller', 'OK de taller', 'alerta',
                trim('No apto · lista '.$revision->disponible_estimado?->format('d/m H:i').' · '.$revision->motivo, ' ·'),
                $revision->created_at->format('Y-m-d H:i'),
                $revision->usuario?->name,
            );
        }

        return $this->paso('taller', 'OK de taller', 'hecho', 'Apto',
            $revision->created_at->format('Y-m-d H:i'),
            $revision->usuario?->name,
        );
    }

    /**
     * El combustible de un tramo contra el tope de su tarjeta; el de ida
     * suma los viáticos, que se depositan antes de salir.
     *
     * @param  array{tramos: list<array{sentido: string, previsto: float|null, registrado: float, falta: float|null}>, viatico_depositado: float, viatico_pendiente: float}  $gastos
     * @return Paso
     */
    private function abastecimiento(array $gastos, SentidoTramo $sentido, bool $conViaticos): array
    {
        $clave = $sentido === SentidoTramo::Ida ? 'abastecimiento_ida' : 'abastecimiento_vuelta';
        $titulo = $sentido === SentidoTramo::Ida ? 'Abastecimiento de ida' : 'Abastecimiento de vuelta';
        $tramo = collect($gastos['tramos'])->firstWhere('sentido', $sentido === SentidoTramo::Ida ? 'ida' : 'regreso');

        $partes = [];
        $completo = true;
        $algo = false;

        if ($tramo === null) {
            $partes[] = 'Sin tarjeta en la pizarra';
            $completo = false;
        } else {
            $partes[] = 'Combustible '.$this->galones($tramo['registrado']).'/'.($tramo['previsto'] === null ? 'lleno' : $this->galones($tramo['previsto'])).' gal';
            $completo = $tramo['falta'] === 0.0;
            $algo = $tramo['registrado'] > 0;
        }

        if ($conViaticos) {
            $total = $gastos['viatico_depositado'] + $gastos['viatico_pendiente'];

            if ($total > 0) {
                $partes[] = 'viáticos S/ '.number_format($gastos['viatico_depositado'], 2).' de S/ '.number_format($total, 2);
                $completo = $completo && $gastos['viatico_pendiente'] === 0.0;
                $algo = $algo || $gastos['viatico_depositado'] > 0;
            }
        }

        return $this->paso($clave, $titulo, $completo ? 'hecho' : ($algo ? 'parcial' : 'pendiente'), implode(' · ', $partes));
    }

    /**
     * En ruta desde la primera GR del tramo.
     *
     * @return Paso
     */
    private function enRuta(Vuelta $vuelta, SentidoTramo $sentido): array
    {
        $clave = $sentido === SentidoTramo::Ida ? 'en_ida' : 'en_vuelta';
        $titulo = $sentido === SentidoTramo::Ida ? 'En ida' : 'En vuelta';
        $guia = $vuelta->paradasDe($sentido)
            ->flatMap(fn (ParadaVuelta $parada) => $parada->viajes)
            ->sortBy(fn (Viaje $viaje): string => $viaje->fecha_traslado->toDateString())
            ->first();

        if ($guia === null) {
            return $this->paso($clave, $titulo, 'pendiente', 'Sin GR todavía');
        }

        return $this->paso($clave, $titulo, 'hecho', 'GR '.$guia->numero_gr, $guia->fecha_traslado->toDateString());
    }

    /**
     * @return Paso
     */
    private function regreso(Vuelta $vuelta): array
    {
        $paradas = $vuelta->paradasDe(SentidoTramo::Regreso);

        if ($paradas->isEmpty()) {
            return $this->paso('regreso', 'Regreso definido', 'pendiente', 'Por definir');
        }

        $conCarga = $paradas->first(fn (ParadaVuelta $parada): bool => ! $parada->esVacio()) ?? $paradas->first();

        return $this->paso('regreso', 'Regreso definido', 'hecho',
            $conCarga->tipo_carga->label().' · '.($conCarga->lugar_origen ?? $conCarga->zona_origen?->label() ?? '¿?').' → '.($conCarga->lugar_destino ?? $conCarga->zona_destino?->label() ?? '¿?'),
        );
    }

    /**
     * Llegó a la base: el día de la GR de regreso más lo que tarda la ruta.
     *
     * @return Paso
     */
    private function llego(Vuelta $vuelta): array
    {
        $ultima = $vuelta->paradas->last(fn (ParadaVuelta $parada): bool => $parada->viajes->isNotEmpty());
        $llegada = $ultima?->sentido === SentidoTramo::Regreso ? $vuelta->llegadaEstimada($ultima) : null;

        if ($vuelta->etapa() === 'en_base' && $llegada !== null) {
            return $this->paso('llego', 'Llegó a base', 'hecho', $ultima->lugar_destino ?? $ultima->zona_destino?->label(), $llegada->toDateString());
        }

        return $this->paso('llego', 'Llegó a base', 'pendiente', $llegada ? 'Llega aprox. el '.$llegada->format('d/m') : null);
    }

    /**
     * @return Paso
     */
    private function cerrado(Vuelta $vuelta): array
    {
        if ($vuelta->estado === EstadoVuelta::Cerrada) {
            return $this->paso('cerrado', 'Cerrado', 'hecho', 'Ya no recibe gastos', $vuelta->fin?->toDateString());
        }

        return $this->paso('cerrado', 'Cerrado', 'pendiente', 'Abierto: todavía recibe gastos');
    }

    private function galones(float $valor): string
    {
        return rtrim(rtrim(number_format($valor, 1, '.', ''), '0'), '.');
    }

    /**
     * @param  'hecho'|'parcial'|'pendiente'|'alerta'  $estado
     * @return Paso
     */
    private function paso(string $clave, string $titulo, string $estado, ?string $detalle = null, ?string $fecha = null, ?string $por = null): array
    {
        return compact('clave', 'titulo', 'estado', 'detalle', 'fecha', 'por');
    }
}
