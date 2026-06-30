<?php

namespace App\Rules;

use App\Models\Conductor;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Ensures the selected driver belongs to the chosen branch.
 */
class ConductorPerteneceASucursal implements ValidationRule
{
    public function __construct(private int|string|null $sucursalId) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value) {
            return;
        }

        $perteneceASucursal = Conductor::query()
            ->whereKey($value)
            ->where('sucursal_id', $this->sucursalId)
            ->exists();

        if (! $perteneceASucursal) {
            $fail('El conductor seleccionado no pertenece a la sucursal indicada.');
        }
    }
}
