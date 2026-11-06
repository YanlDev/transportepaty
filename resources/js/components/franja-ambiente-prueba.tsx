import { usePage } from '@inertiajs/react';

/**
 * Aviso fijo arriba de todo en el ambiente de pruebas: los datos son una
 * copia de producción y se ven iguales, así que sin esto es fácil olvidar en
 * cuál de los dos se está trabajando.
 */
export function FranjaAmbientePrueba() {
    const { esAmbientePrueba } = usePage().props;

    if (!esAmbientePrueba) {
        return null;
    }

    return (
        <div className="sticky top-0 z-50 w-full bg-amber-400 px-4 py-1 text-center text-xs font-semibold text-amber-950">
            AMBIENTE DE PRUEBAS — lo que se cargue acá no llega a producción
        </div>
    );
}
