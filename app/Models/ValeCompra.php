<?php

namespace App\Models;

use App\Enums\EstadoValeCompra;
use App\Enums\Moneda;
use App\Enums\SituacionValeCompra;
use App\Enums\TipoValeCompra;
use App\Models\Concerns\RegistraHistorial;
use App\Models\Concerns\SeAnula;
use App\Services\RelojOperativo;
use Database\Factories\ValeCompraFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Un vale de compra: nace como solicitud, gerencia lo aprueba (o el jefe de
 * operaciones, si es una emergencia en ruta) y desde ahí es el documento con
 * que se compra. Al aprobarlo el servidor lo firma (ver `FirmaDeVales`), y
 * vale hasta `vence_el`.
 *
 * Que esté usado no se anota: sale de los ingresos al almacén que lo nombran
 * y de sus servicios confirmados. Así, anular un ingreso lo deja otra vez
 * disponible sin tocar nada.
 *
 * @property int $id
 * @property TipoValeCompra $tipo
 * @property EstadoValeCompra $estado
 * @property string $codigo_publico
 * @property string $codigo_verificacion
 * @property int $proveedor_id
 * @property Moneda $moneda
 * @property string $total
 * @property string $motivo
 * @property string|null $sustento_precio
 * @property int|null $orden_trabajo_id
 * @property int|null $vehiculo_id
 * @property bool $permite_parciales
 * @property int|null $solicitado_por
 * @property int|null $aprobado_por
 * @property Carbon|null $aprobado_at
 * @property Carbon|null $vence_el
 * @property string|null $huella
 * @property string|null $firma
 * @property int|null $rechazado_por
 * @property Carbon|null $rechazado_at
 * @property string|null $motivo_rechazo
 * @property Carbon|null $anulado_at
 * @property int|null $anulado_por
 * @property string|null $motivo_anulacion
 * @property Carbon|null $created_at
 * @property-read Proveedor $proveedor
 * @property-read User|null $solicitante
 * @property-read User|null $aprobador
 * @property-read OrdenTrabajo|null $ordenTrabajo
 * @property-read Vehiculo|null $vehiculo
 */
#[Fillable([
    'tipo',
    'estado',
    'codigo_publico',
    'codigo_verificacion',
    'proveedor_id',
    'moneda',
    'total',
    'motivo',
    'sustento_precio',
    'orden_trabajo_id',
    'vehiculo_id',
    'permite_parciales',
    'solicitado_por',
    'aprobado_por',
    'aprobado_at',
    'vence_el',
    'huella',
    'firma',
    'rechazado_por',
    'rechazado_at',
    'motivo_rechazo',
    'anulado_at',
    'anulado_por',
    'motivo_anulacion',
])]
class ValeCompra extends Model implements HasMedia
{
    /** @use HasFactory<ValeCompraFactory> */
    use HasFactory, RegistraHistorial, SeAnula;

    use InteractsWithMedia;

    /** Sin 0/O ni 1/I: el código se lee de un papel y se escribe a mano. */
    private const ALFABETO = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    /** Por debajo de esto dos montos son el mismo. */
    public const CENTIMO = 0.005;

    protected $table = 'vales_compra';

    public function numero(): string
    {
        return sprintf('VC-%06d', $this->id);
    }

