<?php

namespace App\Http\Requests;

use App\Models\Abastecimiento;
use App\Models\CapacidadTanque;
use App\Services\Archivos\ReglasDeArchivo;
use App\Services\OdometroDeUnidad;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Lo que de verdad cargó la unidad, según el voucher del grifo: los galones,
 * el precio, el kilometraje y la foto del voucher (obligatoria; al corregir
 * se conserva la que ya tiene). Puede ser distinto de lo autorizado; la
 * diferencia se ve en la liquidación del ticket. En el tanque full el
 * kilometraje es obligatorio: es lo único que dice cuánto rindió. Un
 * voucher que no cabe en el tanque de la marca pide confirmarlo.
 */
class RegistrarDespachoRequest extends FormRequest
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
            'galones_despachados' => ['required', 'numeric', 'min:0.1', 'max:999'],
            'precio_galon' => ['required', 'numeric', 'min:0.01', 'max:99'],
            'kilometraje' => [$this->abastecimiento()->tanque_lleno ? 'required' : 'nullable', 'integer', 'min:1', 'max:9999999'],
            'despachado_el' => ['required', 'date'],
            'foto_voucher' => [$this->abastecimiento()->hasMedia('voucher') ? 'nullable' : 'required', ...ReglasDeArchivo::imagen()],
            'confirmar_capacidad' => ['boolean'],
        ];
    }

    /**
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $abastecimiento = $this->abastecimiento();
                $capacidad = CapacidadTanque::de($abastecimiento->vehiculo->marca);

                if ($capacidad !== null && $this->float('galones_despachados') > $capacidad && ! $this->boolean('confirmar_capacidad')) {
                    $validator->errors()->add('galones_despachados', 'No cabe en el tanque de un '.mb_convert_case((string) $abastecimiento->vehiculo->marca, MB_CASE_TITLE).' ('.rtrim(rtrim(number_format($capacidad, 1, '.', ''), '0'), '.').' gal). Revisa el voucher; si está bien, confírmalo.');

                    return;
                }

                if (! $this->filled('kilometraje')) {
                    return;
                }

                // Corregir el mismo despacho no compite contra sí mismo.
                if ($abastecimiento->kilometraje === $this->integer('kilometraje')) {
                    return;
                }

                $error = app(OdometroDeUnidad::class)->error($abastecimiento->vehiculo, $this->integer('kilometraje'), OdometroDeUnidad::deDespacho($abastecimiento->id));

                if ($error !== null) {
                    $validator->errors()->add('kilometraje', $error);
                }
            },
        ];
    }

    private function abastecimiento(): Abastecimiento
    {
        /** @var Abastecimiento $abastecimiento */
        $abastecimiento = $this->route('abastecimiento');

        return $abastecimiento;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'galones_despachados' => 'galones despachados',
            'precio_galon' => 'precio por galón',
            'despachado_el' => 'fecha del voucher',
            'foto_voucher' => 'foto del voucher',
        ];
    }
}
