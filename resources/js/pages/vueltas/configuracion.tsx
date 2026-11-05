import { Head, Link, router, setLayoutProps, useForm } from '@inertiajs/react';
import { Plus, Trash2, X } from 'lucide-react';
import { useState } from 'react';
import hub from '@/actions/App/Http/Controllers/ConfiguracionController';
import configuracion from '@/actions/App/Http/Controllers/ConfiguracionProgramacionController';
import rutas from '@/actions/App/Http/Controllers/RutaController';
import { Button } from '@/components/ui/button';
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
import type { Opcion } from '@/types/vueltas';

type Props = {
    destinos: { id: number; lugar: string; zona: string; activo: boolean }[];
    palabras: { id: number; zona: string; palabra: string }[];
    zonas: { value: string; label: string; base: boolean }[];
};

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

/**
 * La configuración de la programación: a dónde va la carga particular a otro
 * destino y con qué palabras se reconoce cada zona en las GR. Las rutas
 * (recorrido y dotación de cada tramo) tienen su propio catálogo. Las zonas
 * en sí no se editan: de ellas depende cómo se arma cada ticket.
 */
export default function ConfiguracionProgramacion({
    destinos,
    palabras,
    zonas,
}: Props) {
    const nuevoDestino = useForm({ lugar: '', zona: '' });
    const etiquetaZona = (zona: string) =>
        zonas.find((opcion) => opcion.value === zona)?.label ?? zona;
    const [palabraNueva, setPalabraNueva] = useState<Record<string, string>>(
        {},
    );

    setLayoutProps({
        breadcrumbs: [
            { title: 'Configuración', href: hub.index().url },
            { title: 'Programación', href: configuracion.index().url },
        ],
    });

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

    return (
        <div className="flex h-full flex-1 flex-col gap-10 p-4 md:p-6">
            <Head title="Configuración de la programación" />

            <p className="rounded-xl border bg-card p-4 text-sm text-muted-foreground">
                Lo que se elige al programar la ida y la vuelta (recorrido y
                dotación) está en el{' '}
                <Link
                    href={rutas.index().url}
                    className="font-medium text-foreground underline underline-offset-2"
                >
                    catálogo de rutas
                </Link>
                .
            </p>

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
        </div>
    );
}
