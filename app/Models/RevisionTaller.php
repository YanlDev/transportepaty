<?php

namespace App\Models;

use App\Enums\ResultadoTaller;
use App\Models\Concerns\RegistraHistorial;
use Database\Factories\RevisionTallerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Una revisión de taller sobre la unidad de un ticket. Si no es apta, taller
 * dice cuándo estará lista, por qué y qué se le va a hacer; cuando la
 * termina, la da por apta con los trabajos realizados. Vale la última.
 *
 * @property int $id
 * @property int $vuelta_id
 * @property ResultadoTaller $resultado
 * @property Carbon|null $disponible_estimado
 * @property string|null $motivo
 * @property string|null $trabajos
 * @property int|null $user_id
 * @property Carbon $created_at
 * @property-read Vuelta $vuelta
 * @property-read User|null $usuario
 */
#[Fillable(['vuelta_id', 'resultado', 'disponible_estimado', 'motivo', 'trabajos', 'user_id'])]
class RevisionTaller extends Model
{
    /** @use HasFactory<RevisionTallerFactory> */
    use HasFactory, RegistraHistorial;

    public function etiquetaHistorial(): ?string
    {
        return Vuelta::query()->whereKey($this->vuelta_id)->value('codigo');
    }

    protected $table = 'revisiones_taller';

    /**
     * @return BelongsTo<Vuelta, $this>
     */
    public function vuelta(): BelongsTo
    {
        return $this->belongsTo(Vuelta::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function esApta(): bool
    {
        return $this->resultado === ResultadoTaller::Apto;
    }

    /**
     * Lo que se muestra en el ticket. Necesita `usuario` cargado.
     *
     * @return array{resultado: string, resultado_label: string, disponible_estimado: string|null, motivo: string|null, trabajos: string|null, por: string|null, en: string}
     */
    public function resumen(): array
    {
        return [
            'resultado' => $this->resultado->value,
            'resultado_label' => $this->resultado->label(),
            'disponible_estimado' => $this->disponible_estimado?->format('Y-m-d H:i'),
            'motivo' => $this->motivo,
            'trabajos' => $this->trabajos,
            'por' => $this->usuario?->name,
            'en' => $this->created_at->format('Y-m-d H:i'),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'resultado' => ResultadoTaller::class,
            'disponible_estimado' => 'datetime',
        ];
    }
}
