import { router, useHttp } from '@inertiajs/react';
import {
    ArrowUpFromLine,
    Calculator,
    ClipboardPlus,
    FileOutput,
    PackagePlus,
    Search,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { ElementType } from 'react';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { usePermisos } from '@/hooks/use-permisos';
import { buscar } from '@/routes';
import { create as nuevoIngreso } from '@/routes/almacen/ingresos';
import { create as nuevoVale } from '@/routes/almacen/salidas';
import { cotizador } from '@/routes/cotizaciones';
import { create as nuevaOrden } from '@/routes/mantenimiento/ordenes';
import { emitir as emitirGr } from '@/routes/viajes';
import type { Permiso } from '@/types';

type Resultado = {
    id: string;
    titulo: string;
    detalle: string | null;
    url: string;
};

type Grupo = { titulo: string; items: Resultado[] };

type Accion = {
    titulo: string;
    url: string;
    icono: ElementType;
    permiso: Permiso;
};

/** El evento con que cualquier botón (la barra del celular) abre el buscador. */
const EVENTO = 'transpaty:buscar';

/** Con menos letras el servidor no busca: casi todo coincide. */
const MINIMO = 2;

/** Lo que se espera a que la persona deje de escribir antes de preguntar. */
const ESPERA = 200;

export function abrirBuscador(): void {
    window.dispatchEvent(new Event(EVENTO));
}

/**
 * Lo que se ofrece con el campo vacío: las tareas con pantalla propia, para
 * empezarlas desde donde se esté. Cada una aparece con su permiso.
 */
const ACCIONES: Accion[] = [
    {
        titulo: 'Despachar un vale de almacén',
        url: nuevoVale().url,
        icono: ArrowUpFromLine,
        permiso: 'almacen.despachar',
    },
    {
        titulo: 'Registrar una compra',
        url: nuevoIngreso().url,
        icono: PackagePlus,
        permiso: 'almacen.ingresar',
    },
    {
        titulo: 'Nueva orden de trabajo',
        url: nuevaOrden().url,
        icono: ClipboardPlus,
        permiso: 'mantenimiento.gestionar',
    },
    {
        titulo: 'Emitir una GR',
        url: emitirGr().url,
        icono: FileOutput,
        permiso: 'viajes.emitir',
    },
    {
        titulo: 'Cotizar una ruta',
        url: cotizador().url,
        icono: Calculator,
        permiso: 'cotizaciones.crear',
    },
];

/**
 * El buscador de toda la app. Se abre con Ctrl+K (⌘K en Mac) o con el botón
 * «Buscar», y lleva directo a una placa, un ticket, una OT, una GR, un
 * artículo, un conductor o un cliente: lo que la persona puede ver.
 *
 * Quien filtra es el servidor (`BuscadorGlobal`), no `cmdk`: acá solo se
 * muestra lo que llega y se navega con las flechas y Enter.
 */
export function BuscadorGlobal() {
    const { puede } = usePermisos();
    const [abierto, setAbierto] = useState(false);
    const [texto, setTexto] = useState('');
    const [grupos, setGrupos] = useState<Grupo[]>([]);
    /** El texto de la última respuesta recibida: distingue «buscando» de «no hay». */
    const [respondido, setRespondido] = useState('');
    const http = useHttp<Record<string, never>, { grupos: Grupo[] }>({});
    const espera = useRef<ReturnType<typeof setTimeout> | null>(null);
    const ultimo = useRef('');

    useEffect(() => {
        const abrir = () => setAbierto(true);
        const tecla = (evento: KeyboardEvent) => {
            if (
                evento.key.toLowerCase() === 'k' &&
                (evento.metaKey || evento.ctrlKey)
            ) {
                evento.preventDefault();
                setAbierto((actual) => !actual);
            }
        };

        window.addEventListener(EVENTO, abrir);
        window.addEventListener('keydown', tecla);

        return () => {
            window.removeEventListener(EVENTO, abrir);
            window.removeEventListener('keydown', tecla);
        };
    }, []);

    const escribir = (valor: string) => {
        setTexto(valor);
        ultimo.current = valor.trim();

        if (espera.current) {
            clearTimeout(espera.current);
        }

        if (ultimo.current.length < MINIMO) {
            setGrupos([]);
            setRespondido('');

            return;
        }

        const pedido = ultimo.current;

        espera.current = setTimeout(() => {
            http.cancel();
            http.get(buscar({ query: { q: pedido } }).url, {
                onSuccess: (respuesta) => {
                    // Una respuesta vieja que llega tarde no pisa a la nueva.
                    if (ultimo.current === pedido) {
                        setGrupos(respuesta.grupos);
                        setRespondido(pedido);
                    }
                },
            });
        }, ESPERA);
    };

    const alCambiar = (abrir: boolean) => {
        setAbierto(abrir);

        if (!abrir) {
            escribir('');
        }
    };

    const ir = (url: string) => {
        alCambiar(false);
        router.visit(url);
    };

    const pedido = texto.trim();
    const buscando = pedido.length >= MINIMO && respondido !== pedido;
    const acciones = ACCIONES.filter((accion) => puede(accion.permiso));

    return (
        <Dialog open={abierto} onOpenChange={alCambiar}>
            <DialogHeader className="sr-only">
                <DialogTitle>Buscar</DialogTitle>
                <DialogDescription>
                    Busca una placa, un ticket, una orden, una guía, un
                    artículo, un conductor o un cliente.
                </DialogDescription>
            </DialogHeader>
            <DialogContent
                className="top-4 translate-y-0 overflow-hidden p-0 sm:top-[15%] sm:max-w-xl"
                showCloseButton={false}
            >
                <Command shouldFilter={false} label="Buscador">
                    <CommandInput
                        value={texto}
                        onValueChange={escribir}
                        placeholder="Placa, ticket, OT, GR, artículo, conductor…"
                    />
                    <CommandList className="max-h-[60dvh]">
                        {pedido.length < MINIMO && acciones.length > 0 && (
                            <CommandGroup heading="Empezar una tarea">
                                {acciones.map((accion) => (
                                    <CommandItem
                                        key={accion.url}
                                        value={accion.url}
                                        onSelect={() => ir(accion.url)}
                                        className="min-h-11"
                                    >
                                        <accion.icono className="size-4 text-muted-foreground" />
                                        {accion.titulo}
                                    </CommandItem>
                                ))}
                            </CommandGroup>
                        )}

                        {pedido.length < MINIMO && acciones.length === 0 && (
                            <p className="flex items-center justify-center gap-2 py-8 text-sm text-muted-foreground">
                                <Search className="size-4" />
                                Escribe al menos dos letras.
                            </p>
                        )}

                        {buscando && (
                            <p className="flex items-center justify-center gap-2 py-8 text-sm text-muted-foreground">
                                <Spinner />
                                Buscando…
                            </p>
                        )}

                        {!buscando && pedido.length >= MINIMO && (
                            <CommandEmpty>
                                Nada coincide con «{pedido}».
                            </CommandEmpty>
                        )}

                        {!buscando &&
                            grupos.map((grupo) => (
                                <CommandGroup
                                    key={grupo.titulo}
                                    heading={grupo.titulo}
                                >
                                    {grupo.items.map((item) => (
                                        <CommandItem
                                            key={item.id}
                                            value={item.id}
                                            onSelect={() => ir(item.url)}
                                            className="min-h-11"
                                        >
                                            <span className="font-medium">
                                                {item.titulo}
                                            </span>
                                            {item.detalle && (
                                                <span className="truncate text-muted-foreground">
                                                    {item.detalle}
                                                </span>
                                            )}
                                        </CommandItem>
                                    ))}
                                </CommandGroup>
                            ))}
                    </CommandList>
                </Command>
            </DialogContent>
        </Dialog>
    );
}
