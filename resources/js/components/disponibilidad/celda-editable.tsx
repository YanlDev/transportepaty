import { router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useRef, useState } from 'react';
import { flushSync } from 'react-dom';
import disponibilidad from '@/actions/App/Http/Controllers/DisponibilidadController';
import { ValorCelda } from '@/components/disponibilidad/valor-celda';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { EnumOption, OrigenDato } from '@/types/fleet';

/** Radix Select no admite items con value vacío. */
const SIN_VALOR = 'sin_valor';

type CampoEditable =
    | 'carreta_id'
    | 'conductor_id'
    | 'tipo_carga'
    | 'cliente'
    | 'origen_id'
    | 'destino_id'
    | 'ubicacion_id'
    | 'observaciones'
    | 'proximas_paradas'
    | 'fecha_disponible';

type Control =
    | { tipo: 'select'; opciones: EnumOption[]; placeholder: string }
    | { tipo: 'texto'; placeholder?: string }
    | { tipo: 'fecha' };

type Props = {
    tractoId: number;
    fecha: string;
    campo: CampoEditable;
    valor: string | null;
    valorLabel: string | null;
    origen?: OrigenDato;
    control: Control;
};

type Direccion = 'up' | 'down' | 'left' | 'right';

const FLECHAS: Record<string, Direccion> = {
    ArrowUp: 'up',
    ArrowDown: 'down',
    ArrowLeft: 'left',
    ArrowRight: 'right',
};

/** La celda vecina en la dirección pedida, o null si es borde de la tabla. */
function celdaVecina(
    celda: HTMLTableCellElement,
    direccion: Direccion,
): HTMLTableCellElement | null {
    const fila = celda.closest('tr');

    if (!fila) {
        return null;
    }

    if (direccion === 'left' || direccion === 'right') {
        const celdas = Array.from(fila.cells);
        const indice = celdas.indexOf(celda);

        return celdas[indice + (direccion === 'left' ? -1 : 1)] ?? null;
    }

    const cuerpo = fila.closest('tbody');

    if (!cuerpo) {
        return null;
    }

    const filas = Array.from(cuerpo.rows);
    const indiceFila = filas.indexOf(fila as HTMLTableRowElement);
    const indiceCol = Array.from(fila.cells).indexOf(celda);
    const filaVecina = filas[indiceFila + (direccion === 'up' ? -1 : 1)];

    return filaVecina?.cells[indiceCol] ?? null;
}

/** Mueve el foco del teclado a la celda vecina, si el control lo permite. */
function enfocarVecina(
    celda: HTMLTableCellElement | null,
    direccion: Direccion,
) {
    const destino = celda && celdaVecina(celda, direccion);

    destino?.querySelector<HTMLElement>('button, input')?.focus();
}

/**
 * Una celda de la hoja de disponibilidad: en reposo se ve como cualquier
 * ValorCelda, y al hacer click se convierte en el control que corresponda
 * para editarla ahí mismo, sin abrir ningún diálogo. Las flechas mueven el
 * foco entre celdas y, al confirmar un valor, salta a la de abajo — así se
 * puede llenar una columna entera sin volver a tocar el mouse.
 */
