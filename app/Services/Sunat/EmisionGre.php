<?php

namespace App\Services\Sunat;

use App\Enums\TipoDocumento;
use App\Models\Conductor;
use App\Models\Vehiculo;
use App\Models\Viaje;
use App\Services\ImportadorViaje;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Arma y emite una GR-transportista de Paty con el mismo cuerpo que envía el
 * formulario de SOL (copiado de la emisión real EG03-12623, 28-09-2026), y
 * deja el viaje creado con el PDF que devuelve SUNAT.
 *
 * Todo dato que va a SUNAT se vuelve a consultar acá, en el servidor: la
 * GR-remitente, el TUCE de cada placa, el nombre del conductor en RENIEC y la
 * razón social del pagador. Lo que mandó el navegador solo dice QUÉ se eligió.
 */
class EmisionGre
{
    public const PAGADOR_REMITENTE = '01';

    public const PAGADOR_SUBCONTRATADOR = '02';

    public const PAGADOR_TERCERO = '03';

    public function __construct(
        private readonly ClienteGreSunat $sunat,
        private readonly ImportadorViaje $importador,
    ) {}

    /**
     * @param  list<array{ruc: string, serie: string, numero: int}>  $guias
     * @param  array<int, string>  $tuces  TUCE elegido a mano por id de vehículo (p. ej. el RUC de Paty)
     * @param  string|null  $rucSubcontratador  quien subcontrató a Paty para este traslado (p. ej. Crisar)
     * @return array{numero_gr: string, viaje: Viaje|null}
     *
     * @throws RuntimeException un dato no permite emitir (no se envió nada)
     * @throws EmisionRechazada
     * @throws EmisionEnDuda
     */
    public function emitir(
        array $guias,
        Vehiculo $tracto,
        ?Vehiculo $carreta,
        Conductor $conductor,
        string $fechaTraslado,
        string $pagador,
        ?string $rucPagador,
        array $tuces = [],
        ?string $rucSubcontratador = null,
    ): array {
        $clave = 'sunat.emision.'.md5(json_encode($guias) ?: '');

        // Doble clic o dos personas con la misma GR-remitente: la segunda
        // espera y, al entrar, ya encuentra la GR-transportista registrada.
        return Cache::lock($clave, 180)->block(60, function () use ($guias, $tracto, $carreta, $conductor, $fechaTraslado, $pagador, $rucPagador, $tuces, $rucSubcontratador): array {
            $cuerpo = $this->armar($guias, $tracto, $carreta, $conductor, $fechaTraslado, $pagador, $rucPagador, $tuces, $rucSubcontratador);
            $serie = (string) config('services.sunat_sol.serie_gre');

            Log::channel('sunat')->info('SUNAT GRE: emitiendo GR-transportista.', ['guias' => $guias, 'tracto' => $tracto->placa]);

            try {
                $emitida = $this->sunat->emitir($cuerpo, $serie);
            } catch (EmisionEnDuda $duda) {
                Log::channel('sunat')->error('SUNAT GRE: emisión EN DUDA, revisar en SOL antes de reintentar.', ['guias' => $guias, 'motivo' => $duda->getMessage()]);
                Log::error('SUNAT GRE: emisión EN DUDA, revisar en SOL antes de reintentar.', ['guias' => $guias]);

                throw $duda;
            }

            $numeroGr = sprintf('%s-%08d', $emitida['serie'], $emitida['numero']);
            Log::channel('sunat')->info("SUNAT GRE: emitida {$numeroGr}.");

            return ['numero_gr' => $numeroGr, 'viaje' => $this->registrarEmitida($numeroGr)];
        });
    }

