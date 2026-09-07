<?php

namespace App\Services\Costos;

use App\Models\ParametroFlota;

/**
 * El costo del diésel por kilómetro, a partir de dónde se abastece la flota.
 *
 * El precio se pondera por localidad porque el galón no cuesta lo mismo en
 * Arequipa que en Lima y las unidades no cargan en un solo sitio. Va sin IGV:
 * el impuesto se recupera como crédito fiscal, así que meterlo en el costo
 * infla la tarifa por algo que la empresa no paga de verdad.
 *
 * El aditivo (UREA en las unidades Euro V) se cuenta aparte porque se consume
 * a un ritmo distinto del combustible.
 */
class MetodoCombustible implements Metodo
{
    use LeeEntradas;

    public function derivar(array $entradas, ParametroFlota $flota): Derivacion
    {
        $incluyeIgv = (bool) ($entradas['precio_incluye_igv'] ?? true);
        $divisorIgv = $incluyeIgv ? 1 + $flota->igv_pct : 1.0;

        $precioPonderado = 0.0;
        $participacion = 0.0;

        foreach ($this->filas($entradas, 'localidades') as $localidad) {
            $precioPonderado += $this->numero($localidad, 'participacion') * $this->numero($localidad, 'precio_galon');
            $participacion += $this->numero($localidad, 'participacion');
        }

        $precioSinIgv = $this->dividir($precioPonderado, $divisorIgv);
        $rendimiento = $this->numero($entradas, 'rendimiento_km_galon');
        $combustibleKm = $this->dividir($precioSinIgv, $rendimiento);

        $aditivoSinIgv = $this->dividir($this->numero($entradas, 'aditivo_precio_galon'), $divisorIgv);
        $aditivoKm = $this->dividir($aditivoSinIgv, $this->numero($entradas, 'aditivo_rendimiento_km_galon'));

        return new Derivacion($combustibleKm + $aditivoKm, [
            $this->paso('Participación cargada', $participacion, 'porcentaje'),
            $this->paso('Precio promedio del galón', $precioPonderado),
            $this->paso('Precio sin IGV', $precioSinIgv),
            $this->paso('Rendimiento', $rendimiento, 'numero'),
            $this->paso('Combustible por km', $combustibleKm),
            $this->paso('Aditivo por km', $aditivoKm),
        ]);
    }

    public function entradasPorDefecto(): array
    {
        return [
            'localidades' => [['nombre' => '', 'participacion' => 1, 'precio_galon' => 0]],
            'precio_incluye_igv' => true,
            'rendimiento_km_galon' => 0,
            'aditivo_precio_galon' => 0,
            'aditivo_rendimiento_km_galon' => 0,
        ];
    }

    public function reglas(): array
    {
        return [
            'localidades' => ['required', 'array', 'min:1'],
            'localidades.*.nombre' => ['nullable', 'string', 'max:100'],
            'localidades.*.participacion' => ['required', 'numeric', 'min:0', 'max:1'],
            'localidades.*.precio_galon' => ['required', 'numeric', 'min:0'],
            'precio_incluye_igv' => ['required', 'boolean'],
            'rendimiento_km_galon' => ['required', 'numeric', 'min:0.01'],
            'aditivo_precio_galon' => ['required', 'numeric', 'min:0'],
            'aditivo_rendimiento_km_galon' => ['required', 'numeric', 'min:0'],
        ];
    }
}