    /**
     * El que va en el QR: 128 bits al azar, imposible de adivinar o recorrer.
     */
    public static function nuevoCodigoPublico(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * El que se imprime para escribirlo a mano: `XXXX-XXXX-XXXX`.
     */
    public static function nuevoCodigoVerificacion(): string
    {
        $letras = '';

        for ($i = 0; $i < 12; $i++) {
            $letras .= self::ALFABETO[random_int(0, strlen(self::ALFABETO) - 1)];
        }

        return implode('-', str_split($letras, 4));
    }

    /**
     * Como lo escriba quien verifica: sin guiones, con espacios, en minúsculas.
     */
    public static function normalizarCodigo(string $codigo): string
    {
        $limpio = preg_replace('/[^A-Z0-9]/', '', mb_strtoupper($codigo)) ?? '';

        return implode('-', str_split($limpio, 4));
    }

    /**
     * @return HasMany<DetalleValeCompra, $this>
     */
    public function detalles(): HasMany
    {
        return $this->hasMany(DetalleValeCompra::class);
    }

    /**
     * Las compras que entraron al almacén con este vale.
     *
     * @return HasMany<IngresoAlmacen, $this>
     */
    public function ingresos(): HasMany
    {
        return $this->hasMany(IngresoAlmacen::class);
    }

    /**
     * @return HasMany<ConsultaValeCompra, $this>
     */
    public function consultas(): HasMany
    {
        return $this->hasMany(ConsultaValeCompra::class);
    }

    /**
     * @return BelongsTo<Proveedor, $this>
     */
    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function solicitante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'solicitado_por');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function aprobador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprobado_por');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function rechazadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rechazado_por');
    }

    /**
     * @return BelongsTo<OrdenTrabajo, $this>
     */
    public function ordenTrabajo(): BelongsTo
    {
        return $this->belongsTo(OrdenTrabajo::class);
    }

    /**
     * @return BelongsTo<Vehiculo, $this>
     */
    public function vehiculo(): BelongsTo
    {
        return $this->belongsTo(Vehiculo::class)->withTrashed();
    }

    /**
     * Lo que se autorizó en artículos: es lo que entra al almacén.
     */
    public function totalArticulos(): float
    {
        return round((float) $this->detalles->where('es_servicio', false)->sum('total'), 2);
    }

    /**
     * Lo ya comprado en artículos: los ingresos vigentes que nombran el vale.
     */
    public function recibidoEnArticulos(): float
    {
        return round((float) $this->ingresos()->vigentes()->sum('total'), 2);
    }

    /**
     * Lo ya usado del vale: lo que entró al almacén y los servicios atendidos.
     */
    public function recibido(): float
    {
        return round(
            $this->recibidoEnArticulos() + (float) $this->detalles->where('es_servicio', true)->whereNotNull('atendido_at')->sum('total'),
            2,
        );
    }

    public function saldo(): float
    {
        return round(max((float) $this->total - $this->recibido(), 0), 2);
    }

    /**
     * Si en esa fecha (hoy, por defecto) el vale ya pasó su vigencia.
     */
    public function vencido(Carbon|string|null $fecha = null): bool
    {
        return $this->vence_el !== null
            && Carbon::parse($fecha ?? RelojOperativo::hoy())->startOfDay()->gt($this->vence_el->copy()->startOfDay());
    }

    public function situacion(): SituacionValeCompra
    {
        if ($this->estaAnulado()) {
            return SituacionValeCompra::Anulado;
        }

        if ($this->estado === EstadoValeCompra::Rechazado) {
            return SituacionValeCompra::Rechazado;
        }

        if ($this->estado === EstadoValeCompra::Solicitado) {
            return SituacionValeCompra::PorAprobar;
        }

        $recibido = $this->recibido();

        if ($recibido > 0 && (! $this->permite_parciales || (float) $this->total - $recibido < self::CENTIMO)) {
            return SituacionValeCompra::Usado;
        }

        if ($this->vencido()) {
            return SituacionValeCompra::Vencido;
        }

        return $recibido > 0 ? SituacionValeCompra::Parcial : SituacionValeCompra::Vigente;
    }

    /**
     * Las cotizaciones y los comprobantes de los servicios, en el disco
     * privado: solo se abren con sesión y permiso.
     */
    public function registerMediaCollections(): void
    {
        foreach (['cotizaciones', 'comprobantes_servicio'] as $coleccion) {
            $this->addMediaCollection($coleccion)
                ->useDisk('local')
                ->acceptsMimeTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/webp']);
        }
    }

    public function etiquetaHistorial(): ?string
    {
        return $this->numero();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo' => TipoValeCompra::class,
            'estado' => EstadoValeCompra::class,
            'moneda' => Moneda::class,
            'total' => 'decimal:2',
            'permite_parciales' => 'boolean',
            'aprobado_at' => 'datetime',
            'vence_el' => 'date:Y-m-d',
            'rechazado_at' => 'datetime',
            'anulado_at' => 'datetime',
        ];
    }
}
