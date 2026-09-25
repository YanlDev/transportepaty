<?php

namespace App\Services\Imagenes;

/** El grosor de la letra, cada uno con su archivo de Instrument Sans. */
enum Peso: string
{
    case Regular = 'Regular';
    case SemiBold = 'SemiBold';
    case Bold = 'Bold';

    public function archivo(): string
    {
        return resource_path("fonts/InstrumentSans-{$this->value}.ttf");
    }
}
