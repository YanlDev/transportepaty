<?php

namespace App\Services;

use App\Models\PuntoTraslado;
use App\Models\Viaje;
use Greenter\Model\Client\Client;
use Greenter\Model\Company\Address;
use Greenter\Model\Company\Company;
use Greenter\Model\Despatch\AdditionalDoc;
use Greenter\Model\Despatch\Despatch;
use Greenter\Model\Despatch\DespatchDetail;
use Greenter\Model\Despatch\Direction;
use Greenter\Model\Despatch\Driver;
use Greenter\Model\Despatch\Shipment;
use Greenter\Model\Despatch\Vehicle;

/**
 * Arma la Guía de Remisión Electrónica del Transportista (tipo 31) a partir de
 * un viaje.
 *
 * La GRE-T no repite el detalle de la carga: los lotes, sacos y humedades van
 * en la guía del remitente y acá solo se referencia. La representación impresa
 * lo refleja —solo muestra peso bruto y la GRR—, pero el esquema UBL igual
 * exige una línea de bienes, así que se declara una sola, genérica.
 */
class ConstructorGuiaTransportista
{
    /** Transporte público: la modalidad de toda guía del transportista. */
    private const MODALIDAD_TRANSPORTE_PUBLICO = '01';

    /** Guía de Remisión Transportista en el catálogo 01 de SUNAT. */
    private const TIPO_DOCUMENTO = '31';

    /** RUC en el catálogo 06 de documentos de identidad. */
    private const DOC_RUC = '6';

    /** DNI en el catálogo 06. */
    private const DOC_DNI = '1';

    /**
     * Qué le falta al viaje para poder emitirse. Se devuelve la lista completa
     * y no el primer error: quien corrige los datos prefiere verlos todos de
     * una vez antes que descubrirlos de a uno.
     *
     * @return list<string>
     */
    public function faltantes(Viaje $viaje): array
    {
        $faltantes = [];

        foreach (['ruc', 'razon_social', 'registro_mtc', 'direccion', 'ubigeo'] as $clave) {
            if (blank(config("gre.emisor.{$clave}"))) {
                $faltantes[] = "Falta configurar el dato del emisor: gre.emisor.{$clave}";
            }
        }

        if ($viaje->punto_partida_id === null) {
            $faltantes[] = 'Falta el punto de partida (la GRE exige su ubigeo)';
        }

        if ($viaje->punto_llegada_id === null) {
            $faltantes[] = 'Falta el punto de llegada (la GRE exige su ubigeo)';
        }

        if (blank($viaje->destinatario_ruc)) {
            $faltantes[] = 'Falta el RUC del destinatario';
        }

        if (blank($viaje->cliente_ruc)) {
            $faltantes[] = 'Falta el RUC del remitente';
        }

        if ((float) $viaje->peso <= 0) {
            $faltantes[] = 'El peso bruto debe ser mayor que cero';
        }

        $faltantes = [...$faltantes, ...$this->faltantesDelConductor($viaje)];

        return [...$faltantes, ...$this->faltantesDelVehiculo($viaje)];
    }

    /**
     * @return list<string>
     */
    private function faltantesDelConductor(Viaje $viaje): array
    {
        if ($viaje->conductor === null) {
            return ['El viaje no está vinculado a un conductor del padrón'];
        }

        if (blank($viaje->conductor->licencia)) {
            return ["El conductor {$viaje->conductor->nombres} no tiene licencia registrada"];
        }

        return [];
    }

    /**
     * @return list<string>
     */
    private function faltantesDelVehiculo(Viaje $viaje): array
    {
        if ($viaje->tracto === null) {
            return ['El viaje no está vinculado a un tracto del padrón'];
        }

        $faltantes = [];

        if (blank($viaje->tracto->tuc)) {
            $faltantes[] = "El tracto {$viaje->tracto->placa} no tiene TUC registrado";
        }

        // La carreta es opcional, pero si está declarada SUNAT también le pide
        // su certificado de habilitación.
        if ($viaje->carreta !== null && blank($viaje->carreta->tuc)) {
            $faltantes[] = "La carreta {$viaje->carreta->placa} no tiene TUC registrado";
        }

        return $faltantes;
    }

    /**
     * @throws \RuntimeException si al viaje le falta algún dato obligatorio
     */
    public function construir(Viaje $viaje, string $correlativo): Despatch
    {
        $faltantes = $this->faltantes($viaje);

        if ($faltantes !== []) {
            throw new \RuntimeException(
                "El viaje {$viaje->numero_gr} no se puede emitir:\n- ".implode("\n- ", $faltantes)
            );
        }

        $viaje->loadMissing(['tracto', 'carreta', 'conductor', 'puntoPartida', 'puntoLlegada']);

        return (new Despatch)
            ->setVersion('2022')
            ->setTipoDoc(self::TIPO_DOCUMENTO)
            ->setSerie((string) config('gre.serie'))
            ->setCorrelativo($correlativo)
            ->setFechaEmision($viaje->fecha_emision->toDateTimeImmutable())
            ->setCompany($this->emisor())
            ->setDestinatario($this->contribuyente($viaje->destinatario_ruc, $viaje->destinatario))
            ->setTercero($this->contribuyente($viaje->cliente_ruc, $viaje->cliente))
            ->setEnvio($this->envio($viaje))
            ->setAddDocs($this->guiasDelRemitente($viaje))
            ->setDetails([$this->bienTransportado($viaje)]);
    }

