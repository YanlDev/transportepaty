import { router } from '@inertiajs/react';
import { Check, TriangleAlert } from 'lucide-react';
import vueltas from '@/actions/App/Http/Controllers/VueltaController';
import { Button } from '@/components/ui/button';
import type { Vuelta } from '@/types/vueltas';

/**
 * El aviso de un ticket marcado «por revisar»: qué hay que mirar (una línea
 * por motivo) y el botón para quitar la marca cuando ya se miró.
 */
export function PorRevisar({ vuelta }: { vuelta: Vuelta }) {
    if (!vuelta.por_revisar) {
        return null;
    }

    return (
        <div className="flex flex-col gap-2 rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-900 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200">
            <p className="flex items-center gap-2 font-medium">
                <TriangleAlert className="size-4 shrink-0" />
                Por revisar
            </p>
            <ul className="flex flex-col gap-1">
                {vuelta.por_revisar.split('\n').map((motivo) => (
                    <li key={motivo}>{motivo}</li>
                ))}
            </ul>
            {vuelta.editable && (
                <Button
                    variant="outline"
                    size="sm"
                    className="self-start"
                    onClick={() =>
                        router.post(
                            vueltas.revisado(vuelta.id).url,
                            {},
                            { preserveScroll: true },
                        )
                    }
                >
                    <Check className="size-3.5" />
                    Ya lo revisé
                </Button>
            )}
        </div>
    );
}
