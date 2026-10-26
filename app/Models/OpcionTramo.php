<?php

namespace App\Models;

use App\Enums\SentidoTramo;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Lo que se elige al programar un tramo: la ida al salir («Concentrado a
 * Pisco») o la vuelta cuando se sabe con qué regresa la unidad («Escoria a
 * mina»). Al bajar no se sabe con qué vuelve, por eso se programa por tramo.
 *
 * Las paradas llevan zonas; la que tiene el destino vacío es la de carga
 * particular, cuyo destino se elige al programar (`pide_destino`).
 *
 * @property int $id
 * @property string $nombre
 * @property string|null $descripcion
 * @property SentidoTramo $sentido
 * @property list<array{zona_origen: string, zona_destino: string|null, tipo_carga: string}> $paradas
 * @property bool $pide_destino
 * @property bool $activa
 * @property int $orden
 */
#[Fillable(['nombre', 'descripcion', 'sentido', 'paradas', 'pide_destino', 'activa', 'orden'])]
class OpcionTramo extends Model
{
    protected $table = 'opciones_tramo';

    /**
     * @param  Builder<self>  $query
     */
    public function scopeEnOrden(Builder $query): void
    {
        $query->orderBy('orden')->orderBy('id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sentido' => SentidoTramo::class,
            'paradas' => 'array',
            'pide_destino' => 'boolean',
            'activa' => 'boolean',
            'orden' => 'integer',
        ];
    }
}
