<?php

namespace App\Http\Requests;

use App\Enums\SentidoTramo;
use App\Models\Ruta;
use App\Models\Vuelta;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Galones extra para un tramo de este ticket, con su motivo: un desvío que
 * no se repite (descargar en Lurín antes de subir). Lo agrega quien
 * programa; lo que se repite va como complemento del catálogo.
 */
class GuardarAdicionalRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Vuelta $vuelta */
        $vuelta = $this->route('vuelta');

        return $this->user()?->can('update', $vuelta) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'sentido' => ['required', Rule::enum(SentidoTramo::class)],
            // O una ruta de la tabla (la carga posterior que ya se conoce:
            // harina en Ilo, arroz en Camaná) o galones sueltos para lo que
            // no está en la tabla.
            'ruta_id' => ['nullable', 'integer', Rule::exists('rutas', 'id')->where('activa', true)],
            'variante_ruta_id' => ['nullable', 'integer', Rule::exists('variantes_ruta', 'id')->where('ruta_id', $this->integer('ruta_id'))],
            'galones' => ['nullable', 'required_without:ruta_id', 'prohibits:ruta_id', 'numeric', 'min:1', 'max:300'],
            'motivo' => ['required', 'string', 'min:3', 'max:180'],
        ];
    }

    /**
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty() || ! $this->filled('ruta_id')) {
                    return;
                }

                $ruta = $this->ruta();
                $sentido = $this->enum('sentido', SentidoTramo::class);

                if ($ruta !== null && $sentido !== null && ! $ruta->sirvePara($sentido)) {
                    $validator->errors()->add('ruta_id', "«{$ruta->nombre}» no es una ruta de {$sentido->label()}.");
                }
            },
        ];
    }

    /** La ruta de la tabla que se suma al tramo, si se eligió una. */
    public function ruta(): ?Ruta
    {
        return $this->filled('ruta_id')
            ? Ruta::query()->with(['lineas.punto', 'variantes'])->find($this->integer('ruta_id'))
            : null;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['sentido' => 'tramo', 'ruta_id' => 'ruta', 'variante_ruta_id' => 'variante'];
    }
}
