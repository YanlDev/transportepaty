<?php

namespace App\Http\Requests;

use App\Models\Viaje;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Asociar a mano una factura con sus GR, desde la bandeja «Facturas por
 * asociar». Llegan los viajes marcados en la lista de candidatas y, aparte,
 * los números de GR escritos a mano para las que no salieron en la lista
 * (otro cliente, otra fecha).
 */
class AsociarFacturaRequest extends FormRequest
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
            'viaje_ids' => ['present', 'array'],
            // Una GR anulada no es un viaje y no se cobra.
            'viaje_ids.*' => ['integer', 'distinct', Rule::exists('viajes', 'id')->whereNull('anulada_at')],
            'numeros_gr' => ['present', 'array'],
            'numeros_gr.*' => ['string', 'max:30'],
        ];
    }

    /**
     * Al menos una GR, que los números escritos existan y que ninguna esté
     * marcada como «no se factura».
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $numeros = $this->numerosGr();
                $encontrados = Viaje::query()->whereIn('numero_gr', $numeros)->pluck('numero_gr');
                $faltantes = array_diff($numeros, $encontrados->all());

                if ($faltantes !== []) {
                    $validator->errors()->add('numeros_gr', 'No están en el sistema: '.implode(', ', $faltantes).'.');

                    return;
                }

                if ($this->viajes()->isEmpty()) {
                    $validator->errors()->add('viaje_ids', 'Elige al menos una GR.');

                    return;
                }

                $noFacturables = $this->viajes()->whereNotNull('no_facturable_at')->pluck('numero_gr');

                if ($noFacturables->isNotEmpty()) {
                    $validator->errors()->add('viaje_ids', 'Están marcadas como «no se factura»: '.$noFacturables->join(', ').'.');
                }
            },
        ];
    }

    /**
     * Los viajes a asociar: los marcados más los escritos por número.
     *
     * @return Collection<int, Viaje>
     */
    public function viajes(): Collection
    {
        return Viaje::query()
            ->whereIn('id', array_map('intval', (array) $this->input('viaje_ids', [])))
            ->orWhereIn('numero_gr', $this->numerosGr())
            ->get()
            ->toBase();
    }

    /**
     * Los números escritos a mano, en el formato de `viajes.numero_gr`: se
     * acepta `EG03-12429` o `EG03-00012429`.
     *
     * @return array<int, string>
     */
    private function numerosGr(): array
    {
        return collect((array) $this->input('numeros_gr', []))
            ->map(fn ($numero): string => strtoupper(trim((string) $numero)))
            ->filter()
            ->map(fn (string $numero): string => preg_match('/^([A-Z0-9]{4})-?(\d+)$/', $numero, $partes) === 1
                ? sprintf('%s-%08d', $partes[1], (int) $partes[2])
                : $numero)
            ->unique()
            ->values()
            ->all();
    }
}
