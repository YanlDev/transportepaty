<?php

namespace Database\Factories;

use App\Enums\TipoAlerta;
use App\Models\ConfiguracionAlerta;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConfiguracionAlerta>
 */
class ConfiguracionAlertaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tipo' => TipoAlerta::TicketProgramado,
            'activa' => true,
            'por_app' => true,
            'por_whatsapp' => false,
            'roles' => [],
            'usuarios' => [],
            'areas' => [],
            'hora' => null,
        ];
    }
}
