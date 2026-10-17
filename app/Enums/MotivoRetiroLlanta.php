<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum MotivoRetiroLlanta: string
{
    use HasLabel;

    case Desgaste = 'desgaste';
    case Dano = 'dano';
    case Reencauche = 'reencauche';
    case Otro = 'otro';

    public function label(): string
    {
        return match ($this) {
            self::Desgaste => 'Desgaste',
            self::Dano => 'Daño (corte, reventón…)',
            self::Reencauche => 'Va a reencauche',
            self::Otro => 'Otro',
        };
    }
}
