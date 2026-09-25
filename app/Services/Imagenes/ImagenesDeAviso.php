<?php

namespace App\Services\Imagenes;

use App\Models\Programacion;
use App\Services\AvisoDeSalida;
use Carbon\CarbonImmutable;

/**
 * Los avisos de Programación como imagen, con el logo y los colores de la
 * empresa. Los datos y las redacciones salen de `AvisoDeSalida`: acá solo se
 * decide cómo se ven.
 */
class ImagenesDeAviso
{
    private const AZUL = '#285b9f';

    private const ROJO = '#b91c1c';

    private const VERDE = '#15803d';

    public function __construct(private readonly AvisoDeSalida $aviso) {}

    /** El aviso de salida que recibe el conductor. */
    public function conductor(Programacion $programacion): string
    {
        return $this->lienzo($programacion->fecha)
            ->banda('PROGRAMACIÓN DE SALIDA', self::AZUL)
            ->destacado('Unidad', $programacion->vehiculo->placa)
            ->fila('Conductor', $this->nombreConductor($programacion))
            ->fila('Cliente', $programacion->cliente->alias)
            ->fila('Destino', $programacion->destino)
            ->espacio(48)
            ->banda('No inicies el viaje sin documentación validada.', self::ROJO, tamano: 24)
            ->png();
    }

    /**
     * La advertencia de documentación, armada del mismo texto que ya se
     * mandaba: la primera línea es el título, las que empiezan con «•» son
     * la lista y la última (la oficina) va al pie.
     */
    public function advertencia(): string
    {
        $lineas = explode("\n", $this->aviso->advertencia());
        $titulo = trim(array_shift($lineas), '* ');
        $pie = str_starts_with((string) end($lineas), 'Oficina:') ? array_pop($lineas) : null;

        $lienzo = $this->lienzo()->banda($titulo, self::ROJO)->espacio(16);
        $vinetas = [];

        foreach ($lineas as $linea) {
            if (str_starts_with($linea, '• ')) {
                $vinetas[] = mb_substr($linea, 2);

                continue;
            }

            if ($vinetas !== []) {
                $lienzo->vinetas($vinetas);
                $vinetas = [];
            }

            if (trim($linea) !== '') {
                $lienzo->parrafo($linea);
            }
        }

        $lienzo->espacio(48);

        if ($pie !== null) {
            $lienzo->banda($pie, self::AZUL, tamano: 24);
        }

        return $lienzo->png();
    }

    /** Lo que abastecimiento necesita para preparar la unidad. */
    public function abastecimiento(Programacion $programacion): string
    {
        return $this->datosDeSalida($programacion, 'UNIDAD PROGRAMADA · ABASTECIMIENTO')
            ->espacio(48)
            ->png();
    }

    /** Lo mismo para facturación, con el flete acordado. */
    public function facturacion(Programacion $programacion): string
    {
        $lienzo = $this->datosDeSalida($programacion, 'UNIDAD PROGRAMADA · FACTURACIÓN');

        if ($programacion->precio_flete === null) {
            $lienzo->recuadro('Flete', 'Sin precio acordado', null, '#fef3c7', '#92400e');
        } else {
            ['neto' => $neto, 'total' => $total] = $this->aviso->desglosarFlete($programacion);

            $programacion->precio_incluye_igv
                ? $lienzo->recuadro('Flete acordado', $this->soles($total), "IGV incluido · Neto {$this->soles($neto)}", '#dcfce7', self::VERDE)
                : $lienzo->recuadro('Flete acordado', $this->soles($neto).' + IGV', "Total con IGV {$this->soles($total)}", '#dcfce7', self::VERDE);
        }

        return $lienzo->espacio(48)->png();
    }

    private function datosDeSalida(Programacion $programacion, string $titulo): Lienzo
    {
        $lienzo = $this->lienzo($programacion->fecha)
            ->banda($titulo, self::AZUL)
            ->destacado('Unidad', $programacion->vehiculo->placa)
            ->fila('Conductor', $this->nombreConductor($programacion))
            ->fila('Cliente', $programacion->cliente->alias);

        $lugar = $this->aviso->lugarDeCarga($programacion);

        if ($lugar !== null) {
            $lienzo->fila('Carga en', $lugar);
        }

        return $lienzo->fila('Destino', $programacion->destino);
    }

    private function lienzo(?\DateTimeInterface $fecha = null): Lienzo
    {
        return (new Lienzo)->encabezado(
            public_path('marca/logo-horizontal.png'),
            $fecha === null ? null : ucfirst(CarbonImmutable::instance($fecha)->settings(['locale' => 'es'])->isoFormat('dddd D [de] MMMM')),
        );
    }

    private function nombreConductor(Programacion $programacion): string
    {
        return "{$programacion->conductor->nombres} {$programacion->conductor->apellidos}";
    }

    private function soles(float $monto): string
    {
        return 'S/ '.number_format($monto, 2);
    }
}
