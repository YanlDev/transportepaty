<?php

namespace App\Models;

use App\Enums\EstadoEnvio;
use Carbon\CarbonImmutable;
use Database\Factories\EnvioWhatsappFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un aviso mandado por el número de la empresa y hasta dónde llegó.
 *
 * @property int $id
 * @property int|null $programacion_id De la pantalla de salidas de antes; ya no se llena.
 * @property int|null $vuelta_id
 * @property int|null $area_aviso_id
 * @property int|null $user_id A quién iba la alerta, si fue a un usuario y no a un área.
 * @property string $tipo
 * @property string|null $alerta El `TipoAlerta`, si el envío es una alerta.
 * @property string $destino
 * @property string $numero
 * @property string|null $texto El mensaje de una alerta, ya armado.
 * @property EstadoEnvio $estado
 * @property string|null $mensaje_id
 * @property string|null $error
 * @property int|null $enviado_por
 * @property CarbonImmutable|null $enviado_at
 * @property CarbonImmutable|null $entregado_at
 * @property CarbonImmutable|null $leido_at
 * @property CarbonImmutable $created_at
 * @property-read Vuelta|null $vuelta
 * @property-read AreaAviso|null $area
 */
#[Fillable([
    'vuelta_id',
    'area_aviso_id',
    'user_id',
    'tipo',
    'alerta',
    'destino',
    'numero',
    'texto',
    'estado',
    'mensaje_id',
    'error',
    'enviado_por',
    'enviado_at',
    'entregado_at',
    'leido_at',
])]
class EnvioWhatsapp extends Model
{
    /** @use HasFactory<EnvioWhatsappFactory> */
    use HasFactory;

    public const TIPO_CONDUCTOR = 'conductor';

    public const TIPO_AREA = 'area';

    /** Las unidades de hoy que siguen sin GR, a un área (sin salida propia). */
    public const TIPO_RECORDATORIO = 'recordatorio';

    /** Una alerta del sistema (ver `TipoAlerta`), con su texto ya armado. */
    public const TIPO_ALERTA = 'alerta';

    protected $table = 'envios_whatsapp';

    /**
     * @return BelongsTo<Vuelta, $this>
     */
    public function vuelta(): BelongsTo
    {
        return $this->belongsTo(Vuelta::class);
    }

    /**
     * @return BelongsTo<AreaAviso, $this>
     */
    public function area(): BelongsTo
    {
        return $this->belongsTo(AreaAviso::class, 'area_aviso_id');
    }

    /**
     * Anota un recibo de WhatsApp sin retroceder: si «entregado» llega
     * después de «leído», se ignora.
     */
    public function avanzarA(EstadoEnvio $estado): void
    {
        if ($estado->avance() <= $this->estado->avance()) {
            return;
        }

        $this->estado = $estado;

        match ($estado) {
            EstadoEnvio::Entregado => $this->entregado_at ??= now(),
            EstadoEnvio::Leido => [$this->entregado_at ??= now(), $this->leido_at = now()],
            default => null,
        };

        $this->save();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'estado' => EstadoEnvio::class,
            'enviado_at' => 'datetime',
            'entregado_at' => 'datetime',
            'leido_at' => 'datetime',
        ];
    }
}
