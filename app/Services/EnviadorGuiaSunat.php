<?php

namespace App\Services;

use Greenter\Sunat\GRE\Api\AuthApi;
use Greenter\Sunat\GRE\Api\CpeApi;
use Greenter\Sunat\GRE\Configuration;
use Greenter\Sunat\GRE\Model\CpeDocument;
use Greenter\Sunat\GRE\Model\CpeDocumentArchivo;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use RuntimeException;
use ZipArchive;

/**
 * Envía la guía a SUNAT por el API REST de GRE.
 *
 * El envío es asíncrono y son dos viajes: el primero entrega el zip y devuelve
 * un ticket, el segundo consulta ese ticket y recién ahí aparece el CDR. Que
 * un envío responda con ticket no significa que la guía haya sido aceptada.
 */
class EnviadorGuiaSunat
{
    /** Ámbito que SUNAT exige al pedir el token para comprobantes. */
    private const SCOPE = 'https://api-cpe.sunat.gob.pe';

    public function __construct(
        private readonly ClientInterface $http = new Client,
    ) {}

    /**
     * Deja el documento en SUNAT y devuelve el ticket con el que se consulta
     * su resultado más tarde.
     */
    public function enviar(string $nombreArchivo, string $xmlFirmado): string
    {
        $zip = $this->comprimir($nombreArchivo, $xmlFirmado);

        $documento = (new CpeDocument)->setArchivo(
            (new CpeDocumentArchivo)
                ->setNomArchivo("{$nombreArchivo}.zip")
                ->setArcGreZip(base64_encode($zip))
                ->setHashZip(hash('sha256', $zip))
        );

        $respuesta = $this->cpeApi()->enviarCpe($nombreArchivo, $documento);
        $ticket = $respuesta->getNumTicket();

        if (blank($ticket)) {
            throw new RuntimeException("SUNAT no devolvió ticket para {$nombreArchivo}.");
        }

        return $ticket;
    }

    /**
     * El resultado del envío. `aceptada` distingue el caso feliz; cuando no lo
     * es, el detalle viene en `codigo` y `mensaje`, que es lo único que sirve
     * para corregir la guía y reintentar.
     *
     * @return array{aceptada: bool, codigo: string|null, mensaje: string|null, cdr: string|null}
     */
    public function consultar(string $ticket): array
    {
        $estado = $this->cpeApi()->consultarEnvio($ticket);
        $error = $estado->getError();

        return [
            // Código 0 es "procesado correctamente"; cualquier otro es rechazo
            // o proceso incompleto.
            'aceptada' => $estado->getCodRespuesta() === '0',
            'codigo' => $error?->getNumError(),
            'mensaje' => $error?->getDesError(),
            'cdr' => $estado->getArcCdr(),
        ];
    }

    /**
     * SUNAT recibe el XML dentro de un zip que lleva su mismo nombre.
     */
    private function comprimir(string $nombreArchivo, string $xml): string
    {
        $ruta = tempnam(sys_get_temp_dir(), 'gre').'.zip';
        $zip = new ZipArchive;

        if ($zip->open($ruta, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No se pudo crear el zip del comprobante.');
        }

        $zip->addFromString("{$nombreArchivo}.xml", $xml);
        $zip->close();

        $contenido = (string) file_get_contents($ruta);
        unlink($ruta);

        return $contenido;
    }

    private function cpeApi(): CpeApi
    {
        // Instancia propia y no `getDefaultConfiguration()`: esa es un singleton
        // compartido, y token y envío apuntan a hosts distintos.
        $configuracion = (new Configuration)
            ->setAccessToken($this->token())
            ->setHost((string) config('gre.endpoints.cpe'));

        return new CpeApi($this->http, $configuracion);
    }

    /**
     * Token OAuth2. SUNAT combina el RUC con el usuario secundario SOL para
     * formar el nombre de usuario.
     */
    private function token(): string
    {
        $credenciales = config('gre.api');

        foreach (['client_id', 'client_secret', 'usuario_sol', 'clave_sol'] as $clave) {
            if (blank($credenciales[$clave] ?? null)) {
                throw new RuntimeException(
                    "Falta la credencial gre.api.{$clave}. Se genera en el Menú SOL, ".
                    'en la opción de credenciales de API para Guía de Remisión.'
                );
            }
        }

        $autenticacion = (new Configuration)
            ->setHost((string) config('gre.endpoints.auth'));

        $token = (new AuthApi($this->http, $autenticacion))->getToken(
            'password',
            self::SCOPE,
            $credenciales['client_id'],
            $credenciales['client_secret'],
            config('gre.emisor.ruc').$credenciales['usuario_sol'],
            $credenciales['clave_sol'],
        );

        return (string) $token->getAccessToken();
    }
}
