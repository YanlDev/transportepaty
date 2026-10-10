<?php

namespace App\Models;

use App\Enums\EstadoCobranza;
use App\Enums\Moneda;
use App\Services\DesgloseFactura;
use App\Services\RelojOperativo;
use Carbon\CarbonInterface;
use Database\Factories\FacturaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Una factura emitida por el flete. Es la unidad de dinero y, de paso, la que
 * agrupa: un mismo viaje físico puede llegar con dos GR —dos filas en
 * `viajes`— y cobrarse una sola vez, y una factura quincenal puede juntar diez
 * viajes del mismo cliente. A la inversa, una GR puede tener varias facturas:
 * el flete por un lado y la estadía por otro, o un cobro partido. Quien
 * factura elige qué filas entran; no se deriva de `Viaje::claveGrupoViaje()`,
 * que es una heurística para contar viajes reales y equivocarse ahí sería
 * equivocarse en la cobranza.
 *
 * El flete se guarda descompuesto como lo imprime SUNAT: `monto` es el valor
 * sin IGV, y `igv`, `total`, `detraccion` y `neto` se recalculan solos al
 * guardar (ver `DesgloseFactura`). El cobro va en dos partes: el neto, que
 * entra a una cuenta de la empresa (`fecha_pago`), y la detracción, que el
 * cliente deposita en el Banco de la Nación (`fecha_detraccion`).
 *
 * Una factura subida en PDF (`desde_pdf`) guarda las cifras impresas sin
 * recalcularlas, trae el PDF en la colección `archivo` y recuerda las GR que
 * cita (`gr_citadas`) o el período que cobra, para asociarse a sus viajes.
 * Mientras no tenga ninguno, espera en la bandeja «Facturas por asociar».
 *
 * @property int $id
 * @property string $numero
 * @property Carbon $fecha_emision
 * @property string|null $monto
 * @property string $tasa_igv
 * @property string|null $igv
 * @property string|null $total
 * @property string $tasa_detraccion
 * @property string|null $detraccion
 * @property string|null $neto
 * @property Moneda $moneda
 * @property Carbon|null $fecha_pago
 * @property int|null $cuenta_bancaria_id
 * @property Carbon|null $fecha_detraccion
 * @property string|null $constancia_detraccion
 * @property string|null $observacion
 * @property string|null $cliente_ruc
 * @property string|null $cliente_razon_social
 * @property bool $desde_pdf
 * @property Carbon|null $fecha_vencimiento
 * @property list<string>|null $gr_citadas
 * @property Carbon|null $periodo_desde
 * @property Carbon|null $periodo_hasta
 * @property-read CuentaBancaria|null $cuentaBancaria
 * @property-read Collection<int, Viaje> $viajes
 */
#[Fillable([
    'numero',
    'fecha_emision',
    'monto',
    'tasa_igv',
    'total',
    'tasa_detraccion',
    'moneda',
    'fecha_pago',
    'cuenta_bancaria_id',
    'fecha_detraccion',
    'constancia_detraccion',
    'observacion',
    'cliente_ruc',
    'cliente_razon_social',
    'fecha_vencimiento',
    'gr_citadas',
    'periodo_desde',
    'periodo_hasta',
])]
class Factura extends Model implements HasMedia
{
    /** @use HasFactory<FacturaFactory> */
    use HasFactory;

    use InteractsWithMedia;

    /**
     * El próximo guardado trae las cifras impresas en el PDF y no se
     * recalculan. Lo pone `guardarCifrasImpresas()`; no es una columna.
     */
    private bool $guardandoCifrasImpresas = false;

    /**
     * Fija las cifras tal como las imprimió SUNAT, sin recalcularlas: la
     * detracción impresa puede no ser el 4% del total y es la que vale.
     *
     * @param  array{monto: float, igv: float|null, total: float, detraccion: float, neto: float|null}  $cifras
     */
    public function guardarCifrasImpresas(array $cifras): void
    {
        $this->forceFill([...$cifras, 'desde_pdf' => true]);
        $this->guardandoCifrasImpresas = true;
    }

    /**
     * Las tasas con que nace una factura, tomadas de la configuración. Se
     * copian en la fila para que una factura ya emitida no cambie si cambia
     * la tasa.
     */
    protected static function booted(): void
    {
        static::creating(function (Factura $factura): void {
            $factura->tasa_igv ??= (string) config('transpaty.igv');
            $factura->tasa_detraccion ??= (string) config('transpaty.facturacion.detraccion');
        });

        static::saving(fn (Factura $factura) => $factura->desglosar());
    }

