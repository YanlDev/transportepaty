<?php

namespace App\Services;

use App\Enums\UnidadMedida;
use App\Models\Articulo;
use App\Models\CategoriaArticulo;
use App\Models\MovimientoAlmacen;
use App\Models\PlanMantenimiento;
use App\Models\RepuestoServicio;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Cada ítem de un plan preventivo que sale del almacén tiene su artículo, y
 * en la misma unidad: así el plan, el stock y lo que se entrega a la orden
 * hablan de lo mismo.
 *
 * El artículo se crea solo (en stock cero) al guardar el plan, con el nombre
 * del ítem y la marca del plan —el filtro de combustible de Scania no es el
 * de Volvo—. Mientras no tenga movimientos sigue al ítem: si se le cambia el
 * nombre, cambia; si el ítem se quita, se va. Con movimientos ya es historia
 * del kardex y no se toca.
 */
class ArticulosDelPlan
{
    /** Cómo se escribían las unidades en el plan y a cuál del almacén equivalen. */
    private const UNIDADES = [
        'l' => UnidadMedida::Litro, 'lt' => UnidadMedida::Litro, 'lts' => UnidadMedida::Litro, 'litro' => UnidadMedida::Litro, 'litros' => UnidadMedida::Litro,
        'gal' => UnidadMedida::Galon, 'gl' => UnidadMedida::Galon, 'galon' => UnidadMedida::Galon, 'galones' => UnidadMedida::Galon,
        'kg' => UnidadMedida::Kilogramo, 'kilo' => UnidadMedida::Kilogramo, 'kilos' => UnidadMedida::Kilogramo,
        'm' => UnidadMedida::Metro, 'mt' => UnidadMedida::Metro,
        'jgo' => UnidadMedida::Juego, 'juego' => UnidadMedida::Juego, 'kit' => UnidadMedida::Juego,
        'par' => UnidadMedida::Par, 'cja' => UnidadMedida::Caja, 'caja' => UnidadMedida::Caja,
        'cil' => UnidadMedida::Cilindro, 'bld' => UnidadMedida::Balde, 'balde' => UnidadMedida::Balde,
        'rll' => UnidadMedida::Rollo, 'pqt' => UnidadMedida::Paquete,
        'und' => UnidadMedida::Unidad, 'un' => UnidadMedida::Unidad, 'unidad' => UnidadMedida::Unidad, 'pza' => UnidadMedida::Unidad, '' => UnidadMedida::Unidad,
    ];

    /** Por qué palabra del nombre se adivina la categoría del artículo. */
    private const CATEGORIAS = [
        'Filtros' => ['filtro', 'prefiltro', 'elemento filtrante', 'colador'],
        'Lubricantes' => ['aceite', 'grasa', 'refrigerante', 'líquido', 'liquido', 'fluido'],
        'Motor' => ['faja', 'arandela', 'tapón', 'tapon', 'empaque', 'junta'],
    ];

    /**
     * La unidad del almacén que corresponde a lo que el plan tenía escrito.
     */
    public static function unidad(?string $texto): UnidadMedida
    {
        return self::UNIDADES[mb_strtolower(trim((string) $texto))] ?? UnidadMedida::Unidad;
    }

    /**
     * El nombre del artículo de un ítem: el del ítem, sin la nota del
     * fabricante, con la marca del plan.
     */
    public static function nombre(string $descripcion, string $marca): string
    {
        $limpio = trim((string) preg_replace('/\s*\((fabricante|fab\.)[^)]*\)/iu', '', $descripcion));

        return mb_strtoupper(trim("{$limpio} {$marca}"));
    }

    /**
     * Le da su artículo a cada ítem que no lo tiene, y deja cada ítem en la
     * unidad de su artículo. Devuelve cuántos artículos creó.
     */
    public function asegurar(?PlanMantenimiento $plan = null): int
    {
        $creados = 0;

        $planes = $plan === null ? PlanMantenimiento::query()->with('servicios.repuestos.articulo')->get() : collect([$plan->load('servicios.repuestos.articulo')]);

        foreach ($planes as $unPlan) {
            foreach ($unPlan->servicios as $servicio) {
                foreach ($servicio->repuestos as $repuesto) {
                    $creados += $this->asegurarItem($repuesto, $unPlan);
                }
            }
        }

        return $creados;
    }

    /**
     * Después de guardar un plan: los artículos que nacieron de él y ya no
     * usa ningún ítem se quitan, si nunca se movieron.
     */
    public function limpiar(PlanMantenimiento $plan): void
    {
        $enUso = RepuestoServicio::query()->whereNotNull('articulo_id')->pluck('articulo_id')->unique()->all();

        Articulo::query()
            ->where('plan_mantenimiento_id', $plan->id)
            ->whereNotIn('id', $enUso)
            ->get()
            ->each(function (Articulo $articulo): void {
                if ($this->seMovio($articulo)) {
                    return;
                }

                // Si algo más lo nombra (un pedido de compra, un repuesto
                // pedido para una orden), no se borra: se da de baja.
                try {
                    DB::transaction(fn () => $articulo->delete());
                } catch (QueryException) {
                    $articulo->update(['activo' => false]);
                }
            });
    }

    private function asegurarItem(RepuestoServicio $repuesto, PlanMantenimiento $plan): int
    {
        if ($repuesto->es_servicio) {
            return 0;
        }

        $articulo = $repuesto->articulo;
        $nombre = self::nombre($repuesto->descripcion, $plan->marca);
        $creado = 0;

        if ($articulo === null) {
            $articulo = Articulo::query()->where('nombre', $nombre)->first();

            if ($articulo === null) {
                $articulo = Articulo::create([
                    'codigo' => Articulo::siguienteCodigo(),
                    'nombre' => $nombre,
                    'numero_parte' => $repuesto->codigo,
                    'categoria_articulo_id' => $this->categoria($repuesto->descripcion),
                    'unidad' => self::unidad($repuesto->unidad),
                    'factor_compra' => 1,
                    'marcas_compatibles' => [$plan->marca],
                    'activo' => true,
                ]);
                $articulo->forceFill(['plan_mantenimiento_id' => $plan->id])->saveQuietly();
                $creado = 1;
            }
        } elseif ($articulo->plan_mantenimiento_id === $plan->id && $articulo->nombre !== $nombre && ! $this->seMovio($articulo)
            && Articulo::query()->where('nombre', $nombre)->whereKeyNot($articulo->id)->doesntExist()) {
            // El ítem cambió de nombre y su artículo nunca se movió: lo sigue.
            $articulo->update(['nombre' => $nombre, 'numero_parte' => $repuesto->codigo ?? $articulo->numero_parte]);
        }

        // El ítem queda en la unidad de su artículo: la cantidad del plan se
        // compara con el stock y con lo entregado sin convertir nada.
        $repuesto->forceFill(['articulo_id' => $articulo->id, 'unidad' => $articulo->unidad->value])->saveQuietly();

        return $creado;
    }

    private function seMovio(Articulo $articulo): bool
    {
        return MovimientoAlmacen::query()->where('articulo_id', $articulo->id)->exists();
    }

    private function categoria(string $descripcion): ?int
    {
        $texto = mb_strtolower($descripcion);

        foreach (self::CATEGORIAS as $nombre => $palabras) {
            foreach ($palabras as $palabra) {
                if (str_contains($texto, $palabra)) {
                    return CategoriaArticulo::query()->where('nombre', $nombre)->value('id');
                }
            }
        }

        return null;
    }
}