    /**
     * El cuerpo de la emisión. Público para poder compararlo, campo por campo,
     * con el que envió SOL en la emisión grabada.
     *
     * @param  list<array{ruc: string, serie: string, numero: int}>  $guias
     * @param  array<int, string>  $tuces  TUCE elegido a mano por id de vehículo
     * @param  string|null  $rucSubcontratador  quien subcontrató a Paty para este traslado (p. ej. Crisar)
     * @return array<string, mixed>
     */
    public function armar(
        array $guias,
        Vehiculo $tracto,
        ?Vehiculo $carreta,
        Conductor $conductor,
        string $fechaTraslado,
        string $pagador,
        ?string $rucPagador,
        array $tuces = [],
        ?string $rucSubcontratador = null,
    ): array {
        if ($guias === []) {
            throw new RuntimeException('Falta la GR-remitente.');
        }

        $consultas = array_map(fn (array $guia): array => $this->guiaVerificada($guia), $guias);
        $datosGuias = array_column($consultas, 'datos');
        $principal = $datosGuias[0];
        $remitente = [
            'codTipoDocIdentidad' => '6',
            'desNombre' => (string) data_get($principal, 'emisor.desNombre'),
            'numDocIdentidad' => $guias[0]['ruc'],
            'indFrecuente' => '0',
        ];
        $ruc = (string) config('services.sunat_sol.ruc');
        $nombrePaty = (string) ($this->sunat->contribuyente($ruc)['desRazonSocial'] ?? '');

        if ($nombrePaty === '') {
            throw new RuntimeException('SUNAT no devolvió la razón social de Paty.');
        }

        $subcontratador = $rucSubcontratador === null ? null : $this->contribuyenteConNombre($rucSubcontratador, 'la empresa que subcontrata');

        // Si paga el subcontratador y no se indicó otro RUC, es el mismo.
        if ($pagador === self::PAGADOR_SUBCONTRATADOR && $rucPagador === null && $subcontratador !== null) {
            $rucPagador = $subcontratador['numDocIdentidad'];
        }

        $vehiculos = [$this->vehiculo($tracto, '1', $tuces[$tracto->id] ?? null)];

        if ($carreta !== null) {
            $vehiculos[] = $this->vehiculo($carreta, '2', $tuces[$carreta->id] ?? null);
        }

        return [
            'codCpe' => '31',
            'codTipoCpe' => '00',
            'numSerie' => (string) config('services.sunat_sol.serie_gre'),
            'codEstado' => '01',
            'emision' => ['indSEE' => '1', 'indOrigen' => '1'],
            'emisor' => [
                'indEncSunNumAutorizacionMtc' => '1',
                'numAutorizacionMtc' => (string) config('services.sunat_sol.registro_mtc'),
                'desNombre' => $nombrePaty,
                // «Transporte subcontratado: Sí» y el bloque «Datos del
                // subcontratador» de la GR (emisión EG03-12627).
                'indSubContratacion' => $subcontratador === null ? '0' : '1',
            ] + ($subcontratador === null ? [] : ['subContratador' => $subcontratador]),
            'receptor' => [
                'codTipoDocIdentidad' => (string) data_get($principal, 'receptor.codTipoDocIdentidad', '6'),
                'desNombre' => (string) data_get($principal, 'receptor.desNombre'),
                'numDocIdentidad' => (string) data_get($principal, 'receptor.numDocIdentidad'),
                'indFrecuente' => '0',
            ],
            'traslado' => [
                'fecInicioTraslado' => Carbon::parse($fechaTraslado, 'America/Lima')->startOfDay()->format('Y-m-d\TH:i:s.vP'),
                'indTransbordo' => '0',
                'indRetornoVehicEnvEmbVacio' => '0',
                'indRetornoVehicVacio' => '0',
                'indPagadorFlete' => $pagador,
                'codUnidadMedidaPb' => (string) data_get($principal, 'traslado.codUnidadMedidaPb'),
                'numPesoBruto' => $this->pesoTotal($datosGuias),
                'bien' => [],
                'vehiculo' => $vehiculos,
                'conductor' => [$this->conductor($conductor)],
                'transportista' => [
                    'codTipoDocIdentidad' => '6',
                    'numDocIdentidad' => $ruc,
                    'desNombre' => $nombrePaty,
                    'indFrecuente' => '0',
                ],
                'partida' => $this->punto(data_get($principal, 'traslado.partida.direccion'), 'partida'),
                'llegada' => $this->punto(data_get($principal, 'traslado.llegada.direccion'), 'llegada'),
                'pagadorFlete' => $this->pagador($pagador, $rucPagador, $remitente),
            ],
            'docRelacionado' => array_map(fn (array $guia, array $consulta): array => [
                'codTipoDocumento' => '09',
                'desTipoDocumento' => 'Guía de Remisión Remitente',
                'numSerie' => $guia['serie'],
                'numDocumento' => (string) $guia['numero'],
                'numRuc' => $guia['ruc'],
                'indEncSunDocRelacionado' => '1',
                // Falso cuando SUNAT solo deja ver a Paty la versión resumida
                // (la GR-remitente consigna a otro transportista, p. ej. la
                // subcontratante): así lo manda SOL (EG03-12627).
                'esVisible' => $consulta['completa'],
            ], $guias, $consultas),
            'numRuc' => $ruc,
            'remitente' => $remitente,
        ];
    }

