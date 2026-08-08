<?php

namespace App\Models;

use App\Enums\EstadoProgramacion;
use Carbon\CarbonImmutable;
use Database\Factories\ProgramacionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Lo que un aviso de WhatsApp reportó sobre un tracto en un día puntual:
 * quedó programado para tal carga, o quedó libre. Reemplaza la lectura
 * manual de esas capturas sueltas — una fila por tracto y día, igual que
 * `Asistencia` reemplaza el rooster en papel. Sin registro para el día es
 * «sin marcar», no un estado más: no se asume nada de un tracto del que no
 * llegó aviso.
 *
 * @property int $id
 * @property int $vehiculo_id
 * @property Carbon $fecha
 * @property EstadoProgramacion $estado
 * @property EstadoProgramacion|null $estado_anterior
 * @property CarbonImmutable|null $estado_cambiado_en
 * @property string|null $destino
 * @property string|null $cliente
 * @property string|null $observaciones
 * @property-read Vehiculo $vehiculo
 */
#[Fillable([
    'vehiculo_id',
    'fecha',
    'estado',
    'destino',
    'cliente',
    'observaciones',
])]
class Programacion extends Model
{
    /** @use HasFactory<ProgramacionFactory> */
    use HasFactory;

    protected $table = 'programaciones';

    /**
     * Incluye tractos dados de baja (soft delete): un aviso viejo debe seguir
     * mostrando a qué fierro se refería aunque este ya no esté en la flota.
     *
     * @return BelongsTo<Vehiculo, $this>
     */
    public function vehiculo(): BelongsTo
    {
        return $this->belongsTo(Vehiculo::class)->withTrashed();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha' => 'date:Y-m-d',
            'estado' => EstadoProgramacion::class,
            'estado_anterior' => EstadoProgramacion::class,
            'estado_cambiado_en' => 'datetime',
        ];
    }
}
