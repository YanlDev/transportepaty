import { Head, Link, setLayoutProps, useForm } from '@inertiajs/react';
import { ChevronRight, ClipboardList, Plus } from 'lucide-react';
import inventarios, {
    store,
} from '@/actions/App/Http/Controllers/Almacen/InventarioAlmacenController';
import { AlmacenNav, migasAlmacen } from '@/components/almacen/almacen-nav';
import { EstadoDocumento } from '@/components/almacen/estado-documento';
import { EmptyState } from '@/components/empty-state';
import { EncabezadoPantalla } from '@/components/encabezado-pantalla';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Paginacion } from '@/components/ui/paginacion';
import { Spinner } from '@/components/ui/spinner';
import { usePermisos } from '@/hooks/use-permisos';
import { formatearFecha, formatearFechaHora } from '@/lib/format';
import type { InventarioFila } from '@/types/almacen';
import type { Paginator } from '@/types/fleet';

type Props = {
    inventarios: Paginator<InventarioFila>;
    hayArticulos: boolean;
    hoy: string;
};

const TONO = {
    abierto: 'abierto',
    cerrado: 'vigente',
    anulado: 'anulado',
} as const;

/**
 * Los conteos físicos. El primero arranca el almacén (lo contado es el saldo
 * inicial); los siguientes cuadran el sistema con lo que hay en el estante.
 */
export default function InventariosIndex({
    inventarios: paginador,
    hayArticulos,
    hoy,
}: Props) {
    const { puede } = usePermisos();
    const { data, setData, post, processing, errors } = useForm({
        fecha: hoy,
        observacion: '',
    });

    setLayoutProps({
        breadcrumbs: migasAlmacen({
            title: 'Conteos',
            href: inventarios.index().url,
        }),
    });

    return (
        <div className="mx-auto flex h-full w-full max-w-[1400px] flex-1 flex-col gap-4 p-4 md:p-6">
            <Head title="Conteos de inventario" />

            <EncabezadoPantalla
                titulo="Conteos de inventario"
                descripcion="Se cuenta lo que hay en el estante; lo que no cuadra con el sistema entra o sale como ajuste."
                icono={ClipboardList}
            />

            <AlmacenNav actual="inventarios" />

            {puede('almacen.inventariar') && (
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        post(store().url);
                    }}
                    className="flex flex-col gap-3 rounded-xl border bg-card p-4 sm:flex-row sm:items-end"
                >
                    <div className="grid gap-1.5">
                        <Label htmlFor="conteo-fecha">Fecha del conteo</Label>
                        <Input
                            id="conteo-fecha"
                            type="date"
                            max={hoy}
                            value={data.fecha}
                            onChange={(e) => setData('fecha', e.target.value)}
                        />
                        <InputError message={errors.fecha} />
                    </div>
                    <div className="grid flex-1 gap-1.5">
                        <Label htmlFor="conteo-obs">Observación</Label>
                        <Input
                            id="conteo-obs"
                            value={data.observacion}
                            onChange={(e) =>
                                setData('observacion', e.target.value)
                            }
                            placeholder={
                                paginador.total === 0
                                    ? 'Conteo inicial del almacén'
                                    : 'Estante A, conteo de fin de mes…'
                            }
                        />
                    </div>
                    <Button type="submit" disabled={processing}>
                        {processing ? <Spinner /> : <Plus className="size-4" />}
                        Abrir conteo
                    </Button>
                </form>
            )}

            {paginador.total === 0 && (
                <p className="rounded-xl border border-sky-300 bg-sky-50 px-4 py-3 text-sm text-sky-900 dark:border-sky-800 dark:bg-sky-950/40 dark:text-sky-200">
                    Así arranca el almacén: abre un conteo, recorre los estantes
                    y anota cuánto hay de cada artículo
                    {hayArticulos
                        ? ''
                        : ' (los que no existen se crean ahí mismo)'}
                    . Al cerrarlo, lo contado queda como saldo inicial, con el
                    costo de su última factura o uno estimado.
                </p>
            )}

            {paginador.data.length === 0 ? (
                <EmptyState
                    icono={<ClipboardList className="size-7" />}
                    titulo="Todavía no hay conteos"
                />
            ) : (
                <>
                    <ul className="flex flex-col gap-2">
                        {paginador.data.map((inventario) => (
                            <li key={inventario.id}>
                                <Link
                                    href={inventarios.show(inventario.id)}
                                    className="flex items-center gap-3 rounded-xl border bg-card p-3 transition-colors hover:bg-accent/50"
                                >
                                    <div className="min-w-0 flex-1">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <span className="font-medium">
                                                {inventario.numero}
                                            </span>
                                            <span className="text-sm text-muted-foreground">
                                                {formatearFecha(
                                                    inventario.fecha,
                                                )}
                                            </span>
                                            <EstadoDocumento
                                                tono={TONO[inventario.estado]}
                                            >
                                                {inventario.estado_label}
                                            </EstadoDocumento>
                                        </div>
                                        <p className="truncate text-xs text-muted-foreground">
                                            {inventario.lineas}{' '}
                                            {inventario.lineas === 1
                                                ? 'artículo'
                                                : 'artículos'}
                                            {inventario.usuario &&
                                                ` · abrió ${inventario.usuario}`}
                                            {inventario.cerrado_at &&
                                                ` · cerrado ${formatearFechaHora(inventario.cerrado_at)}`}
                                            {inventario.observacion &&
                                                ` · ${inventario.observacion}`}
                                        </p>
                                    </div>
                                    <ChevronRight className="size-4 shrink-0 text-muted-foreground" />
                                </Link>
                            </li>
                        ))}
                    </ul>
                    <Paginacion paginador={paginador} />
                </>
            )}
        </div>
    );
}
