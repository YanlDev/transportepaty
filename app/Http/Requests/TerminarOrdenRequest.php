<?php

namespace App\Http\Requests;

use App\Enums\TipoOrdenTrabajo;
use App\Models\OrdenTrabajo;
use App\Services\Archivos\ReglasDeArchivo;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * El mecánico termina una orden: fotos de cómo quedó, una nota si hace
 * falta y, en una preventiva, el km (desde ahí se cuenta el próximo
 * servicio). La orden pasa a taller para su revisión.
 */
class TerminarOrdenRequest extends IniciarOrdenRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var OrdenTrabajo $orden */
        $orden = $this->route('orden');

        return [
            'fotos' => ['nullable', 'array', 'max:'.$this->cupoDeFotos('despues')],
            'fotos.*' => ReglasDeArchivo::imagen(),
            'nota' => ['nullable', 'string', 'max:2000'],
            'kilometraje' => [
                Rule::requiredIf($orden->tipo === TipoOrdenTrabajo::Preventiva && $orden->kilometraje === null),
                'nullable', 'integer', 'min:1', 'max:9999999',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...parent::messages(),
            'kilometraje.required' => 'Anota el km de la unidad: el próximo servicio se cuenta desde acá.',
        ];
    }
}
