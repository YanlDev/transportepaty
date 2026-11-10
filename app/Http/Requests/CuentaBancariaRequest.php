<?php

namespace App\Http\Requests;

use App\Enums\Moneda;
use App\Enums\TipoCuenta;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

abstract class CuentaBancariaRequest extends FormRequest
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
            'tipo' => ['required', Rule::enum(TipoCuenta::class)],
            'banco' => ['required', 'string', 'max:60'],
            'alias' => ['required', 'string', 'max:60'],
            // El formato del número varía por banco (guiones, largos
            // distintos), así que se valida el largo y nada más: rechazar uno
            // real por no encajar en un patrón sería peor que aceptarlo.
            // La caja no tiene número de cuenta ni CCI.
            'numero_cuenta' => ['nullable', 'required_if:tipo,'.TipoCuenta::Banco->value, 'prohibited_if:tipo,'.TipoCuenta::Caja->value, 'string', 'max:40', $this->reglaCuentaUnica()],
            'cci' => ['nullable', 'prohibited_if:tipo,'.TipoCuenta::Caja->value, 'string', 'max:40'],
            'moneda' => ['required', Rule::enum(Moneda::class)],
            'activa' => ['required', 'boolean'],
            'notas' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Sin tipo es una cuenta de banco, que es lo que eran todas. En una caja
     * no hay banco que escribir: se llama «Caja», y el número y el CCI vacíos
     * del formulario no cuentan como escritos.
     */
    protected function prepareForValidation(): void
    {
        $tipo = $this->input('tipo') ?? TipoCuenta::Banco->value;
        $esCaja = $tipo === TipoCuenta::Caja->value;

        $this->merge([
            'tipo' => $tipo,
            'banco' => $esCaja ? 'Caja' : $this->input('banco'),
            'numero_cuenta' => blank($this->input('numero_cuenta')) ? null : $this->input('numero_cuenta'),
            'cci' => blank($this->input('cci')) ? null : $this->input('cci'),
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'numero_cuenta.required_if' => 'El número de cuenta es obligatorio.',
            'numero_cuenta.prohibited_if' => 'La caja no lleva número de cuenta.',
            'cci.prohibited_if' => 'La caja no lleva CCI.',
        ];
    }

    abstract protected function reglaCuentaUnica(): Unique;
}