export function CeldaEditable({
    tractoId,
    fecha,
    campo,
    valor,
    valorLabel,
    origen,
    control,
}: Props) {
    const [editando, setEditando] = useState(false);
    const [busquedaOpcion, setBusquedaOpcion] = useState('');
    const canceladoRef = useRef(false);
    const triggerRef = useRef<HTMLButtonElement>(null);

    const guardar = (nuevoValor: string) => {
        const limpio = nuevoValor === '' ? null : nuevoValor;

        if (limpio === valor) {
            return;
        }

        router.patch(
            disponibilidad.actualizarCelda(tractoId).url,
            { fecha, campo, valor: limpio },
            { preserveScroll: true },
        );
    };

    if (!editando) {
        return (
            <button
                type="button"
                className="w-full rounded px-1 py-0.5 text-left hover:bg-muted"
                onClick={() => setEditando(true)}
                onKeyDown={(evento) => {
                    const direccion = FLECHAS[evento.key];

                    if (direccion) {
                        evento.preventDefault();
                        enfocarVecina(
                            evento.currentTarget.closest('td'),
                            direccion,
                        );
                    }
                }}
            >
                <ValorCelda valor={valorLabel} origen={origen} />
            </button>
        );
    }

    if (control.tipo === 'select') {
        const termino = busquedaOpcion.trim().toLowerCase();
        const opcionesFiltradas = termino
            ? control.opciones.filter((opcion) =>
                  opcion.label.toLowerCase().includes(termino),
              )
            : control.opciones;

        return (
            <Select
                defaultOpen
                defaultValue={valor ?? SIN_VALOR}
                onValueChange={(nuevoValor) => {
                    const celda = triggerRef.current?.closest('td') ?? null;

                    flushSync(() => setEditando(false));
                    guardar(nuevoValor === SIN_VALOR ? '' : nuevoValor);
                    enfocarVecina(celda, 'down');
                }}
                onOpenChange={(abierto) => {
                    if (!abierto) {
                        setEditando(false);
                        setBusquedaOpcion('');
                    }
                }}
            >
                <SelectTrigger
                    ref={triggerRef}
                    className="h-7 w-full"
                    aria-label={campo}
                >
                    <SelectValue placeholder={control.placeholder} />
                </SelectTrigger>
                <SelectContent>
                    {control.opciones.length > 8 && (
                        <div className="flex items-center gap-1.5 border-b px-2 pb-1.5">
                            <Search className="size-3.5 shrink-0 text-muted-foreground" />
                            <input
                                autoFocus
                                value={busquedaOpcion}
                                onChange={(evento) =>
                                    setBusquedaOpcion(evento.target.value)
                                }
                                onKeyDown={(evento) => {
                                    const dejaPasar = [
                                        'ArrowDown',
                                        'ArrowUp',
                                        'Home',
                                        'End',
                                        'Enter',
                                        'Escape',
                                    ];

                                    if (!dejaPasar.includes(evento.key)) {
                                        evento.stopPropagation();
                                    }
                                }}
                                placeholder="Buscar..."
                                className="h-7 w-full bg-transparent text-sm outline-none placeholder:text-muted-foreground"
                            />
                        </div>
                    )}
                    <SelectItem value={SIN_VALOR}>
                        {control.placeholder}
                    </SelectItem>
                    {opcionesFiltradas.map((opcion) => (
                        <SelectItem key={opcion.value} value={opcion.value}>
                            {opcion.label}
                        </SelectItem>
                    ))}
                    {opcionesFiltradas.length === 0 && (
                        <p className="px-2 py-1.5 text-xs text-muted-foreground">
                            Sin resultados
                        </p>
                    )}
                </SelectContent>
            </Select>
        );
    }

    return (
        <Input
            autoFocus
            type={control.tipo === 'fecha' ? 'date' : 'text'}
            className="h-7"
            placeholder={
                control.tipo === 'texto' ? control.placeholder : undefined
            }
            defaultValue={valor ?? ''}
            onBlur={(evento) => {
                if (canceladoRef.current) {
                    canceladoRef.current = false;
                    setEditando(false);

                    return;
                }

                setEditando(false);
                guardar(evento.target.value);
            }}
            onKeyDown={(evento) => {
                if (evento.key === 'Escape') {
                    canceladoRef.current = true;
                    evento.currentTarget.blur();

                    return;
                }

                const direccion =
                    evento.key === 'Enter' ? 'down' : FLECHAS[evento.key];

                // Solo arriba/abajo saltan de celda al confirmar: izquierda y
                // derecha mueven el cursor dentro del texto, como en cualquier
                // campo — igual que Excel al editar el contenido de una celda.
                if (direccion !== 'up' && direccion !== 'down') {
                    return;
                }

                evento.preventDefault();

                const celda = evento.currentTarget.closest('td');
                const nuevoValor = evento.currentTarget.value;

                flushSync(() => setEditando(false));
                guardar(nuevoValor);
                enfocarVecina(celda, direccion);
            }}
        />
    );
}
