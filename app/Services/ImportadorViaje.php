<?php

namespace App\Services;

use App\Enums\TipoCarga;
use App\Models\Conductor;
use App\Models\Vehiculo;
use App\Models\Viaje;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Convierte el PDF de una GR-transportista en un `Viaje`: parsea con
 * `LectorGuiaRemision`, resuelve tracto/carreta por placa y conductor por DNI
 * contra el padrón, y guarda. Resubir la misma GR actualiza el viaje en vez de
 * duplicarlo (clave `numero_gr`).
 *
 * Un archivo que no trae los campos imprescindibles —no es una GRE de Paty, o
 * viene de un formato distinto— se descarta sin tumbar el resto del lote.
 */
class ImportadorViaje
{
    /**
     * Campos sin los que un `Viaje` no tiene sentido. El resto admite quedar
     * en null (carreta, RUCs, guías remitente referenciadas).
     *
     * @var list<string>
     */
    private const CAMPOS_IMPRESCINDIBLES = [
        'numero_gr',
        'fecha_emision',
        'fecha_traslado',
        'origen',
        'destino',
        'cliente',
        'destinatario',
        'peso',
        'unidad_peso',
        'placa_tracto',
        'conductor_nombre',
    ];

    public function __construct(private readonly LectorGuiaRemision $lector) {}

    /**
     * @return array{viaje: Viaje|null, reconocido: bool}
     */
    public function importar(UploadedFile $archivo): array
    {
        try {
            $campos = $this->lector->extraerDesdeArchivo($archivo->getRealPath());
        } catch (\Throwable) {
            return ['viaje' => null, 'reconocido' => false];
        }

        foreach (self::CAMPOS_IMPRESCINDIBLES as $campo) {
            if (($campos[$campo] ?? null) === null) {
                return ['viaje' => null, 'reconocido' => false];
            }
        }

        $fechaEmision = $this->parsearFechaHora($campos['fecha_emision']);
        $fechaTraslado = $this->parsearFecha($campos['fecha_traslado']);

        if ($fechaEmision === null || $fechaTraslado === null) {
            return ['viaje' => null, 'reconocido' => false];
        }

        $tracto = $this->buscarVehiculo($campos['placa_tracto']);
        $carreta = $this->buscarVehiculo($campos['placa_carreta']);
        $conductor = $this->buscarConductor($campos['conductor_dni']);
        [$cliente, $clienteRuc] = $this->clienteReal($campos);

        // La clasificación automática (ver `clasificarCarga`) solo se aplica
        // al crear: un viaje que ya existe pudo haber sido corregido a mano
        // (`TipoCarga` no se puede leer del PDF de transportista, alguien lo
        // clasifica), y resubir la misma GR no debe pisar esa corrección.
        $yaExiste = Viaje::query()->where('numero_gr', $campos['numero_gr'])->exists();

        $atributos = [
            'fecha_emision' => $fechaEmision,
            'fecha_traslado' => $fechaTraslado,
            'origen' => $campos['origen'],
            'destino' => $campos['destino'],
            'cliente' => $cliente,
            'cliente_ruc' => $clienteRuc,
            'destinatario' => $campos['destinatario'],
            'destinatario_ruc' => $campos['destinatario_ruc'],
            'guias_remitente' => $campos['guias_remitente'],
            'peso' => $this->normalizarPeso($campos['peso']),
            'unidad_peso' => $campos['unidad_peso'],
            'placa_tracto' => $campos['placa_tracto'],
            'placa_carreta' => $campos['placa_carreta'],
            'tracto_id' => $tracto?->id,
            'carreta_id' => $carreta?->id,
            'conductor_nombre' => $campos['conductor_nombre'],
            'conductor_dni' => $campos['conductor_dni'],
            'conductor_id' => $conductor?->id,
        ];

        if (! $yaExiste) {
            $tipoCarga = $this->clasificarCarga($campos['guias_remitente']);

            if ($tipoCarga !== null) {
                $atributos['tipo_carga'] = $tipoCarga->value;
            }
        }

        $viaje = Viaje::query()->updateOrCreate(['numero_gr' => $campos['numero_gr']], $atributos);

        // El origen no debe borrarse: es el archivo subido por HTTP, no algo
        // desechable que MediaLibrary pueda consumir.
        $viaje->addMedia($archivo)->preservingOriginal()->toMediaCollection('archivo');

        return ['viaje' => $viaje, 'reconocido' => true];
    }

