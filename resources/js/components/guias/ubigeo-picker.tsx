import { Check, Loader2, MapPin, X } from 'lucide-react';
import { useEffect, useId, useRef, useState } from 'react';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

type Opcion = { codigo: string; etiqueta: string };

type Props = {
    label: string;
    /** Código de 6 dígitos ya elegido, o cadena vacía. */
    value: string;
    /** Etiqueta legible del código elegido, para no re-consultarla. */
    etiqueta: string;
    onChange: (codigo: string, etiqueta: string) => void;
    error?: string;
};

/**
 * Selector de distrito que devuelve su ubigeo.
 *
 * Se busca por texto en vez de encadenar tres selects (departamento →
 * provincia → distrito) porque quien llena la guía sabe el nombre del lugar,
 * no en qué provincia cae: escribe «antauta» y aparece «Antauta, Melgar, Puno»
 * con su código. Son 1.874 distritos, así que la lista se pide al servidor.
 */
export function UbigeoPicker({
    label,
    value,
    etiqueta,
    onChange,
    error,
}: Props) {
    const id = useId();
    const [termino, setTermino] = useState('');
    const [opciones, setOpciones] = useState<Opcion[]>([]);
    const [buscando, setBuscando] = useState(false);
    const [abierto, setAbierto] = useState(false);
    const contenedor = useRef<HTMLDivElement>(null);

    // Con menos de dos letras no se consulta; lo ya cargado se descarta al
    // renderizar (`opcionesVisibles`) en vez de limpiando estado desde el
    // efecto, que provocaría un render de más.
    const consultable = termino.trim().length >= 2;
    const opcionesVisibles = consultable ? opciones : [];

    useEffect(() => {
        if (!consultable) {
            return;
        }

        const controlador = new AbortController();

        const timeout = setTimeout(() => {
            // El spinner arranca cuando arranca la consulta, no al teclear:
            // durante los 250 ms de espera todavía no hay nada en curso.
            setBuscando(true);

            fetch(`/ubigeos?buscar=${encodeURIComponent(termino)}`, {
                signal: controlador.signal,
                headers: { Accept: 'application/json' },
            })
                .then((respuesta) => respuesta.json())
                .then((datos: Opcion[]) => {
                    setOpciones(datos);
                    setAbierto(true);
                })
                .catch(() => {
                    /* Petición cancelada por otra tecla: no es un error. */
                })
                .finally(() => setBuscando(false));
        }, 250);

        return () => {
            clearTimeout(timeout);
            controlador.abort();
        };
    }, [termino, consultable]);

    // Cerrar al hacer clic fuera: la lista flota sobre el formulario.
    useEffect(() => {
        const alHacerClic = (evento: MouseEvent) => {
            if (!contenedor.current?.contains(evento.target as Node)) {
                setAbierto(false);
            }
        };

        document.addEventListener('mousedown', alHacerClic);

        return () => document.removeEventListener('mousedown', alHacerClic);
    }, []);

    const elegir = (opcion: Opcion) => {
        onChange(opcion.codigo, opcion.etiqueta);
        setTermino('');
        setOpciones([]);
        setAbierto(false);
    };

    return (
        <div className="grid gap-1.5" ref={contenedor}>
            <Label htmlFor={id}>
                {label}
                <span className="text-destructive"> *</span>
            </Label>

            {value ? (
                <div className="flex items-center gap-2 rounded-md border border-border bg-muted/40 px-3 py-2 text-sm">
                    <MapPin className="size-4 shrink-0 text-muted-foreground" />
                    <span className="min-w-0 flex-1 truncate">{etiqueta}</span>
                    <span className="shrink-0 font-mono text-xs text-muted-foreground">
                        {value}
                    </span>
                    <button
                        type="button"
                        onClick={() => onChange('', '')}
                        aria-label={`Cambiar ${label.toLowerCase()}`}
                        className="shrink-0 rounded p-0.5 text-muted-foreground hover:bg-accent hover:text-foreground"
                    >
                        <X className="size-4" />
                    </button>
                </div>
            ) : (
                <div className="relative">
                    <Input
                        id={id}
                        value={termino}
                        onChange={(e) => setTermino(e.target.value)}
                        onFocus={() =>
                            opcionesVisibles.length > 0 && setAbierto(true)
                        }
                        placeholder="Escribe el distrito: antauta, paracas, callao..."
                        autoComplete="off"
                    />
                    {buscando && (
                        <Loader2 className="absolute top-1/2 right-3 size-4 -translate-y-1/2 animate-spin text-muted-foreground" />
                    )}

                    {abierto && opcionesVisibles.length > 0 && (
                        <ul className="absolute z-50 mt-1 max-h-64 w-full overflow-auto rounded-md border border-border bg-popover p-1 shadow-md">
                            {opcionesVisibles.map((opcion) => (
                                <li key={opcion.codigo}>
                                    <button
                                        type="button"
                                        onClick={() => elegir(opcion)}
                                        className={cn(
                                            'flex w-full items-center gap-2 rounded-sm px-2 py-1.5 text-left text-sm',
                                            'hover:bg-accent hover:text-accent-foreground',
                                        )}
                                    >
                                        <Check className="size-3.5 shrink-0 opacity-0" />
                                        <span className="min-w-0 flex-1 truncate">
                                            {opcion.etiqueta}
                                        </span>
                                        <span className="shrink-0 font-mono text-xs text-muted-foreground">
                                            {opcion.codigo}
                                        </span>
                                    </button>
                                </li>
                            ))}
                        </ul>
                    )}

                    {abierto &&
                        !buscando &&
                        consultable &&
                        opcionesVisibles.length === 0 && (
                            <p className="absolute z-50 mt-1 w-full rounded-md border border-border bg-popover p-3 text-xs text-muted-foreground shadow-md">
                                Ningún distrito coincide con «{termino}».
                            </p>
                        )}
                </div>
            )}

            {error && <p className="text-xs text-destructive">{error}</p>}
        </div>
    );
}