    /**
     * El número de la GR-transportista que ya ampara esa GR-remitente, si
     * existe. `guias_remitente` guarda el número tal como venía impreso
     * («T007 - 10088», a veces con ceros), así que se compara normalizado.
     */
    public function grTransportistaExistente(string $ruc, string $serie, int $numero): ?string
    {
        $buscado = "{$serie}-{$numero}";

        return Viaje::query()
            ->whereRaw('upper(cast(guias_remitente as text)) like ?', ["%{$serie}%"])
            ->get(['numero_gr', 'guias_remitente'])
            ->first(function (Viaje $viaje) use ($ruc, $buscado): bool {
                foreach ($viaje->guias_remitente ?? [] as $guia) {
                    [$serieGuardada, $numeroGuardado] = array_pad(explode('-', str_replace(' ', '', strtoupper($guia['numero'])), 2), 2, '');

                    if ($serieGuardada.'-'.ltrim($numeroGuardado, '0') === $buscado && $guia['ruc'] === $ruc) {
                        return true;
                    }
                }

                return false;
            })?->numero_gr;
    }

    /**
     * @param  array{ruc: string, serie: string, numero: int}  $guia
     * @return array{completa: bool, datos: array<string, mixed>}
     */
    private function guiaVerificada(array $guia): array
    {
        $nombre = "{$guia['serie']}-{$guia['numero']}";
        $existente = $this->grTransportistaExistente($guia['ruc'], $guia['serie'], $guia['numero']);

        if ($existente !== null) {
            throw new RuntimeException("La GR-remitente {$nombre} ya tiene GR-transportista: {$existente}.");
        }

        $consulta = $this->sunat->guiaRemitente($guia['ruc'], $guia['serie'], $guia['numero']);

        if ($consulta === null) {
            throw new RuntimeException("SUNAT no encontró la GR-remitente {$nombre}.");
        }

        if (data_get($consulta['datos'], 'codEstado') !== '01') {
            throw new RuntimeException("La GR-remitente {$nombre} no está vigente en SUNAT.");
        }

        return $consulta;
    }

    /**
     * El TUCE que va en la GR, en este orden:
     *
     * 1. el del documento «Habilitación MTC» de la ficha en Transpaty, si
     *    tiene número y no venció;
     * 2. el que da el MTC por la placa;
     * 3. el RUC de Paty, cuando no hay ninguno de los dos (decisión del
     *    usuario, 28-09-2026).
     *
     * El MTC se consulta siempre, porque la GR declara si SUNAT tiene la placa
     * y el TUCE registrados (`indEncSun…`): hay placas con habilitación nueva
     * que SUNAT todavía no conoce (VJS-982, 21M26000149E).
     *
     * @return array{numero: string, origen: 'transpaty'|'mtc'|'ruc', vence: string|null, placaEnSunat: bool, tuceEnSunat: bool, indTucChv: string}
     */
    public function tuce(Vehiculo $vehiculo): array
    {
        $mtc = $this->sunat->placa($this->placaSunat($vehiculo));
        $tuceMtc = is_string($mtc['numTucChv'] ?? null) && $mtc['numTucChv'] !== '' ? $mtc['numTucChv'] : null;

        $documento = $vehiculo->documentos()
            ->where('tipo', TipoDocumento::HabilitacionMtc)
            ->whereNotNull('numero')
            ->where('numero', '!=', '')
            ->where(fn ($query) => $query->whereNull('fecha_vencimiento')->orWhereDate('fecha_vencimiento', '>=', now('America/Lima')->toDateString()))
            ->first();

        [$numero, $origen, $vence] = match (true) {
            $documento !== null => [Str::upper(trim((string) $documento->numero)), 'transpaty', $documento->fecha_vencimiento?->toDateString()],
            $tuceMtc !== null => [$tuceMtc, 'mtc', null],
            default => [(string) config('services.sunat_sol.ruc'), 'ruc', null],
        };

        return [
            'numero' => $numero,
            'origen' => $origen,
            'vence' => $vence,
            'placaEnSunat' => $mtc !== null,
            'tuceEnSunat' => $tuceMtc !== null && $tuceMtc === $numero,
            'indTucChv' => is_string($mtc['indTucChv'] ?? null) ? $mtc['indTucChv'] : '2',
        ];
    }

