import { router } from '@inertiajs/react';
import { FileText, Paperclip, Trash2 } from 'lucide-react';
import { useRef, useState } from 'react';
import guias from '@/actions/App/Http/Controllers/ViajeGuiaController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { GuiaRemision } from '@/types/fleet';

type Props = {
    viajeId: number;
    guias: GuiaRemision[];
    puedeGestionar: boolean;
};

/**
 * Las dos guías del viaje. Las que faltan siguen en la lista como hueco a la
 * vista: el papel que no llegó no debe desaparecer de la pantalla.
 */
export function GuiasPanel({ viajeId, guias: lista, puedeGestionar }: Props) {
    return (
        <div className="grid gap-4 sm:grid-cols-2">
            {lista.map((guia) => (
                <GuiaTarjeta
                    key={guia.tipo}
                    viajeId={viajeId}
                    guia={guia}
                    puedeGestionar={puedeGestionar}
                />
            ))}
        </div>
    );
}

function GuiaTarjeta({
    viajeId,
    guia,
    puedeGestionar,
}: {
    viajeId: number;
    guia: GuiaRemision;
    puedeGestionar: boolean;
}) {
    const entrada = useRef<HTMLInputElement>(null);
    const [subiendo, setSubiendo] = useState(false);

    // Se envía con `router` y no con `useForm` porque el archivo llega en el
    // mismo evento en que habría que guardarlo en el estado, y ese desfase haría
    // que la primera subida viajara vacía.
    const subir = (archivo: File) => {
        router.post(
            guias.store(viajeId).url,
            { tipo: guia.tipo, archivo },
            {
                preserveScroll: true,
                forceFormData: true,
                onStart: () => setSubiendo(true),
                onFinish: () => setSubiendo(false),
            },
        );
    };

    const quitar = () => {
        if (!confirm(`¿Quitar el archivo de la ${guia.abreviatura}?`)) {
            return;
        }

        router.delete(guias.destroy([viajeId, guia.tipo]).url, {
            preserveScroll: true,
        });
    };

    return (
        <div className="flex flex-col gap-3 rounded-xl border p-4">
            <div className="flex items-start justify-between gap-2">
                <div>
                    <p className="font-medium">{guia.abreviatura}</p>
                    <p className="text-xs text-muted-foreground">
                        {guia.label}
                    </p>
                </div>
                <Badge variant={guia.url ? 'secondary' : 'outline'}>
                    {guia.url ? 'Adjunta' : 'Sin archivo'}
                </Badge>
            </div>

            <p className="text-sm">
                {guia.numero ? (
                    <span className="font-medium">{guia.numero}</span>
                ) : (
                    <span className="text-muted-foreground">
                        Sin número registrado
                    </span>
                )}
            </p>

            <div className="flex flex-wrap gap-2">
                {guia.url && (
                    <Button variant="outline" size="sm" asChild>
                        <a
                            href={guia.url}
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            <FileText className="size-4" />
                            Ver
                        </a>
                    </Button>
                )}

                {puedeGestionar && (
                    <>
                        <input
                            ref={entrada}
                            type="file"
                            className="hidden"
                            accept=".pdf,.xml,image/jpeg,image/png,image/webp"
                            aria-label={`Archivo de la ${guia.abreviatura}`}
                            onChange={(evento) => {
                                const archivo = evento.target.files?.[0];

                                if (archivo) {
                                    subir(archivo);
                                }
                            }}
                        />

                        <Button
                            variant="outline"
                            size="sm"
                            disabled={subiendo}
                            onClick={() => entrada.current?.click()}
                        >
                            <Paperclip className="size-4" />
                            {guia.url ? 'Reemplazar' : 'Adjuntar'}
                        </Button>

                        {guia.url && (
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={quitar}
                                aria-label={`Quitar archivo de la ${guia.abreviatura}`}
                            >
                                <Trash2 className="size-4" />
                            </Button>
                        )}
                    </>
                )}
            </div>
        </div>
    );
}
