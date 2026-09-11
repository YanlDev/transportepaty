<?php

namespace App\Models;

use Database\Factories\ClienteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Un cliente del padrón. La GR sigue trayendo el nombre y el RUC escritos por
 * quien la emitió; esto es la versión de la casa: el RUC como identidad, un
 * alias corto para mostrar, y los datos de contacto que hoy viven en la
 * cabeza de alguien.
 *
 * @property int $id
 * @property string $ruc
 * @property string $razon_social
 * @property string $alias
 * @property string|null $nombre_comercial
 * @property string|null $contacto
 * @property string|null $telefono
 * @property string|null $email
 * @property string|null $direccion
 * @property bool $recurrente
 * @property bool $activo
 * @property string|null $notas
 * @property-read Collection<int, Viaje> $viajes
 *
 * Agregados que el listado pide con `withCount`/`withMax`; solo están
 * presentes en esas consultas.
 * @property-read int|null $viajes_count
 * @property-read string|null $viajes_max_fecha_traslado
 */
#[Fillable([
    'ruc',
    'razon_social',
    'alias',
    'nombre_comercial',
    'contacto',
    'telefono',
    'email',
    'direccion',
    'recurrente',
    'activo',
    'notas',
])]
class Cliente extends Model
{
    /** @use HasFactory<ClienteFactory> */
    use HasFactory;

    /**
     * Formas societarias que no aportan nada al identificar al cliente en una
     * tabla o un gráfico. Van de la más larga a la más corta: si se quitara
     * «S.A.» primero, «SOCIEDAD ANONIMA CERRADA» quedaría a medias.
     *
     * @var list<string>
     */
    private const FORMAS_SOCIETARIAS = [
        'EMPRESA INDIVIDUAL DE RESPONSABILIDAD LIMITADA',
        'EMPRESA INDIVIDUAL DE RESPONSABILIDAD',
        'SOCIEDAD COMERCIAL DE RESPONSABILIDAD LIMITADA',
        'SOCIEDAD ANONIMA CERRADA',
        'SOCIEDAD ANONIMA',
        'E.I.R.L.',
        'S.A.C.',
        'S.R.L.',
        'S.A.',
        'LTDA.',
        'E.I.R.L',
        'S.A.C',
        'S.A',
    ];

    /**
     * @return HasMany<Viaje, $this>
     */
    public function viajes(): HasMany
    {
        return $this->hasMany(Viaje::class);
    }

    /**
     * Un alias de arranque a partir de la razón social: le quita la forma
     * societaria y lo deja en capitalización normal, que es como se lee bien
     * en una tabla. «PORCELANATO LATINO SOCIEDAD ANONIMA CERRADA» queda como
     * «Porcelanato Latino». Es solo una sugerencia —el alias se puede editar
     * a mano y esta función no lo vuelve a tocar.
     */
    public static function aliasSugerido(string $razonSocial): string
    {
        $nombre = Str::squish(Str::upper($razonSocial));

        // Se recorta hasta que no quede nada por recortar: hay razones
        // sociales que encadenan dos formas («... EMPRESA INDIVIDUAL DE
        // RESPONSABILIDAD LTDA.»), y cortar solo la primera dejaría el alias
        // a medias.
        do {
            $anterior = $nombre;

            foreach (self::FORMAS_SOCIETARIAS as $forma) {
                if (str_ends_with($nombre, ' '.$forma)) {
                    $nombre = Str::beforeLast($nombre, ' '.$forma);
                    break;
                }
            }
        } while ($nombre !== $anterior && $nombre !== '');

        return Str::title(Str::squish($nombre));
    }

    /**
     * Busca el cliente que corresponde a un RUC del padrón. Devuelve null si
     * el RUC no está dado de alta —el viaje se queda con el texto crudo de la
     * GR, igual que cuando una placa no matchea.
     */
    public static function porRuc(?string $ruc): ?self
    {
        if ($ruc === null || $ruc === '') {
            return null;
        }

        return self::query()->where('ruc', $ruc)->first();
    }

    /**
     * @param  Builder<$this>  $query
     */
    public function scopeBuscar(Builder $query, string $termino): void
    {
        $query->where(function (Builder $query) use ($termino): void {
            $query->whereLike('razon_social', "%{$termino}%", caseSensitive: false)
                ->orWhereLike('alias', "%{$termino}%", caseSensitive: false)
                ->orWhereLike('ruc', "%{$termino}%", caseSensitive: false)
                ->orWhereLike('contacto', "%{$termino}%", caseSensitive: false);
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'recurrente' => 'boolean',
            'activo' => 'boolean',
        ];
    }
}