    /**
     * El emisor de la GRE-T es la transportista, no el dueño de la carga.
     */
    private function emisor(): Company
    {
        return (new Company)
            ->setRuc((string) config('gre.emisor.ruc'))
            ->setRazonSocial((string) config('gre.emisor.razon_social'))
            ->setNombreComercial((string) config('gre.emisor.nombre_comercial'))
            ->setAddress((new Address)
                ->setDireccion((string) config('gre.emisor.direccion'))
                ->setUbigueo((string) config('gre.emisor.ubigeo')));
    }

    private function contribuyente(string $ruc, string $razonSocial): Client
    {
        return (new Client)
            ->setTipoDoc(self::DOC_RUC)
            ->setNumDoc($ruc)
            ->setRznSocial($razonSocial);
    }

    private function envio(Viaje $viaje): Shipment
    {
        return (new Shipment)
            ->setCodTraslado($viaje->motivo_traslado->value)
            ->setModTraslado(self::MODALIDAD_TRANSPORTE_PUBLICO)
            // Greenter tipa este campo como DateTime mutable, a diferencia de
            // la fecha de emisión que acepta cualquier DateTimeInterface.
            ->setFecTraslado(new \DateTime($viaje->fecha_traslado->toDateString()))
            ->setPesoTotal((float) $viaje->peso)
            ->setUndPesoTotal($viaje->unidad_peso)
            ->setPartida($this->direccion($viaje->puntoPartida))
            ->setLlegada($this->direccion($viaje->puntoLlegada))
            ->setVehiculo($this->vehiculo($viaje))
            ->setChoferes([$this->chofer($viaje)]);
    }

    private function direccion(PuntoTraslado $punto): Direction
    {
        $direccion = new Direction($punto->ubigeo, $punto->direccion);

        if ($punto->tieneEstablecimiento()) {
            $direccion->setRuc($punto->ruc)->setCodLocal($punto->cod_local);
        }

        return $direccion;
    }

    private function vehiculo(Viaje $viaje): Vehicle
    {
        $vehiculo = (new Vehicle)
            ->setPlaca($viaje->tracto->placa)
            ->setNroCirculacion($viaje->tracto->tuc)
            ->setNroAutorizacion((string) config('gre.emisor.registro_mtc'));

        if ($viaje->carreta !== null) {
            $vehiculo->setSecundarios([
                (new Vehicle)
                    ->setPlaca($viaje->carreta->placa)
                    ->setNroCirculacion($viaje->carreta->tuc),
            ]);
        }

        return $vehiculo;
    }

    private function chofer(Viaje $viaje): Driver
    {
        return (new Driver)
            ->setTipo('Principal')
            ->setTipoDoc(self::DOC_DNI)
            ->setNroDoc($viaje->conductor->documento)
            ->setNombres($viaje->conductor->nombres)
            ->setApellidos($viaje->conductor->apellidos)
            ->setLicencia($viaje->conductor->licencia);
    }

    /**
     * La línea de bienes del documento.
     *
     * El esquema UBL exige al menos un `DespatchLine`, aunque la representación
     * impresa de la GRE-T no lo muestre: ahí solo salen el peso bruto y la guía
     * del remitente. Como el desglose real (lotes, sacos, humedad) vive en esa
     * guía y no en la del transportista, se declara una sola línea genérica con
     * el tipo de carga; lo que sustenta el traslado es el documento relacionado.
     */
    private function bienTransportado(Viaje $viaje): DespatchDetail
    {
        return (new DespatchDetail)
            ->setCantidad(1)
            // ZZ es la unidad genérica del catálogo 03 de SUNAT.
            ->setUnidad('ZZ')
            ->setDescripcion($viaje->tipo_carga->label())
            ->setCodigo($viaje->tipo_carga->value);
    }

    /**
     * Las guías del remitente que sustentan el traslado. Van como documento
     * relacionado de tipo 09 y son las que llevan el detalle real de la carga.
     *
     * @return list<AdditionalDoc>
     */
    private function guiasDelRemitente(Viaje $viaje): array
    {
        $documentos = [];

        foreach ($viaje->guias_remitente ?? [] as $guia) {
            $documentos[] = (new AdditionalDoc)
                ->setTipo('09')
                ->setNro(str_replace(' ', '', $guia['numero']))
                ->setEmisor($guia['ruc']);
        }

        return $documentos;
    }
}
