import { ArrowLeft, Check, ChevronDownIcon, PlusIcon } from 'lucide-react';
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
import { cn } from '@/lib/utils';

export type OpcionBuscable = {
    valor: number;
    etiqueta: string;
    /** Texto extra que también matchea al escribir, p. ej. una placa. */
    detalle?: string;
};

/**
 * El campo cerrado: se ve como un desplegable y al pulsarlo pide abrir el
 * buscador. No despliega nada por su cuenta.
 */
export function CampoSelector({
    id,
    etiqueta,
    elegida,
    invalido,
    onAbrir,
}: {
    /** El id del `<label>` que lo describe, para que el click en la etiqueta lo enfoque. */
    id?: string;
    etiqueta: string;
    elegida?: OpcionBuscable;
    invalido?: boolean;
    onAbrir: () => void;
}) {
    return (
        <button
            id={id}
            type="button"
            role="combobox"
            aria-expanded={false}
            aria-label={etiqueta}
            aria-invalid={invalido}
            onClick={onAbrir}
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
    );
}

/**
 * El buscador, que reemplaza al formulario dentro del mismo diálogo mientras
 * se elige.
 *
 * Antes esto era un popover anclado al campo, y en un formulario que ya vive
 * en un modal el panel terminaba montado sobre los otros campos o saliéndose
 * del recuadro. Ocupando el diálogo entero no hay dos capas que pelear: se
 * elige, se vuelve, y el nombre largo se lee completo porque el ancho es el
 * del diálogo y no el del campo.
 */
export function PanelBuscador({
    titulo,
    valor,
    opciones,
    onElegir,
    onVolver,
    crear,
}: {
    titulo: string;
    valor: number | null;
    opciones: OpcionBuscable[];
    onElegir: (valor: number) => void;
    onVolver: () => void;
    /**
     * Da de alta ahí mismo lo que no está en la lista, con el texto que se
     * alcanzó a escribir. Sin esto el buscador es solo de lectura.
     */
    crear?: {
        /** Se antepone al texto escrito, p. ej. «Crear cliente». */
        etiqueta: string;
        onCrear: (texto: string) => void;
    };
}) {
    const [busqueda, setBusqueda] = useState('');
    const escrito = busqueda.trim();

    return (
        <div className="flex flex-col gap-3">
            <div className="flex items-center gap-2">
                <button
                    type="button"
                    onClick={onVolver}
                    aria-label="Volver al formulario"
                    className="rounded-md p-1 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                >
                    <ArrowLeft className="size-4" />
                </button>
                <p className="text-sm font-medium">{titulo}</p>
            </div>

            <Command className="rounded-md border">
                <CommandInput
                    autoFocus
                    value={busqueda}
                    onValueChange={setBusqueda}
                    placeholder={`Buscar ${titulo.toLowerCase()}...`}
                />
                <CommandList className="max-h-[min(24rem,50vh)]">
                    <CommandEmpty>Sin coincidencias.</CommandEmpty>

                    <CommandGroup>
                        {opciones.map((opcion) => (
                            <CommandItem
                                key={opcion.valor}
                                // cmdk filtra por este texto: lleva también el
                                // detalle para poder buscar por placa o por
                                // cualquier dato secundario.
                                value={`${opcion.etiqueta} ${opcion.detalle ?? ''}`}
                                onSelect={() => onElegir(opcion.valor)}
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

                {/* Fuera del `CommandList` a propósito: dentro, cmdk lo
                    filtraría junto con las opciones y desaparecería justo
                    cuando no hay coincidencias, que es cuando hace falta. */}
                {crear && escrito !== '' && (
                    <>
                        <CommandSeparator />
                        <button
                            type="button"
                            onClick={() => crear.onCrear(escrito)}
                            className="flex w-full items-center gap-2 px-3 py-2.5 text-left text-sm outline-none hover:bg-accent focus-visible:bg-accent"
                        >
                            <PlusIcon className="size-4 shrink-0 opacity-60" />
                            <span className="min-w-0 truncate">
                                {crear.etiqueta}{' '}
                                <span className="font-medium">«{escrito}»</span>
                            </span>
                        </button>
                    </>
                )}
            </Command>
        </div>
    );
}
