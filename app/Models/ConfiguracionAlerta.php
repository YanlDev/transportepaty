<?php

namespace App\Models;

use App\Enums\TipoAlerta;
use App\Models\Concerns\RegistraHistorial;
use Carbon\CarbonImmutable;
use Database\Factories\ConfiguracionAlertaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Cómo se avisa un tipo de alerta: si está prendida, por qué canales y a
 * quién. Un tipo que nadie configuró todavía no tiene fila: `para()` lo arma
 * con los valores por defecto de `TipoAlerta`, sin guardarlo.
 *
 * Los destinatarios se suman: los usuarios de los roles elegidos, los
 * usuarios sueltos y, solo por WhatsApp, los números de las áreas de aviso.
 *
 * @property int $id
 * @property TipoAlerta $tipo
 * @property bool $activa
 * @property bool $por_app
 * @property bool $por_whatsapp
 * @property list<string> $roles
 * @property list<int> $usuarios
 * @property list<int> $areas
 * @property string|null $hora
 * @property CarbonImmutable|null $ultimo_envio_at
 */
#[Fillable(['tipo', 'activa', 'por_app', 'por_whatsapp', 'roles', 'usuarios', 'areas', 'hora', 'ultimo_envio_at'])]
class ConfiguracionAlerta extends Model
{
    /** @use HasFactory<ConfiguracionAlertaFactory> */
    use HasFactory, RegistraHistorial;

    protected $table = 'configuracion_alertas';

    /**
     * La configuración guardada del tipo, o la de fábrica si nadie la tocó.
     */
    public static function para(TipoAlerta $tipo): self
    {
        return self::query()->where('tipo', $tipo->value)->first()
            ?? new self([
                'tipo' => $tipo,
                'activa' => true,
                'por_app' => true,
                'por_whatsapp' => false,
                'roles' => $tipo->rolesPorDefecto(),
                'usuarios' => [],
                'areas' => [],
                'hora' => $tipo->horaPorDefecto(),
            ]);
    }

    public function etiquetaHistorial(): ?string
    {
        return $this->tipo->label();
    }

    /**
     * Cuándo salió el último resumen lo anota el envío, no una persona.
     *
     * @return list<string>
     */
    protected function camposSinHistorial(): array
    {
        return ['ultimo_envio_at'];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo' => TipoAlerta::class,
            'activa' => 'boolean',
            'por_app' => 'boolean',
            'por_whatsapp' => 'boolean',
            'roles' => 'array',
            'usuarios' => 'array',
            'areas' => 'array',
            'ultimo_envio_at' => 'datetime',
        ];
    }
}
