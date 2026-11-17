<?php

namespace App\Models;

use App\Models\Concerns\RegistraHistorial;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un número que falta en el correlativo de facturas y ya tiene explicación:
 * se anuló en SUNAT, se emitió por algo que no es un flete. Deja de avisarse
 * como faltante. Si después se sube esa factura, la explicación sobra y no
 * estorba.
 *
 * @property int $id
 * @property string $serie
 * @property int $numero
 * @property string $motivo
 * @property string|null $nota
 * @property int|null $user_id
 * @property-read User|null $usuario
 */
#[Fillable(['serie', 'numero', 'motivo', 'nota', 'user_id'])]
class NumeroDeFacturaExplicado extends Model
{
    use RegistraHistorial;

    protected $table = 'numeros_de_factura_explicados';

    /** @var array<string, string> */
    public const MOTIVOS = [
        'anulada_sunat' => 'Se anuló en SUNAT',
        'no_es_flete' => 'No es un flete (otro servicio)',
        'otro' => 'Otro',
    ];

    public function etiquetaHistorial(): ?string
    {
        return 'Factura '.$this->serie.'-'.$this->numero.' explicada';
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
