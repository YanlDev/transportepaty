<?php

namespace App\Services\Imagenes;

use App\Models\AreaAviso;
use App\Models\Programacion;
use App\Services\AvisoDeSalida;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

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
     * La advertencia de documentación, en bloques cortos.
     *
     * No se arma del texto largo que se manda por chat: ese se lee de corrido
     * y como imagen quedaba un muro de párrafos. Acá cada regla es un bloque
     * que se entiende de un vistazo, que es como se mira una foto en el
     * celular antes de subirse a la unidad.
     */
    public function advertencia(): string
    {
        $huella = sha1(implode('|', [
            $this->aviso->telefonoOficina(),
            md5_file(__FILE__),
            md5_file(__DIR__.'/Lienzo.php'),
        ]));

        return Cache::rememberForever("aviso-png:advertencia:{$huella}", fn (): string => $this->dibujarAdvertencia());
    }

    /**
     * Se dibuja una sola vez por teléfono de oficina y versión del diseño
     * (ver `advertencia()`): es igual para todas las salidas.
     */
    private function dibujarAdvertencia(): string
    {
        $lienzo = $this->lienzo()
            ->banda('PROHIBIDO INICIAR EL VIAJE SIN DOCUMENTACIÓN VALIDADA', self::ROJO)
            ->parrafo('ANTES DE SALIR DEBES TENER:', 18, '#64748b', Peso::SemiBold, espacioAntes: 40)
            ->vinetas([
                'Guía de Remisión del Remitente (GR)',
                'Guía de Remisión del Transportista (GRT)',
                'Placas, tu nombre, tu DNI y el destino, correctos en la guía',
            ], 24)
            ->recuadro('Si falta un documento', 'Llama y espera', 'Esperar no es falta; salir sin documentos sí lo es.', '#fee2e2', self::ROJO)
            ->recuadro('¿No corresponde GRT?', 'Lo valida Programación', 'No lo determines por tu cuenta.', '#fef3c7', '#92400e')
            ->recuadro('Multa SUNAT', 'Hasta 4 UIT', 'Más retención del vehículo y de la carga. Si igual inicias el viaje, la paga el conductor.', '#f1f5f9', '#334155')
            ->espacio(48);

        $oficina = $this->aviso->telefonoOficina();

        if ($oficina) {
            $lienzo->banda("Oficina: {$oficina}", self::AZUL, tamano: 26);
        }

        return $lienzo->png();
    }

    /**
     * El aviso a un área de la casa: la salida con lo que hace falta para
     * prepararla y, si el área lo ve, el flete acordado.
     */
    public function area(Programacion $programacion, AreaAviso $area): string
    {
        $lienzo = $this->datosDeSalida($programacion, 'UNIDAD PROGRAMADA · '.mb_strtoupper($area->nombre));

        if (! $area->ve_flete) {
            return $lienzo->espacio(48)->png();
        }

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

    /**
     * Las unidades programadas para hoy que todavía no tienen GR, para que
     * alguien la gestione antes de que el conductor se quede esperando o,
     * peor, salga sin documentos.
     *
     * @param  iterable<Programacion>  $sinGr
     */
    public function recordatorioSinGr(\DateTimeInterface $fecha, iterable $sinGr): string
    {
        $lienzo = $this->lienzo($fecha)->banda('UNIDADES SIN GR', self::ROJO);
        $cantidad = 0;

        foreach ($sinGr as $programacion) {
            $cantidad++;
            $lienzo->filaUnidad(
                $programacion->vehiculo->placa,
                "{$programacion->cliente->alias} · {$programacion->destino} · {$this->nombreConductor($programacion)}",
            );
        }

        return $lienzo
            ->espacio(40)
            ->banda(
                $cantidad === 1
                    ? '1 unidad programada para hoy sigue sin GR: no puede salir hasta emitirla.'
                    : "{$cantidad} unidades programadas para hoy siguen sin GR: ninguna puede salir hasta emitirla.",
                self::AZUL,
                tamano: 22,
            )
            ->png();
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
