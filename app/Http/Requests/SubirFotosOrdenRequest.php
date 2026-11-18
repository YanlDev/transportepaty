<?php

namespace App\Http\Requests;

use App\Services\Archivos\ReglasDeArchivo;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * Fotos que el mecánico suma mientras trabaja, al grupo de antes o al de
 * después.
 */
class SubirFotosOrdenRequest extends IniciarOrdenRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $coleccion = in_array($this->input('coleccion'), ['antes', 'despues'], true) ? $this->input('coleccion') : 'antes';

        return [
            'coleccion' => ['required', Rule::in(['antes', 'despues'])],
            'fotos' => ['required', 'array', 'min:1', 'max:'.$this->cupoDeFotos($coleccion)],
            'fotos.*' => ReglasDeArchivo::imagen(),
        ];
    }
}
