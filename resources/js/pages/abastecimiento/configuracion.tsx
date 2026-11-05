import { Head, Link, router, setLayoutProps, useForm } from '@inertiajs/react';
import { Fuel, Plus, Route, Settings, Trash2 } from 'lucide-react';
import { useState } from 'react';
import configuracion from '@/actions/App/Http/Controllers/ConfiguracionAbastecimientoController';
import hub from '@/actions/App/Http/Controllers/ConfiguracionController';
import rutas from '@/actions/App/Http/Controllers/RutaController';
import { EncabezadoPantalla } from '@/components/encabezado-pantalla';
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
import { cn } from '@/lib/utils';
import type { PuntoAbastecimiento } from '@/types/abastecimiento';
import type { Opcion } from '@/types/vueltas';

type Props = {
    puntos: PuntoAbastecimiento[];
    zonas: Opcion[];
};

const SIN_ZONA = '__sin_zona';

/** Agregar o editar un punto de abastecimiento. */
function PuntoDialog({
    punto,
    zonas,
    onCerrar,
}: {
    punto: PuntoAbastecimiento | null;
    zonas: Opcion[];
    onCerrar: () => void;
}) {
    const { data, setData, post, put, processing, errors } = useForm({
        nombre: punto?.nombre ?? '',
        zona: punto?.zona ?? '',
        detalle: punto?.detalle ?? '',
        activo: punto?.activo ?? true,
    });

    const enviar = (evento: React.FormEvent) => {
        evento.preventDefault();
        const opciones = { preserveScroll: true, onSuccess: onCerrar };

        if (punto) {
            put(configuracion.updatePunto(punto.id).url, opciones);

            return;
        }

        post(configuracion.storePunto().url, opciones);
    };

    return (
        <Dialog open onOpenChange={(abierto) => !abierto && onCerrar()}>
            <DialogContent className="md:max-w-sm">
                <DialogHeader>
                    <DialogTitle>
                        {punto ? 'Editar punto' : 'Nuevo punto'}
                    </DialogTitle>
                </DialogHeader>
                <form
                    id="punto-abastecimiento"
                    onSubmit={enviar}
                    className="flex flex-col gap-4"
                >
                    <Field label="Nombre" error={errors.nombre} required>
                        {(id) => (
                            <Input
                                id={id}
                                value={data.nombre}
                                onChange={(evento) =>
                                    setData('nombre', evento.target.value)
                                }
                            />
                        )}
                    </Field>
                    <Field label="Zona" error={errors.zona}>
                        {() => (
                            <Select
                                value={data.zona || SIN_ZONA}
                                onValueChange={(valor) =>
                                    setData(
                                        'zona',
                                        valor === SIN_ZONA ? '' : valor,
                                    )
                                }
                            >
                                <SelectTrigger className="w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={SIN_ZONA}>
                                        Punto de paso
                                    </SelectItem>
                                    {zonas.map((zona) => (
                                        <SelectItem
                                            key={zona.value}
                                            value={zona.value}
                                        >
                                            {zona.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        )}
                    </Field>
                    <Field label="Detalle" error={errors.detalle}>
                        {(id) => (
                            <Input
                                id={id}
                                placeholder="Grifo Repsol, cochera…"
                                value={data.detalle}
                                onChange={(evento) =>
                                    setData('detalle', evento.target.value)
                                }
                            />
                        )}
                    </Field>
                    {/* Un punto que ya se usó no se borra, se desactiva:
                        acá se vuelve a activar. */}
                    <label className="flex items-center gap-2 text-sm">
                        <Checkbox
                            checked={data.activo}
                            onCheckedChange={(marcado) =>
                                setData('activo', marcado === true)
                            }
                        />
                        Activo (se ofrece al registrar y en las rutas)
                    </label>
                </form>
                <DialogFooter>
                    <Button
                        type="submit"
                        form="punto-abastecimiento"
                        disabled={processing}
                    >
                        Guardar
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function BotonEditar({
    etiqueta,
    onClick,
}: {
    etiqueta: string;
    onClick: () => void;
}) {
    return (
        <Button
            variant="ghost"
            size="icon"
            className="size-8 shrink-0"
            aria-label={etiqueta}
            title={etiqueta}
            onClick={onClick}
        >
            <Settings className="size-4" />
        </Button>
    );
}

/** El color de la línea según el sentido: el mismo de todo el sistema. */
/**
 * Los puntos donde se carga combustible, con el último precio y lo cargado
 * en el mes. La dotación de cada ruta está en el catálogo de rutas.
 */
export default function AbastecimientoConfiguracion({ puntos, zonas }: Props) {
    const [punto, setPunto] = useState<PuntoAbastecimiento | null | false>(
        false,
    );

    setLayoutProps({
        breadcrumbs: [
            { title: 'Configuración', href: hub.index().url },
            {
                title: 'Puntos de carga',
                href: configuracion.index().url,
            },
        ],
    });

    const quitarPunto = (elegido: PuntoAbastecimiento) => {
        if (!window.confirm(`¿Quitar ${elegido.nombre}?`)) {
            return;
        }

        router.delete(configuracion.destroyPunto(elegido.id).url, {
            preserveScroll: true,
        });
    };

    const alternarPunto = (elegido: PuntoAbastecimiento) =>
        router.put(
            configuracion.updatePunto(elegido.id).url,
            {
                nombre: elegido.nombre,
                zona: elegido.zona ?? '',
                detalle: elegido.detalle ?? '',
                activo: !elegido.activo,
            },
            { preserveScroll: true },
        );

    return (
        <div className="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-5 p-4 md:p-6">
            <Head title="Puntos de carga" />

            <EncabezadoPantalla
                titulo="Puntos de carga"
                descripcion="Los grifos y puntos donde se carga combustible. Lo que le toca a cada ruta está en el catálogo de rutas."
                icono={Fuel}
                acciones={
                    <div className="flex gap-2">
                        <Button variant="outline" asChild>
                            <Link href={rutas.index().url}>
                                <Route className="size-4" />
                                Rutas y dotaciones
                            </Link>
                        </Button>
                        <Button onClick={() => setPunto(null)}>
                            <Plus className="size-4" />
                            Punto
                        </Button>
                    </div>
                }
            />

            <section className="overflow-x-auto rounded-xl border bg-card">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="border-b text-left text-xs text-muted-foreground">
                            <th className="px-4 py-2 font-normal">Punto</th>
                            <th className="px-4 py-2 font-normal">Zona</th>
                            <th className="px-4 py-2 text-right font-normal">
                                Último S/ gal
                            </th>
                            <th className="px-4 py-2 text-right font-normal">
                                Cargado este mes
                            </th>
                            <th className="px-4 py-2 font-normal">Estado</th>
                            <th className="w-0 px-4 py-2" />
                        </tr>
                    </thead>
                    <tbody className="divide-y">
                        {puntos.map((elegido) => (
                            <tr
                                key={elegido.id}
                                className={cn(
                                    !elegido.activo && 'text-muted-foreground',
                                )}
                            >
                                <td className="px-4 py-2.5">
                                    <p className="font-medium">
                                        {elegido.nombre}
                                    </p>
                                    {elegido.detalle && (
                                        <p className="text-xs text-muted-foreground">
                                            {elegido.detalle}
                                        </p>
                                    )}
                                </td>
                                <td className="px-4 py-2.5">
                                    {elegido.zona_label ?? 'De paso'}
                                </td>
                                <td className="px-4 py-2.5 text-right tabular-nums">
                                    {elegido.ultimo_precio != null
                                        ? elegido.ultimo_precio.toFixed(3)
                                        : '—'}
                                </td>
                                <td className="px-4 py-2.5 text-right tabular-nums">
                                    {elegido.galones_mes
                                        ? `${elegido.galones_mes} gal`
                                        : '—'}
                                </td>
                                <td className="px-4 py-2.5">
                                    <button
                                        type="button"
                                        onClick={() => alternarPunto(elegido)}
                                        title={
                                            elegido.activo
                                                ? 'Desactivar: deja de ofrecerse al registrar'
                                                : 'Activar: se vuelve a ofrecer al registrar'
                                        }
                                        className={cn(
                                            'inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs transition',
                                            elegido.activo
                                                ? 'border-emerald-300 bg-emerald-50 text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200'
                                                : 'hover:bg-accent/50',
                                        )}
                                    >
                                        <span
                                            className={cn(
                                                'size-2 rounded-full',
                                                elegido.activo
                                                    ? 'bg-emerald-500'
                                                    : 'bg-muted-foreground/40',
                                            )}
                                        />
                                        {elegido.activo ? 'Activo' : 'Inactivo'}
                                    </button>
                                </td>
                                <td className="px-4 py-2.5">
                                    <div className="flex justify-end gap-1">
                                        <BotonEditar
                                            etiqueta={`Editar ${elegido.nombre}`}
                                            onClick={() => setPunto(elegido)}
                                        />
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            className="size-8"
                                            aria-label={`Quitar ${elegido.nombre}`}
                                            onClick={() => quitarPunto(elegido)}
                                        >
                                            <Trash2 className="size-4" />
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </section>

            {punto !== false && (
                <PuntoDialog
                    key={punto?.id ?? 'nuevo'}
                    punto={punto}
                    zonas={zonas}
                    onCerrar={() => setPunto(false)}
                />
            )}
        </div>
    );
}
