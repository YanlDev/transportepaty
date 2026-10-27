<?php

namespace App\Models;

use App\Models\Concerns\RegistraHistorial;
use Database\Factories\LecturaKilometrajeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * El kilometraje de una unidad, anotado al llegar a base. De acá sale el
 * «km actual» con que se calcula cuánto falta para cada servicio. La foto
 * del tablero es opcional y sirve para revisar una lectura dudosa.
 *
 * @property int $id
 * @property int $vehiculo_id
 * @property int $kilometraje
 * @property Carbon $leido_en
 * @property int|null $user_id
 * @property string|null $observacion
 * @property-read Vehiculo $vehiculo
 * @property-read User|null $user
 */
#[Fillable(['vehiculo_id', 'kilometraje', 'leido_en', 'user_id', 'observacion'])]
class LecturaKilometraje extends Model implements HasMedia
{
    /** @use HasFactory<LecturaKilometrajeFactory> */
    use HasFactory, RegistraHistorial;

    use InteractsWithMedia;

    protected $table = 'lecturas_kilometraje';

    /**
     * @return BelongsTo<Vehiculo, $this>
     */
    public function vehiculo(): BelongsTo
    {
        return $this->belongsTo(Vehiculo::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('foto')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kilometraje' => 'integer',
            'leido_en' => 'datetime',
        ];
    }
}
