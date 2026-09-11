import { Check, ChevronDownIcon } from 'lucide-react';
import { useState } from 'react';
import {
    CommandDialog,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import { cn } from '@/lib/utils';

export type OpcionBuscable = {
    valor: number;
    etiqueta: string;
    /** Texto extra que también matchea al escribir, p. ej. una placa. */
    detalle?: string;
};

/**
 * Elegir un valor obligatorio de una lista larga escribiendo.
 *
 * Es el hermano de `FiltroBuscable`, que resuelve lo mismo para la barra de
 * filtros: aquel puede quedar vacío («Todos los clientes») y este no, porque
 * acá la opción es un campo del formulario. Se separó en vez de agregarle un
 * modo al otro para no terminar con un componente que hace dos cosas según
 * un booleano.
 *
 * Con 61 unidades y 60 conductores un desplegable normal obliga a recorrer
 * la lista a ojo; escribiendo tres letras de la placa se llega directo.
 */
export function SelectorBuscable({
    id,
    valor,
    onCambio,
    etiqueta,
    opciones,
    invalido,
}: {
    /** El id del `<label>` que lo describe, para que el click en la etiqueta enfoque el disparador. */
    id?: string;
    valor: number | null;
    onCambio: (valor: number) => void;
    etiqueta: string;
    opciones: OpcionBuscable[];
    invalido?: boolean;
}) {
    const [abierto, setAbierto] = useState(false);
    const elegida = opciones.find((opcion) => opcion.valor === valor);

    return (
        <>
            <button
                id={id}
                type="button"
                onClick={() => setAbierto(true)}
                aria-label={etiqueta}
                aria-invalid={invalido}
                className="flex h-9 w-full items-center justify-between gap-2 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none hover:bg-accent/40 focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive aria-invalid:ring-destructive/20"
            >
                <span
                    className={cn(
                        'min-w-0 truncate',
                        !elegida && 'text-muted-foreground',
                    )}
                    title={elegida?.etiqueta}
                >
                    {elegida ? elegida.etiqueta : etiqueta}
                </span>
                <ChevronDownIcon className="size-4 shrink-0 opacity-50" />
            </button>

            <CommandDialog
                open={abierto}
                onOpenChange={setAbierto}
                title={etiqueta}
                description={`Escribe para filtrar y elige ${etiqueta.toLowerCase()}.`}
            >
                <CommandInput
                    placeholder={`Buscar ${etiqueta.toLowerCase()}...`}
                />
                <CommandList>
                    <CommandEmpty>Sin coincidencias.</CommandEmpty>

                    <CommandGroup>
                        {opciones.map((opcion) => (
                            <CommandItem
                                key={opcion.valor}
                                // cmdk filtra por este texto: lleva también el
                                // detalle para poder buscar por placa o por
                                // cualquier dato secundario de la opción.
                                value={`${opcion.etiqueta} ${opcion.detalle ?? ''}`}
                                onSelect={() => {
                                    onCambio(opcion.valor);
                                    setAbierto(false);
                                }}
                            >
                                <Check
                                    className={cn(
                                        'text-primary',
                                        valor !== opcion.valor && 'opacity-0',
                                    )}
                                />
                                <span className="min-w-0 flex-1 truncate">
                                    {opcion.etiqueta}
                                </span>
                                {opcion.detalle && (
                                    <span className="shrink-0 text-xs text-muted-foreground">
                                        {opcion.detalle}
                                    </span>
                                )}
                            </CommandItem>
                        ))}
                    </CommandGroup>
                </CommandList>
            </CommandDialog>
        </>
    );
}
