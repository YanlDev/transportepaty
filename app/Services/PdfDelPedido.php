<?php

namespace App\Services;

use App\Models\ValeCompra;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as Documento;

/**
 * El PDF del pedido de compra aprobado: el vale firmado, con su QR para
 * verificarlo. Se descarga desde el pedido y se manda por WhatsApp.
 */
class PdfDelPedido
{
    public function __construct(private readonly CodigoQr $qr) {}

    public function documento(ValeCompra $vale): Documento
    {
        $vale->load(['proveedor', 'solicitante:id,name', 'aprobador:id,name', 'detalles.articulo', 'vehiculo:id,placa', 'ordenTrabajo']);

        $url = route('vales.verificar', $vale->codigo_publico);

        return Pdf::loadView('pdf.vale-compra', [
            'vale' => $vale,
            'url' => $url,
            'urlManual' => route('vales.verificar.formulario'),
            'qr' => $this->qr->celdas($url),
        ]);
    }

    /** El archivo, listo para adjuntar. */
    public function binario(ValeCompra $vale): string
    {
        return $this->documento($vale)->output();
    }

    public function nombre(ValeCompra $vale): string
    {
        return "pedido-de-compra-{$vale->numero()}.pdf";
    }
}
