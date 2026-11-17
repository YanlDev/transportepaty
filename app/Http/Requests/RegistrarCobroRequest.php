<?php

namespace App\Http\Requests;

use App\Enums\Moneda;
use App\Models\CuentaBancaria;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Un depósito y las facturas que paga. Cada factura recibe lo que se le
 * indique —el sistema propone lo que le falta, pero se puede poner menos—;
 * lo que sobre del depósito queda como saldo a favor del cliente, y por eso
 * entonces hace falta decir de quién es.
 */
class RegistrarCobroRequest extends FormRequest
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
            'fecha' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'cuenta_bancaria_id' => ['nullable', Rule::exists('cuentas_bancarias', 'id')->where('activa', true)],
            'numero_operacion' => ['nullable', 'string', 'max:30'],
            'monto' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'moneda' => ['required', Rule::enum(Moneda::class)],
            'cliente_id' => ['nullable', Rule::exists('clientes', 'id')],
            'observacion' => ['nullable', 'string', 'max:500'],
            'aplicaciones' => ['array', 'max:100'],
            'aplicaciones.*.factura_id' => ['required', 'integer', 'distinct', Rule::exists('facturas', 'id')->whereNull('anulada_at')],
            'aplicaciones.*.monto' => ['required', 'numeric', 'gt:0'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $repartido = round(array_sum($this->aplicaciones()), 2);
                $monto = round((float) $this->input('monto'), 2);

                if ($repartido > $monto + 0.005) {
                    $validator->errors()->add('monto', 'Lo repartido entre las facturas ('.number_format($repartido, 2).') pasa del monto del depósito.');
                }

                if ($repartido < $monto - 0.005 && blank($this->input('cliente_id'))) {
                    $validator->errors()->add('cliente_id', 'Sobra saldo sin repartir: indica de qué cliente es para dejarlo a su favor.');
                }

                $cuenta = filled($this->input('cuenta_bancaria_id')) ? CuentaBancaria::query()->find($this->integer('cuenta_bancaria_id')) : null;

                if ($cuenta !== null && $cuenta->moneda->value !== $this->input('moneda')) {
                    $validator->errors()->add('cuenta_bancaria_id', "La cuenta {$cuenta->alias} es en otra moneda.");
                }
            },
        ];
    }

    /**
     * Lo que le toca a cada factura.
     *
     * @return array<int, float>
     */
    public function aplicaciones(): array
    {
        return collect((array) $this->input('aplicaciones', []))
            ->mapWithKeys(fn (array $aplicacion): array => [(int) $aplicacion['factura_id'] => round((float) $aplicacion['monto'], 2)])
            ->all();
    }

    /**
     * @return array{fecha: string, cuenta_bancaria_id: int|null, numero_operacion: string|null, monto: float, moneda: string, cliente_id: int|null, observacion: string|null}
     */
    public function datos(): array
    {
        return [
            'fecha' => $this->string('fecha')->toString(),
            'cuenta_bancaria_id' => $this->filled('cuenta_bancaria_id') ? $this->integer('cuenta_bancaria_id') : null,
            'numero_operacion' => $this->filled('numero_operacion') ? $this->string('numero_operacion')->trim()->toString() : null,
            'monto' => round((float) $this->input('monto'), 2),
            'moneda' => $this->string('moneda')->toString(),
            'cliente_id' => $this->filled('cliente_id') ? $this->integer('cliente_id') : null,
            'observacion' => $this->filled('observacion') ? $this->string('observacion')->trim()->toString() : null,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'fecha' => 'fecha del depósito',
            'cuenta_bancaria_id' => 'cuenta',
            'numero_operacion' => 'N° de operación',
            'monto' => 'monto del depósito',
            'cliente_id' => 'cliente',
            'aplicaciones.*.monto' => 'monto de la factura',
            'aplicaciones.*.factura_id' => 'factura',
        ];
    }
}
