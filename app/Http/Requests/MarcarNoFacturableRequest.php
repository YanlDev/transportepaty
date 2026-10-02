<?php

namespace App\Http\Requests;

use App\Models\Viaje;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Marcar una GR como «no se factura», o devolverla a la cobranza. El motivo es
 * opcional pero se guarda: en tres meses nadie recuerda por qué esa GR no se
 * cobró.
 */
class MarcarNoFacturableRequest extends FormRequest
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
            'no_facturable' => ['required', 'boolean'],
            'motivo' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Una GR ya facturada no puede ser «no facturable» a la vez: primero se
     * saca de sus facturas, o el cobro quedaría apoyado en algo que se dijo
     * que no se cobra.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $viaje = $this->route('viaje');

                if ($this->boolean('no_facturable') && $viaje instanceof Viaje && $viaje->facturas()->exists()) {
                    $validator->errors()->add(
                        'no_facturable',
                        'Esta GR ya está facturada: quítala de sus facturas antes de marcarla como no facturable.',
                    );
                }
            },
        ];
    }
}
