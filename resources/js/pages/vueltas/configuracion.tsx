import { Head, router, setLayoutProps, useForm } from '@inertiajs/react';
import { Pencil, Plus, Trash2, X } from 'lucide-react';
import { useState } from 'react';
import hub from '@/actions/App/Http/Controllers/ConfiguracionController';
import configuracion from '@/actions/App/Http/Controllers/ConfiguracionProgramacionController';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialogo-responsivo';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { cn } from '@/lib/utils';
import type { Opcion } from '@/types/vueltas';

type ParadaOpcion = {
    zona_origen: string;
    zona_destino: string | null;
    tipo_carga: string;
};

type OpcionConfig = {
    id: number;
    nombre: string;
    descripcion: string | null;
    sentido: 'ida' | 'regreso';
    paradas: ParadaOpcion[];
    pide_destino: boolean;
    activa: boolean;
};

type Props = {
    opciones: OpcionConfig[];
    destinos: { id: number; lugar: string; zona: string; activo: boolean }[];
    palabras: { id: number; zona: string; palabra: string }[];
    zonas: { value: string; label: string; base: boolean }[];
    cargas: Opcion[];
};

/** El destino que se elige al programar: la carga particular de vuelta. */
const ELEGIDO = '__elegido';

function Seccion({
    titulo,
    descripcion,
    accion,
    children,
}: {
    titulo: string;
    descripcion: string;
    accion?: React.ReactNode;
    children: React.ReactNode;
}) {
    return (
        <section className="flex flex-col gap-3">
            <div className="flex items-end justify-between gap-3">
                <div>
                    <h2 className="text-base font-semibold">{titulo}</h2>
                    <p className="text-sm text-muted-foreground">
                        {descripcion}
                    </p>
                </div>
                {accion}
            </div>
            {children}
        </section>
    );
}

