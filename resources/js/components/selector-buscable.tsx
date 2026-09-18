import { Check, ChevronDownIcon, PlusIcon } from 'lucide-react';
import { useState } from 'react';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
    CommandSeparator,
} from '@/components/ui/command';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
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
 *
 * Va en popover y no en diálogo —al revés que `FiltroBuscable`— porque este
 * se usa dentro de formularios que ya viven en un `Dialog`, y dos modales
 * apilados se tapan entre sí. El panel se ancla al campo pero lleva ancho
 * propio, para que las razones sociales largas no se corten al ancho del
 * disparador, que era la razón por la que el hermano eligió diálogo.
 */
export function SelectorBuscable({
    id,
    valor,
    onCambio,
    etiqueta,
    opciones,
    invalido,
    crear,
}: {
    /** El id del `<label>` que lo describe, para que el click en la etiqueta enfoque el disparador. */
    id?: string;
    valor: number | null;
    onCambio: (valor: number) => void;
    etiqueta: string;
    opciones: OpcionBuscable[];
    invalido?: boolean;
    /**
     * Da de alta ahí mismo lo que no está en la lista, con el texto que se
     * alcanzó a escribir. Sin esto el selector es solo de lectura sobre las
     * opciones que recibe.
     */
    crear?: {
        /** Se antepone al texto escrito, p. ej. «Crear cliente». */
        etiqueta: string;
        onCrear: (texto: string) => void;
    };
}) {
    const [abierto, setAbierto] = useState(false);
    const [busqueda, setBusqueda] = useState('');
    const elegida = opciones.find((opcion) => opcion.valor === valor);

    const cerrar = () => {
        setAbierto(false);
        setBusqueda('');
    };

    const escrito = busqueda.trim();

    return (
        <Popover
            open={abierto}
            onOpenChange={(proximo) => (proximo ? setAbierto(true) : cerrar())}
        >
            <PopoverTrigger asChild>
                <button
                    id={id}
                    type="button"
                    role="combobox"
                    aria-expanded={abierto}
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
            </PopoverTrigger>

            {/* Al menos tan ancho como el campo, pero nunca menos de 22rem ni
                más que la pantalla: los nombres largos se leen enteros y en
                móvil no se desborda. */}
            <PopoverContent className="w-(--radix-popover-trigger-width) max-w-[calc(100vw-2rem)] min-w-[min(22rem,calc(100vw-2rem))] p-0">
                <Command>
                    <CommandInput
                        value={busqueda}
                        onValueChange={setBusqueda}
                        placeholder={`Buscar ${etiqueta.toLowerCase()}...`}
                    />
                    <CommandList>
                        <CommandEmpty>Sin coincidencias.</CommandEmpty>

                        <CommandGroup>
                            {opciones.map((opcion) => (
                                <CommandItem
                                    key={opcion.valor}
                                    // cmdk filtra por este texto: lleva también
                                    // el detalle para poder buscar por placa o
                                    // por cualquier dato secundario.
                                    value={`${opcion.etiqueta} ${opcion.detalle ?? ''}`}
                                    onSelect={() => {
                                        onCambio(opcion.valor);
                                        cerrar();
                                    }}
                                >
                                    <Check
                                        className={cn(
                                            'text-primary',
                                            valor !== opcion.valor &&
                                                'opacity-0',
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

                    {/* Fuera del `CommandList` a propósito: dentro, cmdk lo
                        filtraría junto con las opciones y desaparecería justo
                        cuando no hay coincidencias, que es cuando hace falta. */}
                    {crear && escrito !== '' && (
                        <>
                            <CommandSeparator />
                            <button
                                type="button"
                                onClick={() => {
                                    crear.onCrear(escrito);
                                    cerrar();
                                }}
                                className="flex w-full items-center gap-2 px-3 py-2.5 text-left text-sm outline-none hover:bg-accent focus-visible:bg-accent"
                            >
                                <PlusIcon className="size-4 shrink-0 opacity-60" />
                                <span className="min-w-0 truncate">
                                    {crear.etiqueta}{' '}
                                    <span className="font-medium">
                                        «{escrito}»
                                    </span>
                                </span>
                            </button>
                        </>
                    )}
                </Command>
            </PopoverContent>
        </Popover>
    );
}
