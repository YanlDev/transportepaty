<?php

namespace App\Models;

use Database\Factories\PlantillaMantenimientoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $nombre
 * @property string $tipo_mantenimiento
 * @property string|null $marca
 * @property string|null $modelo
 * @property string|null $tipo_vehiculo
 * @property int|null $intervalo_km
 * @property int|null $intervalo_meses
 * @property bool $una_vez
 * @property string|null $descripcion
 * @property float|null $costo_estimado
 * @property int $orden
 * @property bool $activo
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'nombre',
    'tipo_mantenimiento',
    'marca',
    'modelo',
    'tipo_vehiculo',
    'intervalo_km',
    'intervalo_meses',
    'una_vez',
    'descripcion',
    'costo_estimado',
    'orden',
    'activo',
])]
class PlantillaMantenimiento extends Model
{
    /** @use HasFactory<PlantillaMantenimientoFactory> */
    use HasFactory;

    protected $table = 'plantillas_mantenimiento';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'intervalo_km' => 'integer',
            'intervalo_meses' => 'integer',
            'una_vez' => 'boolean',
            'costo_estimado' => 'decimal:2',
            'orden' => 'integer',
            'activo' => 'boolean',
        ];
    }
}