function SelectSimple({
    valor,
    opciones,
    placeholder,
    onCambiar,
}: {
    valor: string;
    opciones: Opcion[];
    placeholder?: string;
    onCambiar: (valor: string) => void;
}) {
    return (
        <Select value={valor || undefined} onValueChange={onCambiar}>
            <SelectTrigger className="w-full min-w-0">
                <SelectValue placeholder={placeholder} />
            </SelectTrigger>
            <SelectContent>
                {opciones.map((opcion) => (
                    <SelectItem key={opcion.value} value={opcion.value}>
                        {opcion.label}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}

/** Agregar o editar una opción de ida o de vuelta. */
function OpcionDialog({
    opcion,
    sentidoInicial,
    zonas,
    cargas,
    onCerrar,
}: {
    opcion: OpcionConfig | null;
    sentidoInicial: 'ida' | 'regreso';
    zonas: Opcion[];
    cargas: Opcion[];
    onCerrar: () => void;
}) {
    const { data, setData, post, put, processing, errors } = useForm({
        nombre: opcion?.nombre ?? '',
        descripcion: opcion?.descripcion ?? '',
        sentido: opcion?.sentido ?? sentidoInicial,
        activa: opcion?.activa ?? true,
        paradas: opcion?.paradas ?? [
            { zona_origen: '', zona_destino: '', tipo_carga: '' },
        ],
    });
    const errores = errors as Record<string, string | undefined>;

    const cambiarParada = (indice: number, cambios: Partial<ParadaOpcion>) =>
        setData(
            'paradas',
            data.paradas.map((parada, i) =>
                i === indice ? { ...parada, ...cambios } : parada,
            ),
        );

    const enviar = (evento: React.FormEvent) => {
        evento.preventDefault();
        const opciones = { preserveScroll: true, onSuccess: onCerrar };

        if (opcion) {
            put(configuracion.updateOpcion(opcion.id).url, opciones);

            return;
        }

        post(configuracion.storeOpcion().url, opciones);
    };

    const quitar = () => {
        if (!opcion || !window.confirm(`¿Quitar «${opcion.nombre}»?`)) {
            return;
        }

        router.delete(configuracion.destroyOpcion(opcion.id).url, {
            preserveScroll: true,
            onSuccess: onCerrar,
        });
    };

    return (
        <Dialog open onOpenChange={(abierto) => !abierto && onCerrar()}>
            <DialogContent className="md:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>
                        {opcion ? 'Editar opción' : 'Nueva opción'}
                    </DialogTitle>
                </DialogHeader>
                <form
                    id="opcion-tramo"
                    onSubmit={enviar}
                    className="flex flex-col gap-4"
                >
                    <div className="grid gap-4 sm:grid-cols-[1fr_10rem]">
                        <Field label="Nombre" error={errors.nombre} required>
                            {(id) => (
                                <Input
                                    id={id}
                                    placeholder="Concentrado a Pisco"
                                    value={data.nombre}
                                    onChange={(evento) =>
                                        setData('nombre', evento.target.value)
                                    }
                                />
                            )}
                        </Field>
                        <Field label="Tramo" required>
                            {() => (
                                <SelectSimple
                                    valor={data.sentido}
                                    opciones={[
                                        { value: 'ida', label: 'Ida' },
                                        { value: 'regreso', label: 'Vuelta' },
                                    ]}
                                    onCambiar={(valor) =>
                                        setData(
                                            'sentido',
                                            valor as 'ida' | 'regreso',
                                        )
                                    }
                                />
                            )}
                        </Field>
                    </div>
                    <Field label="Descripción" error={errors.descripcion}>
                        {(id) => (
                            <Input
                                id={id}
                                placeholder="Mina → Pisco"
                                value={data.descripcion}
                                onChange={(evento) =>
                                    setData('descripcion', evento.target.value)
                                }
                            />
                        )}
                    </Field>

                    <div className="flex flex-col gap-2">
                        <span className="text-sm font-medium">Paradas</span>
                        {data.paradas.map((parada, indice) => (
                            <div
                                key={indice}
                                className="grid grid-cols-[1fr_1fr_1fr_auto] gap-2"
                            >
                                <SelectSimple
                                    valor={parada.zona_origen}
                                    opciones={zonas}
                                    placeholder="Desde"
                                    onCambiar={(valor) =>
                                        cambiarParada(indice, {
                                            zona_origen: valor,
                                        })
                                    }
                                />
                                <SelectSimple
                                    valor={parada.zona_destino ?? ELEGIDO}
                                    opciones={[
                                        {
                                            value: ELEGIDO,
                                            label: 'Lo elige quien programa',
                                        },
                                        ...zonas,
                                    ]}
                                    placeholder="Hasta"
                                    onCambiar={(valor) =>
                                        cambiarParada(indice, {
                                            zona_destino:
                                                valor === ELEGIDO
                                                    ? null
                                                    : valor,
                                        })
                                    }
                                />
                                <SelectSimple
                                    valor={parada.tipo_carga}
                                    opciones={cargas}
                                    placeholder="Carga"
                                    onCambiar={(valor) =>
                                        cambiarParada(indice, {
                                            tipo_carga: valor,
                                        })
                                    }
                                />
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    aria-label="Quitar parada"
                                    disabled={data.paradas.length === 1}
                                    onClick={() =>
                                        setData(
                                            'paradas',
                                            data.paradas.filter(
                                                (_, i) => i !== indice,
                                            ),
                                        )
                                    }
                                >
                                    <X className="size-4" />
                                </Button>
                            </div>
                        ))}
                        {Object.keys(errores).some((clave) =>
                            clave.startsWith('paradas'),
                        ) && (
                            <p className="text-sm text-destructive">
                                Completa desde, hasta y carga de cada parada.
                            </p>
                        )}
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className="self-start"
                            onClick={() =>
                                setData('paradas', [
                                    ...data.paradas,
                                    {
                                        zona_origen:
                                            data.paradas.at(-1)?.zona_destino ??
                                            '',
                                        zona_destino: '',
                                        tipo_carga: '',
                                    },
                                ])
                            }
                        >
                            <Plus className="size-4" />
                            Parada
                        </Button>
                    </div>

                    <label className="flex items-center gap-2 text-sm">
                        <Checkbox
                            checked={data.activa}
                            onCheckedChange={(marcado) =>
                                setData('activa', marcado === true)
                            }
                        />
                        Se ofrece al programar
                    </label>
                </form>
                <DialogFooter className="gap-2 sm:justify-between">
                    {opcion ? (
                        <Button type="button" variant="ghost" onClick={quitar}>
                            Quitar opción
                        </Button>
                    ) : (
                        <span />
                    )}
                    <Button
                        type="submit"
                        form="opcion-tramo"
                        disabled={processing}
                    >
                        Guardar
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

/**
 * La configuración de la programación: las opciones para programar la ida y
 * la vuelta, a dónde va la carga particular y con qué palabras se reconoce
 * cada zona en las GR. Las zonas en sí no se editan: de ellas depende cómo se
 * arma cada ticket.
 */
export default function ConfiguracionProgramacion({
    opciones,
    destinos,
    palabras,
    zonas,
    cargas,
}: Props) {
    const [editando, setEditando] = useState<{
        opcion: OpcionConfig | null;
        sentido: 'ida' | 'regreso';
    } | null>(null);
    const nuevoDestino = useForm({ lugar: '', zona: '' });
    const [palabraNueva, setPalabraNueva] = useState<Record<string, string>>(
        {},
    );

    setLayoutProps({
        breadcrumbs: [
            { title: 'Configuración', href: hub.index().url },
            { title: 'Rutas y tramos', href: configuracion.index().url },
        ],
    });

    const etiquetaZona = (zona: string | null) =>
        zona === null
            ? 'destino elegido'
            : (zonas.find((opcion) => opcion.value === zona)?.label ?? zona);
    const etiquetaCarga = (carga: string) =>
        cargas.find((opcion) => opcion.value === carga)?.label ?? carga;

    const recorrido = (opcion: OpcionConfig) =>
        opcion.paradas
            .map(
                (parada, indice) =>
                    `${indice === 0 ? `${etiquetaZona(parada.zona_origen)} ` : ''}→ ${etiquetaZona(parada.zona_destino)} (${etiquetaCarga(parada.tipo_carga).toLowerCase()})`,
            )
            .join(' ');

    const agregarPalabra = (zona: string) => {
        const palabra = (palabraNueva[zona] ?? '').trim();

        if (palabra === '') {
            return;
        }

        router.post(
            configuracion.storePalabra().url,
            { zona, palabra },
            {
                preserveScroll: true,
                onSuccess: () =>
                    setPalabraNueva((anterior) => ({
                        ...anterior,
                        [zona]: '',
                    })),
            },
        );
    };

    const tablaOpciones = (sentido: 'ida' | 'regreso') => (
        <div className="overflow-x-auto rounded-xl border bg-card">
            <Table>
                <TableHeader>
                    <TableRow className="hover:bg-transparent">
                        <TableHead className="w-64">Opción</TableHead>
                        <TableHead>Recorrido</TableHead>
                        <TableHead className="w-24">Estado</TableHead>
                        <TableHead className="w-12" />
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {opciones
                        .filter((opcion) => opcion.sentido === sentido)
                        .map((opcion) => (
                            <TableRow
                                key={opcion.id}
                                className={cn(!opcion.activa && 'opacity-50')}
                            >
                                <TableCell className="font-medium">
                                    {opcion.nombre}
                                    {opcion.descripcion && (
                                        <span className="block text-xs font-normal text-muted-foreground">
                                            {opcion.descripcion}
                                        </span>
                                    )}
                                </TableCell>
                                <TableCell className="text-sm text-muted-foreground">
                                    {recorrido(opcion)}
                                </TableCell>
                                <TableCell className="text-xs">
                                    {opcion.activa ? 'Se ofrece' : 'Oculta'}
                                </TableCell>
                                <TableCell>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label={`Editar ${opcion.nombre}`}
                                        onClick={() =>
                                            setEditando({ opcion, sentido })
                                        }
                                    >
                                        <Pencil className="size-4" />
                                    </Button>
                                </TableCell>
                            </TableRow>
                        ))}
                </TableBody>
            </Table>
        </div>
    );

    return (
        <div className="flex h-full flex-1 flex-col gap-10 p-4 md:p-6">
            <Head title="Configuración de la programación" />

            <Seccion
                titulo="Opciones de ida"
                descripcion="Lo que se elige al programar la salida desde Juliaca o la mina."
                accion={
                    <Button
                        size="sm"
                        onClick={() =>
                            setEditando({ opcion: null, sentido: 'ida' })
                        }
                    >
                        <Plus className="size-4" />
                        Opción
                    </Button>
                }
            >
                {tablaOpciones('ida')}
            </Seccion>

            <Seccion
                titulo="Opciones de vuelta"
                descripcion="Lo que se elige cuando se sabe con qué regresa la unidad."
                accion={
                    <Button
                        size="sm"
                        onClick={() =>
                            setEditando({ opcion: null, sentido: 'regreso' })
                        }
                    >
                        <Plus className="size-4" />
                        Opción
                    </Button>
                }
            >
                {tablaOpciones('regreso')}
            </Seccion>

            <Seccion
                titulo="Destinos de la carga particular"
                descripcion="A dónde puede ir la carga particular de vuelta. Si no es Juliaca, de ahí la unidad sigue a Juliaca."
            >
                <div className="overflow-x-auto rounded-xl border bg-card">
                    <Table>
                        <TableHeader>
                            <TableRow className="hover:bg-transparent">
                                <TableHead>Destino</TableHead>
                                <TableHead>Zona</TableHead>
                                <TableHead className="w-12" />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {destinos.map((destino) => (
                                <TableRow key={destino.id}>
                                    <TableCell className="font-medium">
                                        {destino.lugar}
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {etiquetaZona(destino.zona)}
                                    </TableCell>
                                    <TableCell>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            aria-label={`Quitar ${destino.lugar}`}
                                            onClick={() =>
                                                window.confirm(
                                                    `¿Quitar ${destino.lugar}?`,
                                                ) &&
                                                router.delete(
                                                    configuracion.destroyDestino(
                                                        destino.id,
                                                    ).url,
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            <Trash2 className="size-4" />
                                        </Button>
                                    </TableCell>
                                </TableRow>
                            ))}
                            <TableRow className="hover:bg-transparent">
                                <TableCell>
                                    <Input
                                        placeholder="Nuevo destino"
                                        value={nuevoDestino.data.lugar}
                                        aria-invalid={
                                            Boolean(
                                                nuevoDestino.errors.lugar,
                                            ) || undefined
                                        }
                                        onChange={(evento) =>
                                            nuevoDestino.setData(
                                                'lugar',
                                                evento.target.value,
                                            )
                                        }
                                    />
                                </TableCell>
                                <TableCell>
                                    <SelectSimple
                                        valor={nuevoDestino.data.zona}
                                        opciones={zonas}
                                        placeholder="Zona"
                                        onCambiar={(valor) =>
                                            nuevoDestino.setData('zona', valor)
                                        }
                                    />
                                </TableCell>
                                <TableCell>
                                    <Button
                                        size="icon"
                                        aria-label="Agregar destino"
                                        disabled={nuevoDestino.processing}
                                        onClick={() =>
                                            nuevoDestino.post(
                                                configuracion.storeDestino()
                                                    .url,
                                                {
                                                    preserveScroll: true,
                                                    onSuccess: () =>
                                                        nuevoDestino.reset(),
                                                },
                                            )
                                        }
                                    >
                                        <Plus className="size-4" />
                                    </Button>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>
            </Seccion>

            <Seccion
                titulo="Zonas y palabras"
                descripcion="Cómo se reconoce cada zona en la dirección de la GR. Si aparece un lugar que no se reconoce, agrégalo a su zona."
            >
                <div className="overflow-x-auto rounded-xl border bg-card">
                    <Table>
                        <TableBody>
                            {zonas.map((zona) => (
                                <TableRow
                                    key={zona.value}
                                    className="hover:bg-transparent"
                                >
                                    <TableCell className="w-44 align-top font-medium">
                                        {zona.label}
                                        {zona.base && (
                                            <span className="ml-2 rounded bg-emerald-500/15 px-1.5 py-0.5 text-[10px] font-semibold text-emerald-700 uppercase dark:text-emerald-400">
                                                Base
                                            </span>
                                        )}
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex flex-wrap items-center gap-1.5">
                                            {palabras
                                                .filter(
                                                    (palabra) =>
                                                        palabra.zona ===
                                                        zona.value,
                                                )
                                                .map((palabra) => (
                                                    <span
                                                        key={palabra.id}
                                                        className="inline-flex items-center gap-1 rounded-full border py-0.5 pr-1 pl-2.5 text-xs"
                                                    >
                                                        {palabra.palabra}
                                                        <button
                                                            type="button"
                                                            aria-label={`Quitar ${palabra.palabra}`}
                                                            className="rounded-full p-0.5 text-muted-foreground hover:bg-accent hover:text-foreground"
                                                            onClick={() =>
                                                                router.delete(
                                                                    configuracion.destroyPalabra(
                                                                        palabra.id,
                                                                    ).url,
                                                                    {
                                                                        preserveScroll: true,
                                                                    },
                                                                )
                                                            }
                                                        >
                                                            <X className="size-3" />
                                                        </button>
                                                    </span>
                                                ))}
                                            <form
                                                className="inline-flex"
                                                onSubmit={(evento) => {
                                                    evento.preventDefault();
                                                    agregarPalabra(zona.value);
                                                }}
                                            >
                                                <Input
                                                    placeholder="+ palabra"
                                                    className="h-7 w-32 text-xs"
                                                    value={
                                                        palabraNueva[
                                                            zona.value
                                                        ] ?? ''
                                                    }
                                                    onChange={(evento) =>
                                                        setPalabraNueva(
                                                            (anterior) => ({
                                                                ...anterior,
                                                                [zona.value]:
                                                                    evento
                                                                        .target
                                                                        .value,
                                                            }),
                                                        )
                                                    }
                                                />
                                            </form>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
            </Seccion>

            {editando && (
                <OpcionDialog
                    key={editando.opcion?.id ?? `nueva-${editando.sentido}`}
                    opcion={editando.opcion}
                    sentidoInicial={editando.sentido}
                    zonas={zonas}
                    cargas={cargas}
                    onCerrar={() => setEditando(null)}
                />
            )}
        </div>
    );
}
