<?php

namespace App\Http\Requests;

use App\Enums\SentidoTramo;
use App\Enums\TipoCarga;
use App\Enums\TipoLineaAbastecimiento;
use App\Enums\ZonaRuta;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Una tarjeta de la pizarra con sus filas. Las filas se mandan siempre
 * todas: al guardar, reemplazan a las anteriores.
 */
class GuardarReglaAbastecimientoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'max:20'],
            'sentido' => ['nullable', Rule::enum(SentidoTramo::class)],
            'zona_origen' => ['nullable', Rule::enum(ZonaRuta::class)],
            'zona_destino' => ['nullable', Rule::enum(ZonaRuta::class)],
            'tipo_carga' => ['nullable', Rule::enum(TipoCarga::class)],
            'lugares' => ['nullable', 'string', 'max:255'],
            'peso_desde' => ['nullable', 'numeric', 'min:0', 'max:99'],
            'peso_hasta' => ['nullable', 'numeric', 'min:0', 'max:99', 'gt:peso_desde'],
            'nota' => ['nullable', 'string', 'max:1000'],
            'activa' => ['boolean'],
            'lineas' => ['array', 'max:20'],
            'lineas.*.tipo' => ['required', Rule::enum(TipoLineaAbastecimiento::class)],
            'lineas.*.concepto' => ['required', 'string', 'max:100'],
            'lineas.*.punto_abastecimiento_id' => ['nullable', Rule::exists('puntos_abastecimiento', 'id')],
            'lineas.*.galones' => ['nullable', 'numeric', 'min:0', 'max:999'],
            'lineas.*.tanque_lleno' => ['boolean'],
            'lineas.*.monto' => ['nullable', 'numeric', 'min:0', 'max:99999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'peso_desde' => 'peso desde',
            'peso_hasta' => 'peso hasta',
            'lineas.*.concepto' => 'concepto',
            'lineas.*.galones' => 'galones',
            'lineas.*.monto' => 'monto',
        ];
    }
}
