<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un documento del almacén no se borra: se anula, con quién, cuándo y por
 * qué. Sigue a la vista en su listado, pero sus movimientos salen del kardex.
 *
 * Usa `anulado_at`, `anulado_por` y `motivo_anulacion`.
 *
 * @mixin Model
 */
trait SeAnula
{
    public function estaAnulado(): bool
    {
        return $this->anulado_at !== null;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function anuladoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'anulado_por');
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeVigentes(Builder $query): void
    {
        $query->whereNull($this->qualifyColumn('anulado_at'));
    }
}
