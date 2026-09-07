<?php

namespace App\Models;

use App\Enums\EstadoCotizacion;
use Database\Factories\CotizacionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * El precio que se le pasa a un cliente por un viaje que todavía no se hizo.
 *
 * Guarda el desglose completo y las tasas con las que se calculó, no solo el
 * total: una cotización se discute («¿por qué sale esto?») y se compara contra
 * otra de hace tres meses, y para eso el número suelto no alcanza.
 *
 * @property int $id
 * @property string $numero
 * @property Carbon $fecha
 * @property Carbon $valido_hasta
 * @property int|null $cliente_id
 * @property string $cliente_nombre
 * @property string|null $cliente_ruc
 * @property int|null $punto_partida_id
 * @property int|null $punto_llegada_id
 * @property string $origen
 * @property string $destino
 * @property string|null $material
 * @property int $km
 * @property float $dias
 * @property float $peajes
 * @property float $viaticos
 * @property float $alojamiento
 * @property float $cochera
 * @property float $carga_descarga
 * @property float $otros_ruta
 * @property array{componentes: list<array<string, mixed>>, ruta: list<array<string, mixed>>} $desglose
 * @property float $margen_pct
 * @property float $total_directo
 * @property float $total_indirecto
 * @property float $costo_operativo
 * @property float $margen
 * @property float $subtotal
 * @property float $igv
 * @property float $total
 * @property EstadoCotizacion $estado
 * @property string|null $notas
 */
#[Fillable([
    'numero',
    'fecha',
    'valido_hasta',
    'cliente_id',
    'cliente_nombre',
    'cliente_ruc',
    'punto_partida_id',
    'punto_llegada_id',
    'origen',
    'destino',
    'material',
    'km',
    'dias',
    'peajes',
    'viaticos',
    'alojamiento',
    'cochera',
    'carga_descarga',
    'otros_ruta',
    'desglose',
    'margen_pct',
    'total_directo',
    'total_indirecto',
    'costo_operativo',
    'margen',
    'subtotal',
    'igv',
    'total',
    'estado',
    'notas',
])]
class Cotizacion extends Model
{
    /** @use HasFactory<CotizacionFactory> */
    use HasFactory;

    /**
     * La serie con la que Paty numera sus proformas. Se mantiene la que ya
     * usan en papel para que una cotización del sistema y una de antes no
     * choquen ni se lean como cosas distintas.
     */
    public const SERIE = '006';

    protected $table = 'cotizaciones';

    /**
     * @return BelongsTo<Cliente, $this>
     */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    /**
     * @return BelongsTo<PuntoTraslado, $this>
     */
    public function puntoPartida(): BelongsTo
    {
        return $this->belongsTo(PuntoTraslado::class, 'punto_partida_id');
    }

    /**
     * @return BelongsTo<PuntoTraslado, $this>
     */
    public function puntoLlegada(): BelongsTo
    {
        return $this->belongsTo(PuntoTraslado::class, 'punto_llegada_id');
    }

    /**
     * El correlativo que sigue dentro de la serie. Se calcula sobre el máximo
     * guardado y no sobre el conteo: borrar una cotización no debe hacer que
     * el siguiente número repita uno ya entregado a un cliente.
     */
    public static function siguienteNumero(): string
    {
        $ultimo = self::query()
            ->where('numero', 'like', self::SERIE.'-%')
            ->orderByDesc('numero')
            ->value('numero');

        $correlativo = $ultimo === null
            ? 1
            : (int) substr($ultimo, strlen(self::SERIE) + 1) + 1;

        return self::SERIE.'-'.str_pad((string) $correlativo, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Lo que cuesta el kilómetro en esta cotización, con todo dentro: sirve
     * para comparar rutas de largos distintos, que en total no se comparan.
     */
    public function costoPorKm(): float
    {
        return $this->km > 0 ? $this->costo_operativo / $this->km : 0.0;
    }

    /**
     * Las tasas con las que se emitió, listas para volver a calcular sobre
     * ellas. Corregir un kilometraje mal tipeado usa estas y no las vigentes:
     * una proforma que el cliente ya tiene en la mano no puede cambiar de
     * precio porque mientras tanto subió el diésel.
     *
     * @return list<array{nombre: string, tipo: string, naturaleza: string, tasa: float}>
     */
    public function lineasCongeladas(): array
    {
        return array_map(fn (array $linea): array => [
            'nombre' => (string) $linea['nombre'],
            'tipo' => (string) $linea['tipo'],
            'naturaleza' => (string) $linea['naturaleza'],
            'tasa' => (float) $linea['tasa'],
        ], $this->desglose['componentes'] ?? []);
    }

    /**
     * @param  Builder<$this>  $query
     */
    public function scopeBuscar(Builder $query, string $termino): void
    {
        $query->where(function (Builder $query) use ($termino): void {
            $query->whereLike('numero', "%{$termino}%", caseSensitive: false)
                ->orWhereLike('cliente_nombre', "%{$termino}%", caseSensitive: false)
                ->orWhereLike('origen', "%{$termino}%", caseSensitive: false)
                ->orWhereLike('destino', "%{$termino}%", caseSensitive: false);
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'valido_hasta' => 'date',
            'km' => 'integer',
            'dias' => 'float',
            'peajes' => 'float',
            'viaticos' => 'float',
            'alojamiento' => 'float',
            'cochera' => 'float',
            'carga_descarga' => 'float',
            'otros_ruta' => 'float',
            'desglose' => 'array',
            'margen_pct' => 'float',
            'total_directo' => 'float',
            'total_indirecto' => 'float',
            'costo_operativo' => 'float',
            'margen' => 'float',
            'subtotal' => 'float',
            'igv' => 'float',
            'total' => 'float',
            'estado' => EstadoCotizacion::class,
        ];
    }
}