    /**
     * @param  string|null  $elegido  TUCE escrito a mano en la pantalla (o el RUC de Paty); manda sobre la ficha y el MTC
     * @return array<string, string>
     */
    private function vehiculo(Vehiculo $vehiculo, string $tipo, ?string $elegido = null): array
    {
        $tuce = $this->tuce($vehiculo);
        $elegido = $elegido === null ? null : Str::upper(trim($elegido));

        if ($elegido !== null && $elegido !== '' && $elegido !== $tuce['numero']) {
            $mtc = $this->sunat->placa($this->placaSunat($vehiculo));
            $tuce['numero'] = $elegido;
            $tuce['tuceEnSunat'] = ($mtc['numTucChv'] ?? null) === $elegido;
        }

        return [
            'indTipoVehiculo' => $tipo,
            'numPlaca' => $this->placaSunat($vehiculo),
            'numTucChv' => $tuce['numero'],
            'indTucChv' => $tuce['indTucChv'],
            'indEncSunNumTucChv' => $tuce['tuceEnSunat'] ? '1' : '0',
            'indEncSunNumPlaca' => $tuce['placaEnSunat'] ? '1' : '0',
            'indFrecuente' => '0',
        ];
    }

    private function placaSunat(Vehiculo $vehiculo): string
    {
        return strtoupper(str_replace(['-', ' '], '', $vehiculo->placa));
    }

    /** @return array<string, string> */
    private function conductor(Conductor $conductor): array
    {
        if (blank($conductor->licencia)) {
            throw new RuntimeException("{$conductor->nombre_completo} no tiene licencia registrada.");
        }

        $persona = $this->sunat->persona($conductor->documento);
        $nombre = trim(implode(' ', array_filter([
            $persona['apePaterno'] ?? null,
            $persona['apeMaterno'] ?? null,
            $persona['nomPerNat'] ?? null,
        ])));

        if ($nombre === '') {
            throw new RuntimeException("RENIEC no encontró el DNI {$conductor->documento}.");
        }

        $licencia = Str::upper((string) $conductor->licencia);

        return [
            'indTipoOrden' => '1',
            'codTipoDocIdentidad' => '1',
            'numDocIdentidad' => $conductor->documento,
            'numLicencia' => $licencia,
            'desNombre' => $nombre,
            'indEncSunNumLicencia' => $this->sunat->licencia($licencia)['encontrada'] ? '1' : '0',
            'desTipoDocIdentidad' => 'DOCUMENTO NACIONAL DE IDENTIDAD',
            'indFrecuente' => '0',
        ];
    }

    /**
     * Quién paga el flete. Para el remitente se manda su propio RUC: es la
     * única variante que no está en la emisión grabada (que fue «tercero»);
     * si SUNAT la rechazara, responde 422 y no se emite nada.
     *
     * @param  array{codTipoDocIdentidad: string, desNombre: string, numDocIdentidad: string, indFrecuente: string}  $remitente
     * @return array{codTipoDocIdentidad: string, numDocIdentidad: string, desNombre: string}
     */
    private function pagador(string $pagador, ?string $ruc, array $remitente): array
    {
        if ($pagador === self::PAGADOR_REMITENTE) {
            return [
                'codTipoDocIdentidad' => '6',
                'numDocIdentidad' => $remitente['numDocIdentidad'],
                'desNombre' => $remitente['desNombre'],
            ];
        }

        if ($ruc === null) {
            throw new RuntimeException('Falta el RUC de quien paga el flete.');
        }

        return $this->contribuyenteConNombre($ruc, 'quien paga el flete');
    }

