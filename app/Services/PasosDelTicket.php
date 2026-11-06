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
     * @param  array{tramos: list<array{sentido: string, confirmada: bool, pendiente: string|null, previsto: float|null, registrado: float, falta: float|null, tanque_lleno?: array{puntos: list<array{punto_id: int|null, punto: string}>, usado: bool, galones: float}}>, viatico_depositado: float, viatico_pendiente: float}  $gastos
     * @return array{pasos: list<Paso>, actual: string|null}
     */
    public function de(Vuelta $vuelta, array $gastos): array
    {
        $pasos = $this->pasos($vuelta, $gastos, conAutor: true);
        $actual = $this->indiceActual($vuelta, $pasos);

        return ['pasos' => $pasos, 'actual' => $actual === null ? null : $pasos[$actual]['clave']];
    }

    /**
     * Para el seguimiento de la programación: lo próximo que le toca al
     * ticket desde donde va, y lo que quedó atrás sin hacer (un ticket que
     * ya va en ida sin el OK de taller). No busca quién lo programó: en el
     * tablero sería una consulta por ticket.
     *
     * @param  array{tramos: list<array{sentido: string, confirmada: bool, pendiente: string|null, previsto: float|null, registrado: float, falta: float|null, tanque_lleno?: array{puntos: list<array{punto_id: int|null, punto: string}>, usado: bool, galones: float}}>, viatico_depositado: float, viatico_pendiente: float}  $gastos
     * @return array{siguiente: Paso|null, saltados: list<string>}
     */
    public function seguimiento(Vuelta $vuelta, array $gastos): array
    {
        $pasos = $this->pasos($vuelta, $gastos, conAutor: false);
        $actual = $this->indiceActual($vuelta, $pasos);

        if ($actual === null) {
            return ['siguiente' => null, 'saltados' => []];
        }

        $saltados = [];

        foreach (array_slice($pasos, 0, $actual) as $paso) {
            if ($paso['estado'] !== 'hecho') {
                $saltados[] = $paso['titulo'];
            }
        }

        return ['siguiente' => $pasos[$actual], 'saltados' => $saltados];
    }

    /**
     * En qué paso va: el primero sin terminar después del último hecho. Así
     * un ticket que ya va en ida sigue con definir la vuelta, aunque haya
     * salido sin el OK de taller (eso queda como saltado, no como lo
     * próximo). Uno cerrado ya no espera nada.
     *
     * @param  list<Paso>  $pasos
     */
    private function indiceActual(Vuelta $vuelta, array $pasos): ?int
    {
        if ($vuelta->estado === EstadoVuelta::Cerrada) {
            return null;
        }

        // Programar no es avanzar: tener la vuelta definida desde el inicio
        // no deja atrás el taller ni el combustible de la ida. Solo cuenta
        // lo que pasó (el OK, el combustible, la GR, la llegada).
        $ultimoHecho = 0;

        foreach ($pasos as $indice => $paso) {
            if ($paso['estado'] === 'hecho' && ! in_array($paso['clave'], ['programado', 'regreso'], true)) {
                $ultimoHecho = $indice;
            }
        }

        foreach ($pasos as $indice => $paso) {
            if ($indice > $ultimoHecho && $paso['estado'] !== 'hecho') {
                return $indice;
            }
        }

        // Todo lo anterior está hecho: lo que falta es lo primero sin hacer.
        foreach ($pasos as $indice => $paso) {
            if ($paso['estado'] !== 'hecho') {
                return $indice;
            }
        }

        return null;
    }

    /**
     * @param  array{tramos: list<array{sentido: string, confirmada: bool, pendiente: string|null, previsto: float|null, registrado: float, falta: float|null, tanque_lleno?: array{puntos: list<array{punto_id: int|null, punto: string}>, usado: bool, galones: float}}>, viatico_depositado: float, viatico_pendiente: float}  $gastos
     * @return list<Paso>
     */
    private function pasos(Vuelta $vuelta, array $gastos, bool $conAutor): array
    {
        // Un ticket que arranca en la costa no tiene ida (la unidad ya había
        // bajado cuando empezaron los tickets): sus pasos no aplican, no
        // quedaron sin hacer, y los viáticos van con la vuelta.
        $sinIda = $vuelta->paradasDe(SentidoTramo::Ida)->isEmpty()
            && $vuelta->paradasDe(SentidoTramo::Regreso)->isNotEmpty();

        return array_values(array_filter([
            $conAutor ? $this->programado($vuelta) : $this->paso('programado', 'Programado', 'hecho'),
            $this->taller($vuelta),
            $sinIda ? null : $this->abastecimiento($gastos, SentidoTramo::Ida, conViaticos: true),
            $sinIda ? null : $this->enRuta($vuelta, SentidoTramo::Ida),
            $this->regreso($vuelta),
            $this->abastecimiento($gastos, SentidoTramo::Regreso, conViaticos: $sinIda),
            $this->enRuta($vuelta, SentidoTramo::Regreso),
            $this->llego($vuelta),
            $this->cerrado($vuelta),
        ]));
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
     * El combustible de un tramo contra el tope de sus rutas; el de ida
     * suma los viáticos, que se depositan antes de salir.
     *
     * @param  array{tramos: list<array{sentido: string, confirmada: bool, pendiente: string|null, previsto: float|null, registrado: float, falta: float|null, tanque_lleno?: array{puntos: list<array{punto_id: int|null, punto: string}>, usado: bool, galones: float}}>, viatico_depositado: float, viatico_pendiente: float}  $gastos
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
            $partes[] = 'Sin vuelta definida';
            $completo = false;
        } elseif (! $tramo['confirmada']) {
            $partes[] = $tramo['pendiente'] ?? 'Sin tope';
            $completo = false;
            $algo = $tramo['registrado'] > 0;
        } else {
            $partes[] = 'Combustible '.$this->galones($tramo['registrado']).'/'.($tramo['previsto'] === null ? '¿?' : $this->galones($tramo['previsto'])).' gal';
            $completo = $tramo['falta'] === 0.0;
            $algo = $tramo['registrado'] > 0;

            // El tanque full de la ruta (Kio) también se tiene que cargar.
            if (($tramo['tanque_lleno']['puntos'] ?? []) !== []) {
                $partes[] = $tramo['tanque_lleno']['usado'] ? 'tanque full hecho' : 'falta el tanque full';
                $completo = $completo && $tramo['tanque_lleno']['usado'];
                $algo = $algo || $tramo['tanque_lleno']['usado'];
            }
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
            // Lo que hay que hacer, no solo lo que falta: la GR se sube en
            // Guías y se asigna a este ticket desde «GR por asignar».
            return $this->paso($clave, $titulo, 'pendiente', 'Sube la GR en Guías y asígnala desde «GR por asignar»');
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
     * Llegó a la base: lo registra una persona, con el día y el km. Mientras
     * tanto se muestra cuándo debería llegar (y si ya se atrasó).
     *
     * @return Paso
     */
    private function llego(Vuelta $vuelta): array
    {
        if ($vuelta->llegoABase()) {
            return $this->paso('llego', 'Llegó a base', 'hecho',
                $vuelta->km_llegada === null ? null : number_format($vuelta->km_llegada, 0, '.', ',').' km',
                $vuelta->llegada_el->toDateString(),
                $vuelta->llegadaRegistradaPor?->name,
            );
        }

        $prevista = $vuelta->llegadaPrevista();

        if ($prevista === null || $vuelta->etapa() !== 'en_vuelta') {
            return $this->paso('llego', 'Llegó a base', 'pendiente', 'Lo registra programación al volver');
        }

        return $this->paso('llego', 'Llegó a base', $prevista->lt(RelojOperativo::fechaDeHoy()) ? 'alerta' : 'pendiente',
            'Debería llegar el '.$prevista->format('d/m').': regístrala al volver',
        );
    }

    /**
     * @return Paso
     */
    private function cerrado(Vuelta $vuelta): array
    {
        if ($vuelta->estado === EstadoVuelta::Cerrada) {
            return $this->paso('cerrado', 'Liquidado y cerrado', 'hecho', 'Ya no recibe gastos', $vuelta->fin?->toDateString());
        }

        return $this->paso('cerrado', 'Liquidado y cerrado', 'pendiente', 'Se cierra con la liquidación completa');
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
