<?php

namespace App\Services\Sunat;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Las consultas que hace el formulario «Emisión de GRE» de SOL contra
 * `api-cpe.sunat.gob.pe`, con el token de {@see SesionSol}. Solo lectura: la
 * emisión se suma cuando se grabe una real.
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
            return Http::withToken($this->sesion->token())
                ->acceptJson()
                ->withHeaders([
                    'Origin' => 'https://e-factura.sunat.gob.pe',
                    'Referer' => 'https://e-factura.sunat.gob.pe/',
                ])
                ->timeout(20)
                ->get(self::BASE.$ruta);
        } catch (ConnectionException) {
            throw new RuntimeException('No se pudo conectar con la API de SUNAT.');
        }
    }

    private function codigoError(Response $respuesta): ?int
    {
        $codigo = $respuesta->json('errors.0.cod');

        return is_numeric($codigo) ? (int) $codigo : null;
    }
}
