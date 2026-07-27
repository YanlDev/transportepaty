<?php

namespace App\Models;

use App\Enums\EstadoVehiculo;
use App\Enums\TipoCaja;
use App\Enums\TipoDocumento;
use App\Enums\TipoVehiculo;
use Database\Factories\VehiculoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $placa
 * @property string|null $marca
 * @property string|null $modelo
 * @property int|null $anio
 * @property TipoVehiculo $tipo
 * @property EstadoVehiculo $estado
 * @property TipoCaja|null $caja
 * @property string|null $vin
 * @property string|null $numero_motor
 * @property string|null $color
 * @property int|null $ejes
 * @property int|null $peso_neto
 * @property int|null $peso_bruto
 * @property int|null $carga_util
 * @property Carbon|null $fecha_adquisicion
 * @property string|null $observaciones
 */
#[Fillable([
    'placa',
    'marca',
    'modelo',
    'anio',
    'tipo',
    'estado',
    'caja',
    'vin',
    'numero_motor',
    'color',
    'ejes',
    'peso_neto',
    'peso_bruto',
    'carga_util',
    'fecha_adquisicion',
    'observaciones',
])]
class Vehiculo extends Model
{
    /** @use HasFactory<VehiculoFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'tipo' => TipoVehiculo::Tracto->value,
        'estado' => EstadoVehiculo::Activo->value,
    ];

    /**
     * Las carretas no tienen transmisión, así que se descarta cualquier caja
     * que llegue del formulario cuando el tipo no es tracto.
     */
    protected static function booted(): void
    {
        static::saving(function (Vehiculo $vehiculo): void {
            if (! $vehiculo->tipo->tieneCaja()) {
                $vehiculo->caja = null;
            }
        });
    }

    /**
     * @return HasMany<VehiculoDocumento, $this>
     */
    public function documentos(): HasMany
    {
        return $this->hasMany(VehiculoDocumento::class)->latest();
    }

    /**
     * La habilitación vigente del MTC, conocida como TUC. Es un documento y no
     * una columna del vehículo, así que se expone como relación para poder
     * precargarla en los listados sin caer en N+1.
     *
     * @return HasOne<VehiculoDocumento, $this>
     */
    public function tuc(): HasOne
    {
        return $this->hasOne(VehiculoDocumento::class)
            ->where('tipo', TipoDocumento::HabilitacionMtc)
            ->latestOfMany();
    }

    /**
     * Descripción corta para listados: marca y modelo, o la placa si el
     * vehículo aún no tiene esos datos cargados (caso típico de las carretas).
     */
    public function descripcion(): string
    {
        $partes = array_filter([$this->marca, $this->modelo]);

        return $partes === [] ? $this->placa : implode(' ', $partes);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo' => TipoVehiculo::class,
            'estado' => EstadoVehiculo::class,
            'caja' => TipoCaja::class,
            'anio' => 'integer',
            'ejes' => 'integer',
            'peso_neto' => 'integer',
            'peso_bruto' => 'integer',
            'carga_util' => 'integer',
            'fecha_adquisicion' => 'date:Y-m-d',
        ];
    }
}
