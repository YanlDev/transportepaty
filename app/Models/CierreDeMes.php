<?php

namespace App\Models;

use App\Models\Concerns\RegistraHistorial;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

/**
 * Un mes cerrado de la cobranza. Con el mes cerrado, sus facturas (por fecha
 * de emisión) y sus cobros (por fecha del depósito) no se crean, corrigen ni
 * anulan: lo que ya se le reportó al contador no cambia por debajo. Cobrar
 * después una factura de un mes cerrado sí se puede: ese cobro es del mes en
 * que entra la plata. Reabrir borra el cierre; el historial lo recuerda.
 *
 * @property int $id
 * @property string $mes `AAAA-MM`
 * @property int|null $cerrado_por
 * @property-read User|null $cerradoPor
 */
#[Fillable(['mes', 'cerrado_por'])]
class CierreDeMes extends Model
{
    use RegistraHistorial;

    protected $table = 'cierres_de_mes';

    public function etiquetaHistorial(): ?string
    {
        return 'Cierre de '.$this->mes;
    }

    public static function estaCerrado(CarbonInterface|string|null $fecha): bool
    {
        if ($fecha === null) {
            return false;
        }

        $mes = $fecha instanceof CarbonInterface ? $fecha->format('Y-m') : substr($fecha, 0, 7);

        return self::query()->where('mes', $mes)->exists();
    }

    /**
     * Corta el cambio si alguna de esas fechas cae en un mes cerrado.
     *
     * @throws ValidationException
     */
    public static function exigirAbierto(string $que, CarbonInterface|string|null ...$fechas): void
    {
        foreach ($fechas as $fecha) {
            if (self::estaCerrado($fecha)) {
                $mes = $fecha instanceof CarbonInterface ? $fecha->format('m/Y') : substr((string) $fecha, 5, 2).'/'.substr((string) $fecha, 0, 4);

                throw ValidationException::withMessages([
                    'mes' => "{$que}: el mes {$mes} está cerrado. Reábrelo desde «Cierre de mes» para cambiarlo.",
                ]);
            }
        }
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function cerradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cerrado_por');
    }
}
