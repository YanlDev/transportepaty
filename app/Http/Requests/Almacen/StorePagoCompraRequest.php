<?php

namespace App\Http\Requests\Almacen;

use App\Enums\MedioPago;
use App\Models\ValeCompra;
use App\Services\Archivos\ReglasDeArchivo;
use App\Services\UsoDeVale;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Anotar un pago al proveedor. El dinero ya salió del banco: acá queda
 * cuándo, cuánto, de qué cuenta, el número de operación y el voucher. El
 * efectivo no lleva operación ni voucher.
 */
class StorePagoCompraRequest extends FormRequest
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
        $porBanco = Rule::requiredIf(fn (): bool => MedioPago::tryFrom((string) $this->input('medio'))?->dejaVoucher() ?? false);

        return [
            'fecha' => ['required', 'date', 'before_or_equal:today'],
            'monto' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'medio' => ['required', Rule::enum(MedioPago::class)],
            'cuenta_bancaria_id' => ['nullable', 'integer', Rule::exists('cuentas_bancarias', 'id')],
            'numero_operacion' => [$porBanco, 'nullable', 'string', 'max:60'],
            'voucher' => [$porBanco, 'nullable', ...ReglasDeArchivo::imagenOPdf()],
            'observacion' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * No se paga más de lo que falta (con la tolerancia de redondeo).
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

                /** @var ValeCompra $vale */
                $vale = $this->route('vale');
                $falta = $vale->porPagar();

                if ((float) $this->input('monto') - $falta > UsoDeVale::tolerancia()) {
                    $validator->errors()->add('monto', sprintf(
                        'Al %s le faltan pagar %s %s: el pago no puede ser mayor.',
                        $vale->numero(),
                        $vale->moneda->simbolo(),
                        number_format($falta, 2),
                    ));
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fecha.before_or_equal' => 'La fecha del pago no puede ser futura.',
            'monto.gt' => 'El monto tiene que ser mayor que cero.',
            'numero_operacion.required' => 'Pon el número de operación del voucher.',
            'voucher.required' => 'Sube la foto o el PDF del voucher.',
            'voucher.mimetypes' => 'El voucher tiene que ser un PDF o una foto (JPG, PNG, WEBP o HEIC).',
        ];
    }
}
