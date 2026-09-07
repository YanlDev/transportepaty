<?php

namespace Database\Seeders;

use App\Models\Ubigeo;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Carga los 1.874 distritos del Perú con su código INEI.
 *
 * Es dato de referencia, no de negocio: no cambia salvo que el INEI cree un
 * distrito nuevo, así que vive en un JSON versionado junto al código en vez de
 * bajarse de una API en cada despliegue.
 */
class UbigeoSeeder extends Seeder
{
    public function run(): void
    {
        $ruta = database_path('data/ubigeos.json');

        if (! is_file($ruta)) {
            throw new RuntimeException("No se encuentra el catálogo de ubigeos en {$ruta}.");
        }

        /** @var list<array{codigo: string, distrito: string, provincia: string, departamento: string, busqueda: string}> $ubigeos */
        $ubigeos = json_decode((string) file_get_contents($ruta), true, flags: JSON_THROW_ON_ERROR);

        foreach (array_chunk($ubigeos, 500) as $lote) {
            Ubigeo::query()->upsert($lote, ['codigo']);
        }
    }
}
