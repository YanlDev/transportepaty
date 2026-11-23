<?php

namespace App\Http\Requests\Almacen;

use App\Enums\MedioPago;
use App\Models\ValeCompra;
use App\Services\Archivos\ReglasDeArchivo;
use App\Services\UsoDeVale;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Anotar un pago al proveedor. El dinero ya salió del banco: acá queda
 * cuándo, cuánto, de qué cuenta, el número de operación y el voucher. El
 * efectivo no lleva operación ni voucher.
 *
 * Cuando lo pagado salió en varias operaciones (dos depósitos, dos
 * transferencias) llegan en «operaciones», cada una con su monto, su número
 * y su voucher: se anota un pago por operación.
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

        $comunes = [
            'fecha' => ['required', 'date', 'before_or_equal:today'],
            'medio' => ['required', Rule::enum(MedioPago::class)],
            'cuenta_bancaria_id' => ['nullable', 'integer', Rule::exists('cuentas_bancarias', 'id')],
            'observacion' => ['nullable', 'string', 'max:500'],
        ];

        if ($this->vieneEnOperaciones()) {
            return [
                ...$comunes,
                'operaciones' => ['required', 'array', 'min:1', 'max:10'],
                'operaciones.*.monto' => ['required', 'numeric', 'gt:0', 'max:99999999'],
                'operaciones.*.numero_operacion' => [$porBanco, 'nullable', 'string', 'max:60', 'distinct:ignore_case'],
                'operaciones.*.voucher' => [$porBanco, 'nullable', ...ReglasDeArchivo::imagenOPdf()],
            ];
        }

        return [
            ...$comunes,
            'monto' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'numero_operacion' => [$porBanco, 'nullable', 'string', 'max:60'],
            'voucher' => [$porBanco, 'nullable', ...ReglasDeArchivo::imagenOPdf()],
        ];
    }

    /**
     * Los pagos a anotar: uno por operación, o el único del formulario simple.
     *
     * @return list<array{monto: float, numero_operacion: string|null, voucher: UploadedFile|null}>
     */
    public function pagos(): array
    {
        if (! $this->vieneEnOperaciones()) {
            return [[
                'monto' => (float) $this->input('monto'),
                'numero_operacion' => $this->input('numero_operacion'),
                'voucher' => $this->file('voucher'),
            ]];
        }

        return collect(array_keys((array) $this->input('operaciones')))
            ->map(fn (int|string $indice): array => [
                'monto' => (float) $this->input("operaciones.{$indice}.monto"),
                'numero_operacion' => $this->input("operaciones.{$indice}.numero_operacion"),
                'voucher' => $this->file("operaciones.{$indice}.voucher"),
            ])
            ->values()
            ->all();
    }

    /**
     * Lo común a todas las operaciones: fecha, medio, cuenta y observación.
     *
     * @return array<string, mixed>
     */
    public function datosComunes(): array
    {
        return $this->safe()->only(['fecha', 'medio', 'cuenta_bancaria_id', 'observacion']);
    }

    private function vieneEnOperaciones(): bool
    {
        return $this->has('operaciones');
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

                $total = round(array_sum(array_column($this->pagos(), 'monto')), 2);

                if ($total - $falta > UsoDeVale::tolerancia()) {
                    $validator->errors()->add($this->vieneEnOperaciones() ? 'operaciones' : 'monto', sprintf(
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
            'operaciones.*.monto.required' => 'Pon el monto de esta operación.',
            'operaciones.*.monto.gt' => 'El monto tiene que ser mayor que cero.',
            'operaciones.*.numero_operacion.required' => 'Pon el número de operación del voucher.',
            'operaciones.*.numero_operacion.distinct' => 'Este número de operación está repetido.',
            'operaciones.*.voucher.required' => 'Sube la foto o el PDF del voucher.',
            'operaciones.*.voucher.mimetypes' => 'El voucher tiene que ser un PDF o una foto (JPG, PNG, WEBP o HEIC).',
            'numero_operacion.required' => 'Pon el número de operación del voucher.',
            'voucher.required' => 'Sube la foto o el PDF del voucher.',
            'voucher.mimetypes' => 'El voucher tiene que ser un PDF o una foto (JPG, PNG, WEBP o HEIC).',
        ];
    }
}
