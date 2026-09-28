<?php

namespace App\Services\Sunat;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use RuntimeException;

/**
 * Las consultas que hace el formulario «Emisión de GRE» de SOL contra
 * `api-cpe.sunat.gob.pe`, con el token de {@see SesionSol}: las consultas, la
 * emisión y la descarga del PDF, copiadas de una emisión real (EG03-12623).
 *
 * Los errores de validación de SUNAT (HTTP 422) no se lanzan: vuelven como
 * `null` o con su mensaje, porque en la pantalla son información («no
 * encontramos esa licencia»), no una falla.
 */
class ClienteGreSunat
{
    private const BASE = 'https://api-cpe.sunat.gob.pe/v1/contribuyente';

    /** SUNAT responde 2049 cuando la GR existe pero Paty no figura en ella. */
    private const SIN_PERMISO_PARA_CONSULTAR = 2049;

    public function __construct(private readonly SesionSol $sesion) {}

    /**
     * La GR-remitente completa si Paty figura como transportista; si no, la
     * versión resumida que SUNAT da a cualquiera (remitente, partida, llegada,
     * peso y RUC del transportista), marcada con `completa = false`.
     *
     * @return array{completa: bool, datos: array<string, mixed>}|null null si no existe
     */
    public function guiaRemitente(string $ruc, string $serie, int $numero): ?array
    {
        $id = sprintf('%s-09-%s-%d', $ruc, strtoupper($serie), $numero);
        $respuesta = $this->pedir("/gre/comprobantes/{$id}");

        if ($respuesta->successful()) {
            return ['completa' => true, 'datos' => $respuesta->json()];
        }

        if ($this->codigoError($respuesta) !== self::SIN_PERMISO_PARA_CONSULTAR) {
            return null;
        }

        $resumida = $this->pedir("/gre/comprobantes/simplificado/{$id}");

        return $resumida->successful() ? ['completa' => false, 'datos' => $resumida->json()] : null;
    }

    /**
     * TUCE o certificado de habilitación vehicular registrado en el MTC.
     *
     * @return array{numTucChv: string, indTucChv: string, indVigencia: string}|null
     */
    public function placa(string $placa): ?array
    {
        $placa = strtoupper(str_replace(['-', ' '], '', $placa));
        $respuesta = $this->pedir("/gre/mtc/{$placa}/numPlaca");

        return $respuesta->successful() ? $respuesta->json() : null;
    }

    /**
     * La licencia en el MTC, o el mensaje de SUNAT cuando no la encuentra.
     *
     * @return array{encontrada: bool, datos: array<string, mixed>|null, mensaje: string|null}
     */
    public function licencia(string $numero): array
    {
        $respuesta = $this->pedir('/gre/mtc/'.strtoupper($numero).'/numLicencia');

        return [
            'encontrada' => $respuesta->successful(),
            'datos' => $respuesta->successful() ? $respuesta->json() : null,
            'mensaje' => $respuesta->successful() ? null : $respuesta->json('errors.0.msg'),
        ];
    }

    /**
     * Nombre y dirección de una persona por DNI (RENIEC vía SUNAT).
     *
     * @return array<string, mixed>|null
     */
    public function persona(string $dni): ?array
    {
        $respuesta = $this->pedir("/parametros/personas/{$dni}");

        return $respuesta->successful() ? $respuesta->json() : null;
    }

    /**
     * Razón social y domicilio de un RUC (lo que autocompleta SOL).
     *
     * @return array<string, mixed>|null
     */
    public function contribuyente(string $ruc): ?array
    {
        $respuesta = $this->pedir("/parametros/contribuyentes/{$ruc}");

        return $respuesta->successful() ? $respuesta->json('datosContribuyente') : null;
    }

