<?php

namespace App\Services;

use App\Enums\TipoCarga;
use App\Models\Cliente;
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
        $clienteDelPadron = Cliente::porRuc($clienteRuc);

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
            'cliente_id' => $clienteDelPadron?->id,
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
     * Vuelve a buscar tracto, carreta, conductor y cliente de un viaje ya
     * guardado contra el padrón actual, sin tocar el PDF. Sirve para el caso
     * típico de que la GR se suba antes de que el vehículo, el conductor o el
     * cliente estén cargados: la primera vez no matchea, y acá se cierra ese
     * hueco sin tener que volver a subir el archivo.
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

        if ($viaje->cliente_id === null) {
            $cliente = Cliente::porRuc($viaje->cliente_ruc);

            if ($cliente !== null) {
                $cambios['cliente_id'] = $cliente->id;
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
     * @param  array<string, mixed>  $campos
     * @return array{0: string, 1: string|null}
     */
    private function clienteReal(array $campos): array
    {
        if ($campos['subcontratador'] !== null) {
            return [self::normalizarRazonSocial($campos['subcontratador']), $campos['subcontratador_ruc']];
        }

        return [self::normalizarRazonSocial($campos['cliente']), $campos['cliente_ruc']];
    }

    /**
     * La misma empresa llega escrita distinto según quién emitió la GR, y sin
     * normalizar el nombre queda como si fueran clientes distintos —se parten
     * en dos barras del tablero y en dos opciones del filtro. Se empareja lo
     * que es puro formato:
     *
     * - mayúsculas («Minsur S.A.» y «MINSUR S.A.»);
     * - espacios de más entre palabras;
     * - espacios dentro de la forma societaria («MINSUR S. A.» → «MINSUR
     *   S.A.», «CRISAR LOGISTICA S. A. C.» → «S.A.C.»), que es la variante
     *   que traen las GR reales de Minsur.
     *
     * Solo aplica al cliente. El destinatario NO se normaliza: ahí los
     * sufijos distinguen puntos de entrega reales («MP11 - San Rafael»,
     * «CODISAL (JULIACA)») y fusionarlos perdería el dato.
     *
     * Estática porque también la usa la migración que emparejó los viajes ya
     * importados antes de esta regla.
     */
    public static function normalizarRazonSocial(string $nombre): string
    {
        $normalizado = Str::squish(Str::upper($nombre));

        // Quita el espacio que separa las iniciales punteadas de la forma
        // societaria, sin tocar «S.A.C. (JULIACA)» ni nombres corrientes.
        return preg_replace('/(?<=\b\p{Lu}\.)\s+(?=\p{Lu}\.)/u', '', $normalizado) ?? $normalizado;
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
            // La GRE de Bienes Fiscalizados solo trae fecha de emisión, sin
            // hora (ver `LectorGuiaRemision::extraerCamposBienesFiscalizados`).
            try {
                return Carbon::createFromFormat('d/m/Y', $texto)->startOfDay();
            } catch (\Exception) {
                return null;
            }
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
