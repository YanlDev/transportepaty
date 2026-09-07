<?php

namespace App\Services;

use Greenter\Model\Despatch\Despatch;
use Greenter\Xml\Builder\DespatchBuilder;
use Greenter\XMLSecLibs\Sunat\SignedXml;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Convierte el documento en el XML UBL 2.1 firmado que SUNAT espera recibir.
 *
 * Se usa el constructor de XML y el firmador por separado en vez de la fachada
 * `See` de Greenter: esa instancia un cliente SOAP en su constructor, y la GRE
 * viaja por API REST. Evitarla ahorra la extensión `ext-soap` en el servidor.
 */
class FirmadorGuiaTransportista
{
    public function __construct(
        private readonly DespatchBuilder $constructor = new DespatchBuilder,
        private readonly SignedXml $firmador = new SignedXml,
    ) {}

    /**
     * El XML firmado, listo para comprimir y enviar.
     */
    public function firmar(Despatch $guia): string
    {
        $this->firmador->setCertificate($this->certificado());

        return $this->firmador->signXml($this->constructor->build($guia));
    }

    /**
     * El XML sin firma. Sirve para revisar la estructura sin necesidad de
     * tener el certificado digital instalado.
     */
    public function generar(Despatch $guia): string
    {
        return $this->constructor->build($guia);
    }

    /**
     * Nombre con el que SUNAT identifica el comprobante: RUC, tipo, serie y
     * correlativo. Es también el nombre del zip y del CDR de respuesta.
     */
    public function nombreArchivo(Despatch $guia): string
    {
        return $guia->getName();
    }

    private function certificado(): string
    {
        $ruta = (string) config('gre.certificado');

        if (! Storage::disk('local')->exists($ruta)) {
            throw new RuntimeException(
                "No se encuentra el certificado digital en el disco privado: {$ruta}. ".
                'Sin certificado se puede generar el XML pero no firmarlo.'
            );
        }

        return (string) Storage::disk('local')->get($ruta);
    }
}
