import { Head, router, setLayoutProps } from '@inertiajs/react';
import { HandCoins, Plus, Tags } from 'lucide-react';
import { useState } from 'react';
import gastos from '@/actions/App/Http/Controllers/ValeGastoController';
import { AccionPrincipalMovil } from '@/components/accion-principal-movil';
import { EmptyState } from '@/components/empty-state';
import { EncabezadoPantalla } from '@/components/encabezado-pantalla';
import { FiltroSelect } from '@/components/filtro-select';
import { CategoriasDialog } from '@/components/gastos/categorias-dialog';
import { PedirValeDialog } from '@/components/gastos/pedir-vale-dialog';
import { Button } from '@/components/ui/button';
import { Paginacion } from '@/components/ui/paginacion';
import { StatusBadge } from '@/components/ui/status-badge';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatearFecha, formatearPlaca, formatearSoles } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { EnumOption, Paginator } from '@/types/fleet';
import { TONO_VALE_GASTO } from '@/types/gastos';
import type {
    CategoriaGasto,
    CategoriaGastoAdministrada,
    ValeGastoCabecera,
} from '@/types/gastos';

type Filtros = {
    estado: string | null;
    area: string | null;
    categoria: number | null;
};

type Props = {
    vales: Paginator<ValeGastoCabecera>;
    filtros: Filtros;
    estados: EnumOption[];
    areas: EnumOption[];
    /** Las activas: las que se ofrecen al pedir. */
    categorias: CategoriaGasto[];
    /** Todas, para quien las administra; null si no le toca. */
    todasLasCategorias: CategoriaGastoAdministrada[] | null;
    vehiculos: { id: number; placa: string }[];
    areaDelUsuario: string;
    porAprobar: number;
    /** Ve los vales de todas las áreas y no solo los suyos. */
    veTodos: boolean;
    puedeSolicitar: boolean;
    maximoKb: number;
};

/**
 * Los vales de gasto: lo que cada área pide para gastar fuera del almacén.
 * Quien pide ve los suyos; gerencia y contabilidad, los de todos.
 */
