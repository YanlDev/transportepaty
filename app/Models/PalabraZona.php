<?php

namespace App\Models;

use App\Enums\ZonaRuta;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Una palabra de la dirección de la GR que delata una zona («ANTAUTA» →
 * mina, «PARACAS» → Pisco). Agregar una es enseñarle al sistema un lugar
 * nuevo sin tocar código.
 *
 * @property int $id
 * @property ZonaRuta $zona
 * @property string $palabra
 */
#[Fillable(['zona', 'palabra'])]
class PalabraZona extends Model
{
    protected $table = 'palabras_zona';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['zona' => ZonaRuta::class];
    }
}
