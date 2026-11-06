import { Head, Link } from '@inertiajs/react';
import { History, Search } from 'lucide-react';
import historial from '@/actions/App/Http/Controllers/HistorialController';
import { EmptyState } from '@/components/empty-state';
import Heading from '@/components/heading';
import {
    ACCIONES,
    CambiosMovimiento,
} from '@/components/historial/cambios-movimiento';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useFiltros } from '@/hooks/use-filtros';
import { cn } from '@/lib/utils';
import type { Movimiento } from '@/types/abastecimiento';
import type { Paginator } from '@/types/fleet';

type Filtros = {
    modelo: string;
    accion: string;
    usuario: string;
    desde: string;
    hasta: string;
    buscar: string;
};

type Props = {
    movimientos: Paginator<Movimiento & { modelo: string; modelo_id: number }>;
    filtros: Filtros;
    modelos: string[];
    usuarios: { id: number; name: string }[];
};

/** El nombre de cada tipo de registro, para leerlo en castellano. */
const MODELOS: Record<string, string> = {
    Viatico: 'Viático',
    Abastecimiento: 'Abastecimiento',
    Vuelta: 'Ticket',
    ParadaVuelta: 'Tramo de ticket',
    Viaje: 'GR / viaje',
    Vehiculo: 'Unidad',
    Conductor: 'Conductor',
    Cliente: 'Cliente',
    Factura: 'Factura',
    User: 'Usuario',
    Programacion: 'Salida programada',
    RevisionTaller: 'Revisión de taller',
    Cotizacion: 'Cotización',
};

const nombreModelo = (modelo: string) => MODELOS[modelo] ?? modelo;

const TODOS = '_todos';

const fechaHora = (iso: string) =>
    new Date(iso).toLocaleString('es-PE', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });

/**
 * Todo lo que se hizo en el sistema: quién creó, cambió, anuló o eliminó
 * qué registro, cuándo, y los valores de antes y después. Solo se lee.
 */
export default function HistorialIndex({
    movimientos: paginador,
    filtros,
    modelos,
    usuarios,
}: Props) {
    const { buscar, setBuscar, aplicar } = useFiltros(
        filtros,
        historial.index().url,
        { conservar: ['modelos', 'usuarios'] },
    );

    const selector = (
        clave: 'modelo' | 'accion' | 'usuario',
        placeholder: string,
        opciones: { value: string; label: string }[],
    ) => (
        <Select
            value={filtros[clave] || TODOS}
            onValueChange={(valor) =>
                aplicar({ [clave]: valor === TODOS ? '' : valor })
            }
        >
            <SelectTrigger className="w-full sm:w-44">
                <SelectValue placeholder={placeholder} />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value={TODOS}>{placeholder}</SelectItem>
                {opciones.map((opcion) => (
                    <SelectItem key={opcion.value} value={opcion.value}>
                        {opcion.label}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );

    return (
        <div className="flex flex-col gap-6">
            <Head title="Historial" />

            <Heading
                variant="small"
                title="Historial de movimientos"
                description="Cada alta, cambio, anulación o eliminación, con quién y cuándo. No se edita ni se borra."
            />

            <div className="flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                <div className="relative sm:w-56">
                    <Search className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        value={buscar}
                        onChange={(evento) => setBuscar(evento.target.value)}
                        placeholder="Ticket, placa, GR…"
                        className="pl-8"
                    />
                </div>
                {selector(
                    'modelo',
                    'Todo registro',
                    modelos.map((modelo) => ({
                        value: modelo,
                        label: nombreModelo(modelo),
                    })),
                )}
                {selector(
                    'accion',
                    'Toda acción',
                    Object.entries(ACCIONES).map(([value, { texto }]) => ({
                        value,
                        label: texto,
                    })),
                )}
                {selector(
                    'usuario',
                    'Todo usuario',
                    usuarios.map((usuario) => ({
                        value: String(usuario.id),
                        label: usuario.name,
                    })),
                )}
                <div className="flex items-center gap-2">
                    <Input
                        type="date"
                        aria-label="Desde"
                        value={filtros.desde}
                        onChange={(evento) =>
                            aplicar({ desde: evento.target.value })
                        }
                        className="w-full sm:w-40"
                    />
                    <span className="text-muted-foreground">–</span>
                    <Input
                        type="date"
                        aria-label="Hasta"
                        value={filtros.hasta}
                        onChange={(evento) =>
                            aplicar({ hasta: evento.target.value })
                        }
                        className="w-full sm:w-40"
                    />
                </div>
            </div>

            {paginador.data.length === 0 ? (
                <EmptyState
                    icono={<History />}
                    titulo="Sin movimientos"
                    descripcion="No hay movimientos con esos filtros."
                />
            ) : (
                <ol className="flex flex-col divide-y rounded-xl border">
                    {paginador.data.map((movimiento) => {
                        const accion = ACCIONES[movimiento.accion];

                        return (
                            <li
                                key={movimiento.id}
                                className="flex flex-col gap-1.5 p-3 sm:flex-row sm:gap-4"
                            >
                                <div className="flex shrink-0 flex-col gap-0.5 text-xs sm:w-40">
                                    <span className="text-muted-foreground tabular-nums">
                                        {fechaHora(movimiento.fecha)}
                                    </span>
                                    <span className="font-medium">
                                        {movimiento.usuario ?? 'Sistema'}
                                    </span>
                                </div>
                                <div className="flex min-w-0 flex-1 flex-col gap-1">
                                    <p className="flex flex-wrap items-center gap-1.5 text-sm">
                                        <span
                                            className={cn(
                                                'rounded-full px-2 py-0.5 text-xs font-medium ring-1',
                                                accion?.clase,
                                            )}
                                        >
                                            {accion?.texto ?? movimiento.accion}
                                        </span>
                                        <span className="text-muted-foreground">
                                            {nombreModelo(movimiento.modelo)}
                                        </span>
                                        <span className="font-medium">
                                            {movimiento.etiqueta ??
                                                `#${movimiento.modelo_id}`}
                                        </span>
                                    </p>
                                    <CambiosMovimiento
                                        accion={movimiento.accion}
                                        cambios={movimiento.cambios}
                                    />
                                </div>
                            </li>
                        );
                    })}
                </ol>
            )}

            {paginador.last_page > 1 && (
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <p className="text-sm text-muted-foreground">
                        Mostrando {paginador.from}–{paginador.to} de{' '}
                        {paginador.total}
                    </p>
                    <div className="flex flex-wrap gap-1">
                        {paginador.links.map((link, indice) => (
                            <Button
                                key={indice}
                                asChild={!!link.url}
                                size="sm"
                                variant={link.active ? 'default' : 'outline'}
                                disabled={!link.url}
                            >
                                {link.url ? (
                                    <Link
                                        href={link.url}
                                        preserveScroll
                                        dangerouslySetInnerHTML={{
                                            __html: link.label,
                                        }}
                                    />
                                ) : (
                                    <span
                                        dangerouslySetInnerHTML={{
                                            __html: link.label,
                                        }}
                                    />
                                )}
                            </Button>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}
