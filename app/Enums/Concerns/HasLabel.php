<?php

namespace App\Enums\Concerns;

/**
 * Provides a human-readable label and a frontend-friendly options list
 * for string-backed enums.
 */
trait HasLabel
{
    /**
     * The human-readable label for the case.
     */
    abstract public function label(): string;

    /**
     * The cases mapped to value/label pairs for select inputs.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case): array => [
                'value' => $case->value,
                'label' => $case->label(),
            ],
            self::cases(),
        );
    }
}