    /**
     * Recalcula IGV, total, detracción y neto a partir de lo que se tocó: el
     * total si se escribió el total (flete pactado con IGV), y si no el valor.
     * Sin valor ni total no hay nada que calcular y las cifras quedan vacías.
     */
    private function desglosar(): void
    {
        // Las cifras impresas se respetan tal cual —la detracción puede no
        // ser el 4% del total—, salvo que alguien corrija el valor o el total
        // a mano: entonces vuelve a calcularse y deja de ser «la del PDF».
        if ($this->guardandoCifrasImpresas) {
            $this->guardandoCifrasImpresas = false;

            return;
        }

        if ($this->desde_pdf) {
            if (! $this->isDirty(['monto', 'total', 'tasa_igv', 'tasa_detraccion'])) {
                return;
            }

            $this->desde_pdf = false;
        }

        $tasaIgv = (float) ($this->tasa_igv ?? config('transpaty.igv'));
        $tasaDetraccion = (float) ($this->tasa_detraccion ?? config('transpaty.facturacion.detraccion'));
        $umbral = (float) config('transpaty.facturacion.umbral_detraccion');

        // El total manda cuando es lo que se escribió y no vino a la vez un
        // valor (en una factura nueva, `monto` vacío también cuenta como tocado).
        $desdeTotal = $this->total !== null
            && $this->isDirty('total')
            && ($this->monto === null || ! $this->isDirty('monto'));

        if ($desdeTotal) {
            $this->forceFill(DesgloseFactura::desdeTotal((float) $this->total, $tasaIgv, $tasaDetraccion, $umbral));

            return;
        }

        if ($this->monto === null) {
            $this->monto = null;
            $this->igv = null;
            $this->total = null;
            $this->detraccion = null;
            $this->neto = null;

            return;
        }

        // Con el total ya fijado y solo la detracción en juego, se respeta el
        // total: es el que dice la factura impresa.
        $cifras = $this->isDirty(['monto', 'tasa_igv']) || $this->total === null
            ? DesgloseFactura::desdeValor((float) $this->monto, $tasaIgv, $tasaDetraccion, $umbral)
            : DesgloseFactura::desdeTotal((float) $this->total, $tasaIgv, $tasaDetraccion, $umbral);

        $this->forceFill($cifras);
    }

    /**
     * @return BelongsToMany<Viaje, $this>
     */
    public function viajes(): BelongsToMany
    {
        return $this->belongsToMany(Viaje::class)->withTimestamps();
    }

    /**
     * @return BelongsTo<CuentaBancaria, $this>
     */
    public function cuentaBancaria(): BelongsTo
    {
        return $this->belongsTo(CuentaBancaria::class);
    }

    /**
     * Por cobrar, cobrada solo en parte o cobrada del todo. Nunca devuelve
     * `SinFacturar`: si hay factura, algo se facturó — ese caso es el del
     * viaje sin facturas, y lo resuelve `Viaje::estadoCobranza()`.
     *
     * El neto manda: mientras no entre, la factura está por cobrar. Con el
     * neto adentro, falta la detracción si la factura lleva y todavía no se
     * depositó.
     */
    public function estado(): EstadoCobranza
    {
        if ($this->fecha_pago === null) {
            return EstadoCobranza::Facturado;
        }

        return $this->faltaDetraccion()
            ? EstadoCobranza::FaltaDetraccion
            : EstadoCobranza::Pagado;
    }

    /** Lleva detracción y el cliente todavía no la depositó. */
    public function faltaDetraccion(): bool
    {
        return (float) $this->detraccion > 0 && $this->fecha_detraccion === null;
    }

    /**
     * Cuándo vence: la fecha de la cuota impresa, si la factura se subió en
     * PDF; si no, la emisión más el plazo de crédito, que es el mismo para
     * todos los clientes.
     */
    public function fechaVencimiento(): CarbonInterface
    {
        return $this->fecha_vencimiento?->copy()->startOfDay()
            ?? $this->fecha_emision->copy()->startOfDay()
                ->addDays((int) config('transpaty.facturacion.plazo_credito_dias'));
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('archivo')
            ->singleFile()
            ->acceptsMimeTypes(['application/pdf']);
    }

    /**
     * Días pasados del vencimiento: positivo si ya venció, cero el día que
     * vence y negativo mientras falte. Null cuando ya se cobró todo: de una
     * factura pagada interesa la fecha, no el atraso.
     */
    public function diasVencida(): ?int
    {
        if ($this->estado() === EstadoCobranza::Pagado) {
            return null;
        }

        return (int) $this->fechaVencimiento()->diffInDays(RelojOperativo::fechaDeHoy(), absolute: false);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha_emision' => 'date:Y-m-d',
            'fecha_pago' => 'date:Y-m-d',
            'fecha_detraccion' => 'date:Y-m-d',
            'fecha_vencimiento' => 'date:Y-m-d',
            'periodo_desde' => 'date:Y-m-d',
            'periodo_hasta' => 'date:Y-m-d',
            'gr_citadas' => 'array',
            'desde_pdf' => 'boolean',
            'monto' => 'decimal:2',
            'tasa_igv' => 'decimal:4',
            'igv' => 'decimal:2',
            'total' => 'decimal:2',
            'tasa_detraccion' => 'decimal:4',
            'detraccion' => 'decimal:2',
            'neto' => 'decimal:2',
            'moneda' => Moneda::class,
        ];
    }
}