export default function GastosIndex({
    vales,
    filtros,
    estados,
    areas,
    categorias,
    todasLasCategorias,
    vehiculos,
    areaDelUsuario,
    porAprobar,
    veTodos,
    puedeSolicitar,
    maximoKb,
}: Props) {
    setLayoutProps({
        breadcrumbs: [{ title: 'Vales de gasto', href: gastos.index().url }],
    });

    const [pidiendo, setPidiendo] = useState(false);
    const [verCategorias, setVerCategorias] = useState(false);

    const filtrar = (cambio: Partial<Filtros>) => {
        const proximos = { ...filtros, ...cambio };

        router.get(
            gastos.index().url,
            Object.fromEntries(
                Object.entries(proximos).filter(([, valor]) => valor !== null),
            ),
            { preserveState: true, preserveScroll: true },
        );
    };

    return (
        <div className="mx-auto flex h-full w-full max-w-[1400px] flex-1 flex-col gap-4 p-4 md:p-6">
            <Head title="Vales de gasto" />

            <EncabezadoPantalla
                titulo="Vales de gasto"
                descripcion={
                    veTodos
                        ? 'Lo que cada área pide para gastar. El vale se pide antes de gastar y lo aprueba gerencia.'
                        : 'Pide acá la plata que tu área necesita gastar. El vale se pide antes de gastar y lo aprueba gerencia.'
                }
                icono={HandCoins}
                acciones={
                    <div className="flex flex-wrap gap-2">
                        {todasLasCategorias !== null && (
                            <Button
                                variant="outline"
                                onClick={() => setVerCategorias(true)}
                            >
                                <Tags className="size-4" />
                                Categorías
                            </Button>
                        )}
                        {puedeSolicitar && (
                            <Button
                                onClick={() => setPidiendo(true)}
                                className="max-md:hidden"
                            >
                                <Plus className="size-4" />
                                Pedir vale
                            </Button>
                        )}
                    </div>
                }
            />

            <div className="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
                <FiltroSelect
                    valor={filtros.estado}
                    onCambio={(estado) => filtrar({ estado })}
                    todos="Todos"
                    etiqueta="Estado"
                    opciones={estados}
                />
                {veTodos && (
                    <FiltroSelect
                        valor={filtros.area}
                        onCambio={(area) => filtrar({ area })}
                        todos="Todas las áreas"
                        etiqueta="Área"
                        opciones={areas}
                    />
                )}
                <FiltroSelect
                    valor={
                        filtros.categoria === null
                            ? null
                            : String(filtros.categoria)
                    }
                    onCambio={(categoria) =>
                        filtrar({
                            categoria:
                                categoria === null ? null : Number(categoria),
                        })
                    }
                    todos="Todas las categorías"
                    etiqueta="Categoría"
                    opciones={(todasLasCategorias ?? categorias).map(
                        (categoria) => ({
                            value: String(categoria.id),
                            label: categoria.nombre,
                        }),
                    )}
                />
                {porAprobar > 0 && filtros.estado !== 'solicitado' && (
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => filtrar({ estado: 'solicitado' })}
                    >
                        {porAprobar === 1
                            ? '1 por aprobar'
                            : `${porAprobar} por aprobar`}
                    </Button>
                )}
            </div>

            {vales.data.length === 0 ? (
                <EmptyState
                    icono={<HandCoins className="size-7" />}
                    titulo="No hay vales de gasto"
                    descripcion={
                        puedeSolicitar
                            ? 'Cuando tu área necesite gastar en algo, pídelo desde acá antes de hacerlo.'
                            : 'Cuando un área pida un vale, aparece acá para aprobarlo.'
                    }
                />
            ) : (
                <>
                    <div className="overflow-x-auto rounded-xl border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>N°</TableHead>
                                    <TableHead>Pedido</TableHead>
                                    <TableHead>Área</TableHead>
                                    <TableHead>Categoría</TableHead>
                                    <TableHead>Qué</TableHead>
                                    <TableHead>Para</TableHead>
                                    <TableHead>Pidió</TableHead>
                                    <TableHead className="text-right">
                                        Monto
                                    </TableHead>
                                    <TableHead>Estado</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {vales.data.map((vale) => (
                                    <TableRow
                                        key={vale.id}
                                        className={cn(
                                            'cursor-pointer',
                                            vale.estado === 'anulado' &&
                                                'opacity-60',
                                        )}
                                        onClick={() =>
                                            router.visit(gastos.show(vale.id))
                                        }
                                    >
                                        <TableCell className="font-medium whitespace-nowrap">
                                            {vale.numero}
                                        </TableCell>
                                        <TableCell className="whitespace-nowrap">
                                            {formatearFecha(vale.solicitado)}
                                        </TableCell>
                                        <TableCell>{vale.area}</TableCell>
                                        <TableCell>{vale.categoria}</TableCell>
                                        <TableCell className="max-w-72 truncate">
                                            {vale.concepto}
                                        </TableCell>
                                        <TableCell className="whitespace-nowrap">
                                            {vale.placa
                                                ? formatearPlaca(vale.placa)
                                                : 'General'}
                                        </TableCell>
                                        <TableCell>
                                            {vale.solicitado_por ?? '—'}
                                        </TableCell>
                                        <TableCell className="text-right whitespace-nowrap tabular-nums">
                                            {formatearSoles(vale.monto)}
                                        </TableCell>
                                        <TableCell>
                                            <StatusBadge
                                                label={vale.estado_label}
                                                tone={
                                                    TONO_VALE_GASTO[vale.estado]
                                                }
                                            />
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                    <Paginacion paginador={vales} />
                </>
            )}

            {puedeSolicitar && (
                <>
                    <AccionPrincipalMovil
                        onClick={() => setPidiendo(true)}
                        icono={<Plus />}
                    >
                        Pedir vale
                    </AccionPrincipalMovil>
                    <PedirValeDialog
                        open={pidiendo}
                        onOpenChange={setPidiendo}
                        areas={areas}
                        areaDelUsuario={areaDelUsuario}
                        categorias={categorias}
                        vehiculos={vehiculos}
                        maximoKb={maximoKb}
                    />
                </>
            )}

            {todasLasCategorias !== null && (
                <CategoriasDialog
                    open={verCategorias}
                    onOpenChange={setVerCategorias}
                    categorias={todasLasCategorias}
                />
            )}
        </div>
    );
}