    /**
     * Emite la GR-transportista con el cuerpo que arma {@see EmisionGre}.
     * Devuelve el número que asignó SUNAT.
     *
     * No se reintenta NUNCA salvo un 401, que SUNAT responde sin procesar
     * nada (token vencido). Cualquier otro problema después de enviar —corte,
     * timeout, 5xx— deja la emisión en duda: pudo haber salido, y repetirla
     * emitiría una GR duplicada que después hay que dar de baja.
     *
     * @param  array<string, mixed>  $cuerpo
     * @return array{serie: string, numero: int, qr: string|null}
     *
     * @throws EmisionRechazada SUNAT validó y no emitió (es seguro corregir y volver a intentar)
     * @throws EmisionEnDuda no se sabe si se emitió
     */
    public function emitir(array $cuerpo, string $serie): array
    {
        $ruta = "/gre/comprobantes/31-{$serie}/emision";

        try {
            $respuesta = $this->solicitud(60)->post(self::BASE.$ruta, $cuerpo);

            if ($respuesta->status() === 401) {
                $this->sesion->olvidar();
                $respuesta = $this->solicitud(60)->post(self::BASE.$ruta, $cuerpo);
            }
        } catch (ConnectionException) {
            throw new EmisionEnDuda('Se cortó la conexión con SUNAT mientras emitía.');
        }

        if (in_array($respuesta->status(), [400, 422], true)) {
            $errores = $respuesta->json('errors');
            $mensajes = is_array($errores)
                ? implode(' · ', array_filter(array_map(fn (mixed $error): string => is_array($error) ? (string) ($error['msg'] ?? '') : '', $errores)))
                : '';

            throw new EmisionRechazada($mensajes !== '' ? $mensajes : (string) $respuesta->json('msg', 'SUNAT rechazó la GR.'));
        }

        $numero = $respuesta->json('data.pkComprobante.numCpe');

        if (! $respuesta->successful() || ! is_numeric($numero)) {
            throw new EmisionEnDuda("SUNAT respondió {$respuesta->status()} sin el número de la GR.");
        }

        return [
            'serie' => (string) $respuesta->json('data.pkComprobante.numSerieCpe', $serie),
            'numero' => (int) $numero,
            'qr' => $respuesta->json('data.qr'),
        ];
    }

    /**
     * El PDF de una GR-transportista de Paty ya emitida, en binario. Es una
     * lectura, así que se reintenta: recién emitida, SUNAT a veces tarda en
     * tenerlo listo o corta la primera conexión.
     */
    public function pdf(string $ruc, string $serie, int $numero, int $intentos = 3): ?string
    {
        $ruta = "/gre/comprobantes/{$ruc}-31-{$serie}-{$numero}/descarga/pdf";

        for ($intento = 1; ; $intento++) {
            try {
                $respuesta = $this->pedir($ruta);
                $pdf = $respuesta->successful() ? $respuesta->json('pdf') : null;
                $binario = is_string($pdf) ? base64_decode($pdf, true) : false;

                if (is_string($binario) && str_starts_with($binario, '%PDF')) {
                    return $binario;
                }
            } catch (RuntimeException $error) {
                if ($intento >= $intentos) {
                    throw $error;
                }
            }

            if ($intento >= $intentos) {
                return null;
            }

            Sleep::for(2)->seconds();
        }
    }

    /**
     * GET con el token. Si SUNAT lo da por vencido (401) se inicia sesión de
     * nuevo UNA vez: es una lectura, repetirla no tiene efectos.
     */
    private function pedir(string $ruta): Response
    {
        $respuesta = $this->enviar($ruta);

        if ($respuesta->status() === 401) {
            $this->sesion->olvidar();
            $respuesta = $this->enviar($ruta);
        }

        if ($respuesta->serverError() || $respuesta->status() === 401) {
            throw new RuntimeException("SUNAT respondió {$respuesta->status()} al consultar {$ruta}.");
        }

        return $respuesta;
    }

    private function enviar(string $ruta): Response
    {
        try {
            return $this->solicitud(20)->get(self::BASE.$ruta);
        } catch (ConnectionException) {
            throw new RuntimeException('No se pudo conectar con la API de SUNAT.');
        }
    }

    /**
     * Con los encabezados que manda el navegador. No son decorativos: el
     * firewall de SUNAT corta la conexión («Empty reply from server») a la
     * descarga del PDF si faltan, aunque el token sea válido. Se probó
     * 4 de 4 con el juego completo y 0 de 4 con el mínimo (28-09-2026).
     */
    private function solicitud(int $segundos): PendingRequest
    {
        return Http::withToken($this->sesion->token())
            ->withHeaders([
                'Accept' => 'application/json, text/plain, */*',
                'Content-Type' => 'application/json',
                'Accept-Language' => 'es-PE,es;q=0.9',
                'Origin' => 'https://e-factura.sunat.gob.pe',
                'Referer' => 'https://e-factura.sunat.gob.pe/',
                'Sec-Fetch-Dest' => 'empty',
                'Sec-Fetch-Mode' => 'cors',
                'Sec-Fetch-Site' => 'same-site',
            ])
            ->withUserAgent(SesionSol::AGENTE)
            ->timeout($segundos);
    }

    private function codigoError(Response $respuesta): ?int
    {
        $codigo = $respuesta->json('errors.0.cod');

        return is_numeric($codigo) ? (int) $codigo : null;
    }
}
