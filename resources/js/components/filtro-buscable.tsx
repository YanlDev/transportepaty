import { Check, ChevronDownIcon } from 'lucide-react';
import { useState } from 'react';
import {
    CommandDialog,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
    CommandSeparator,
} from '@/components/ui/command';
import { cn } from '@/lib/utils';
import type { EnumOption } from '@/types/fleet';

type Props = {
    valor: string | null;
    onCambio: (valor: string | null) => void;
    /** Texto de la opción que limpia el filtro, p. ej. «Todos los clientes». */
    todos: string;
    etiqueta: string;
    opciones: EnumOption[];
};

/**
 * Un filtro que se elige escribiendo. Para listas como la de clientes —varias
 * decenas de razones sociales largas— un desplegable normal se vuelve una
 * lista interminable que hay que recorrer a ojo y que además se desborda de la
 * pantalla.
 *
 * Va en diálogo y no en un panel anclado al botón: los nombres son largos
 * («FERRETERIA Y MATERIALES DE CONSTRUCCION S.R.L. - FEMACO S.R.L.») y en el
 * ancho del disparador se cortarían todos.
 */
export function FiltroBuscable({
    valor,
    onCambio,
    todos,
    etiqueta,
    opciones,
}: Props) {
    const [abierto, setAbierto] = useState(false);
    const elegida = opciones.find((opcion) => opcion.value === valor);

    const elegir = (nuevo: string | null) => {
        setAbierto(false);
        onCambio(nuevo);
    };

    return (
        <>
            {/* Imita al disparador de `FiltroSelect` para que la barra de
                filtros se vea pareja, sin importar cuál de los dos sea. */}
            <button
                type="button"
                onClick={() => setAbierto(true)}
                aria-label={etiqueta}
                className="flex h-9 w-full items-center justify-between gap-2 rounded-md border border-input bg-transparent px-3 py-2 text-sm whitespace-nowrap shadow-xs transition-[color,box-shadow] outline-none hover:bg-accent/40 focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 sm:w-auto sm:max-w-56 sm:min-w-36"
            >
                {/* `min-w-0` para que el truncado muerda: sin eso el botón se
                    estira con la razón social y desacomoda la barra entera. */}
                <span
                    className={cn(
                        'min-w-0 truncate',
                        !elegida && 'text-muted-foreground',
                    )}
                    title={elegida?.label}
                >
                    {elegida ? elegida.label : etiqueta}
                </span>
                <ChevronDownIcon className="size-4 shrink-0 opacity-50" />
            </button>

            <CommandDialog
                open={abierto}
                onOpenChange={setAbierto}
                title={etiqueta}
                description={`Escribe para filtrar y elige una opción de ${etiqueta.toLowerCase()}.`}
            >
                <CommandInput
                    placeholder={`Buscar ${etiqueta.toLowerCase()}...`}
                />
                <CommandList>
                    <CommandEmpty>Sin coincidencias.</CommandEmpty>

                    <CommandGroup>
                        <CommandItem
                            value={todos}
                            onSelect={() => elegir(null)}
                        >
                            <Check
                                className={cn(
                                    'text-primary',
                                    valor !== null && 'opacity-0',
                                )}
                            />
                            {todos}
                        </CommandItem>
                    </CommandGroup>

                    <CommandSeparator />

                    <CommandGroup>
                        {opciones.map((opcion) => (
                            <CommandItem
                                key={opcion.value}
                                value={opcion.label}
                                onSelect={() => elegir(opcion.value)}
                            >
                                <Check
                                    className={cn(
                                        'text-primary',
                                        valor !== opcion.value && 'opacity-0',
                                    )}
                                />
                                <span className="min-w-0 flex-1">
                                    {opcion.label}
                                </span>
                            </CommandItem>
                        ))}
                    </CommandGroup>
                </CommandList>
            </CommandDialog>
        </>
    );
}