    /**
     * Vuelve a buscar tracto, carreta y conductor de un viaje ya guardado
     * contra el padrón actual, sin tocar el PDF. Sirve para el caso típico de
     * que la GR se suba antes de que el vehículo o el conductor estén
     * cargados: la primera vez no matchea, y acá se cierra ese hueco sin tener
     * que volver a subir el archivo.
     *
     * Nunca pisa una coincidencia que ya existía.
     */
    public function reintentar(Viaje $viaje): bool
    {
        $cambios = [];

        if ($viaje->tracto_id === null) {
            $tracto = $this->buscarVehiculo($viaje->placa_tracto);

            if ($tracto !== null) {
                $cambios['tracto_id'] = $tracto->id;
            }
        }

        if ($viaje->carreta_id === null) {
            $carreta = $this->buscarVehiculo($viaje->placa_carreta);

            if ($carreta !== null) {
                $cambios['carreta_id'] = $carreta->id;
            }
        }

        if ($viaje->conductor_id === null) {
            $conductor = $this->buscarConductor($viaje->conductor_dni);

            if ($conductor !== null) {
                $cambios['conductor_id'] = $conductor->id;
            }
        }

        if ($cambios === []) {
            return false;
        }

        $viaje->update($cambios);

        return true;
    }

    /**
     * Cuando el flete lo paga un subcontratador, ese es el vínculo comercial
     * real de Paty — no el remitente que figura en la GR, que solo es dueño
     * de la carga. Verificado contra el corpus de GRs de Ajeper: el
     * remitente siempre dice «AJEPER S.A.», pero quien contrata y paga a
     * Paty es «CRISAR LOGISTICA S.A.C.» en todas, sin excepción.
     *
     * También pareja mayúsculas: la misma empresa llega de la GR como
     * «Minsur S.A.» en unos documentos y «MINSUR S.A.» en otros, y sin
     * normalizar quedan como si fueran dos clientes distintos.
     *
     * @param  array<string, mixed>  $campos
     * @return array{0: string, 1: string|null}
     */
    private function clienteReal(array $campos): array
    {
        if ($campos['subcontratador'] !== null) {
            return [Str::upper($campos['subcontratador']), $campos['subcontratador_ruc']];
        }

        return [Str::upper($campos['cliente']), $campos['cliente_ruc']];
    }

    /**
     * @see TipoCarga::desdeGuiaRemitenteMinsur() para la regla en sí. Solo
     * mira la primera guía remitente referenciada: en el histórico revisado,
     * cuando un viaje trae más de una siempre son de la misma serie.
     *
     * Pública porque también la usa el comando de reclasificación masiva
     * (`transpaty:clasificar-carga-minsur`), que corrige viajes que ya
     * existían antes de que esta regla se agregara.
     *
     * @param  list<array{numero: string, ruc: string}>  $guiasRemitente
     */
    public function clasificarCarga(array $guiasRemitente): ?TipoCarga
    {
        $primera = $guiasRemitente[0] ?? null;

        if ($primera === null || $primera['ruc'] !== TipoCarga::RUC_MINSUR) {
            return null;
        }

        return TipoCarga::desdeGuiaRemitenteMinsur($primera['numero']);
    }

    private function buscarVehiculo(?string $placa): ?Vehiculo
    {
        if ($placa === null) {
            return null;
        }

        return Vehiculo::query()->wherePlacaLike($placa)->first();
    }

    private function buscarConductor(?string $dni): ?Conductor
    {
        if ($dni === null) {
            return null;
        }

        return Conductor::query()->where('documento', $dni)->first();
    }

    private function parsearFechaHora(string $texto): ?Carbon
    {
        try {
            return Carbon::createFromFormat('d/m/Y h:i A', $texto);
        } catch (\Exception) {
            return null;
        }
    }

    private function parsearFecha(string $texto): ?string
    {
        try {
            return Carbon::createFromFormat('d/m/Y', $texto)->toDateString();
        } catch (\Exception) {
            return null;
        }
    }

    private function normalizarPeso(string $texto): float
    {
        return (float) str_replace(',', '', $texto);
    }
}
