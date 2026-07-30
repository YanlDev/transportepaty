<?php

namespace App\Models;

use App\Enums\TipoCarga;
use Database\Factories\ImportacionFilaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una fila del Excel, con lo que decía la celda y lo que se pudo resolver contra
 * el catálogo. Vive aparte del estado canónico a propósito: mientras el usuario
 * no confirme la importación, esto es evidencia y previsualización, no un dato
 * de la operación.
 *
 * @property int $id
 * @property int $importacion_id
 * @property int $numero_fila
 * @property array<string, string> $crudo
 * @property int|null $tracto_id
 * @property int|null $carreta_id
 * @property int|null $conductor_id
 * @property TipoCarga|null $tipo_carga
 * @property int|null $origen_id
 * @property int|null $destino_id
 * @property int|null $ubicacion_id
 * @property string|null $observaciones
 * @property list<string>|null $problemas
 * @property bool $incluir
 * @property int|null $estado_unidad_id
 * @property-read Vehiculo|null $tracto
 * @property-read Vehiculo|null $carreta
 * @property-read Conductor|null $conductor
 * @property-read Ubicacion|null $origen
 * @property-read Ubicacion|null $destino
 * @property-read Ubicacion|null $ubicacion
 */
#[Fillable([
    'importacion_id',
    'numero_fila',
    'crudo',
    'tracto_id',
    'carreta_id',
    'conductor_id',
    'tipo_carga',
    'origen_id',
    'destino_id',
    'ubicacion_id',
    'observaciones',
    'problemas',
    'incluir',
    'estado_unidad_id',
])]
class ImportacionFila extends Model
{
    /** @use HasFactory<ImportacionFilaFactory> */
    use HasFactory;

    protected $table = 'importacion_filas';

    /**
     * Indica si la fila tiene todo lo mínimo para poder aplicarse: sin tracto no
     * hay a quién asignarle el estado.
     */
    public function puedeAplicarse(): bool
    {
        return $this->incluir && $this->tracto_id !== null;
    }

    /**
     * @return BelongsTo<Importacion, $this>
     */
    public function importacion(): BelongsTo
    {
        return $this->belongsTo(Importacion::class);
    }

    /**
     * @return BelongsTo<Vehiculo, $this>
     */
    public function tracto(): BelongsTo
    {
        return $this->belongsTo(Vehiculo::class, 'tracto_id');
    }

    /**
     * @return BelongsTo<Vehiculo, $this>
     */
    public function carreta(): BelongsTo
    {
        return $this->belongsTo(Vehiculo::class, 'carreta_id');
    }

    /**
     * @return BelongsTo<Conductor, $this>
     */
    public function conductor(): BelongsTo
    {
        return $this->belongsTo(Conductor::class);
    }

    /**
     * @return BelongsTo<Ubicacion, $this>
     */
    public function origen(): BelongsTo
    {
        return $this->belongsTo(Ubicacion::class, 'origen_id');
    }

    /**
     * @return BelongsTo<Ubicacion, $this>
     */
    public function destino(): BelongsTo
    {
        return $this->belongsTo(Ubicacion::class, 'destino_id');
    }

    /**
     * @return BelongsTo<Ubicacion, $this>
     */
    public function ubicacion(): BelongsTo
    {
        return $this->belongsTo(Ubicacion::class, 'ubicacion_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'crudo' => 'array',
            'tipo_carga' => TipoCarga::class,
            'problemas' => 'array',
            'incluir' => 'boolean',
        ];
    }
}