    /**
     * Un RUC con su razón social según SUNAT, en la forma en que la GR lo
     * pide para el pagador y el subcontratador.
     *
     * @return array{codTipoDocIdentidad: string, numDocIdentidad: string, desNombre: string}
     */
    private function contribuyenteConNombre(string $ruc, string $quien): array
    {
        $nombre = (string) ($this->sunat->contribuyente($ruc)['desRazonSocial'] ?? '');

        if ($nombre === '') {
            throw new RuntimeException("SUNAT no encontró el RUC {$ruc} de {$quien}.");
        }

        return ['codTipoDocIdentidad' => '6', 'numDocIdentidad' => $ruc, 'desNombre' => $nombre];
    }

    /** @return array{direccion: array<string, string>, indFrecuente: string} */
    private function punto(mixed $direccion, string $cual): array
    {
        if (! is_array($direccion) || blank($direccion['codUbigeo'] ?? null)) {
            throw new RuntimeException("La GR-remitente no trae el punto de {$cual} con su ubigeo.");
        }

        return [
            'direccion' => [
                'codUbigeo' => (string) $direccion['codUbigeo'],
                'desDireccion' => (string) ($direccion['desDireccion'] ?? ''),
                'desDepartamento' => (string) ($direccion['desDepartamento'] ?? ''),
                'desProvincia' => (string) ($direccion['desProvincia'] ?? ''),
                'desDistrito' => (string) ($direccion['desDistrito'] ?? ''),
            ],
            'indFrecuente' => '0',
        ];
    }

    /** @param  list<array<string, mixed>>  $guias */
    private function pesoTotal(array $guias): string
    {
        $total = array_sum(array_map(fn (array $guia): float => (float) data_get($guia, 'traslado.numPesoBruto', 0), $guias));

        return rtrim(rtrim(number_format($total, 3, '.', ''), '0'), '.');
    }

    /**
     * Baja de SUNAT el PDF de una GR-transportista de Paty ya emitida y la
     * pasa por el importador de siempre, así el viaje queda igual que uno
     * subido a mano. Se usa al emitir y para recuperar una emitida cuyo PDF no
     * llegó. Devuelve null si el PDF no llega: la GR igual está emitida.
     */
    public function registrarEmitida(string $numeroGr): ?Viaje
    {
        if (! preg_match('/^([A-Z0-9]{4})-0*(\d+)$/', Str::upper(trim($numeroGr)), $partes)) {
            throw new RuntimeException("«{$numeroGr}» no es un número de GR (ej. EG03-00012624).");
        }

        [, $serie, $numero] = $partes;
        $ruc = (string) config('services.sunat_sol.ruc');

        try {
            $pdf = $this->sunat->pdf($ruc, $serie, (int) $numero);
        } catch (RuntimeException $error) {
            Log::channel('sunat')->warning("SUNAT GRE: no se pudo bajar el PDF de {$numeroGr}.", ['motivo' => $error->getMessage()]);

            return null;
        }

        if ($pdf === null) {
            Log::channel('sunat')->warning("SUNAT GRE: SUNAT no devolvió el PDF de {$numeroGr}.");

            return null;
        }

        $ruta = tempnam(sys_get_temp_dir(), 'gre');
        file_put_contents($ruta, $pdf);

        try {
            $nombre = sprintf('%s-31-%s-%d.pdf', $ruc, $serie, $numero);
            $viaje = $this->importador->importar(new UploadedFile($ruta, $nombre, 'application/pdf', null, true))['viaje'];
        } finally {
            @unlink($ruta);
        }

        if ($viaje === null) {
            Log::channel('sunat')->warning("SUNAT GRE: el importador no reconoció el PDF de {$numeroGr}.");
        } else {
            Log::channel('sunat')->info("SUNAT GRE: viaje registrado para {$numeroGr}.", ['viaje_id' => $viaje->id]);
        }

        return $viaje;
    }
}
