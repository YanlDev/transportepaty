import { Head, router, setLayoutProps, useForm } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import abastecimiento from '@/actions/App/Http/Controllers/AbastecimientoController';
import configuracion from '@/actions/App/Http/Controllers/ConfiguracionAbastecimientoController';
import { ReglaDialog } from '@/components/abastecimiento/regla-dialog';
import { Button } from '@/components/ui/button';
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
import { colorTarjeta } from '@/lib/color-tarjeta';
import { cn } from '@/lib/utils';
import type {
    OpcionesConfiguracion,
    PuntoAbastecimiento,
    ReglaAbastecimiento,
} from '@/types/abastecimiento';

type Props = {
    galonesTanqueLleno: string | null;
    puntos: PuntoAbastecimiento[];
    reglas: ReglaAbastecimiento[];
    opciones: OpcionesConfiguracion;
};

const SIN_ZONA = '__sin_zona';

/** Agregar o editar un punto de abastecimiento. */
function PuntoDialog({
    punto,
    zonas,
    onCerrar,
}: {
    punto: PuntoAbastecimiento | null;
    zonas: OpcionesConfiguracion['zonas'];
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

/** A qué trayecto se aplica la tarjeta, en una línea. */
function criterios(
    regla: ReglaAbastecimiento,
    opciones: OpcionesConfiguracion,
): string {
    const etiqueta = (
        lista: { value: string; label: string }[],
        valor: string | null,
    ) =>
        valor
            ? (lista.find((opcion) => opcion.value === valor)?.label ?? valor)
            : null;
    const desde = etiqueta(opciones.zonas, regla.zona_origen);
    const hasta = etiqueta(opciones.zonas, regla.zona_destino);
    const peso =
        regla.peso_desde !== null || regla.peso_hasta !== null
            ? `${regla.peso_desde !== null ? `más de ${regla.peso_desde} t` : ''}${regla.peso_desde !== null && regla.peso_hasta !== null ? ' y ' : ''}${regla.peso_hasta !== null ? `hasta ${regla.peso_hasta} t` : ''}`
            : null;

    return [
        etiqueta(opciones.sentidos, regla.sentido),
        desde || hasta
            ? `${desde ?? 'cualquiera'} → ${hasta ?? 'cualquiera'}`
            : null,
        etiqueta(opciones.cargas, regla.tipo_carga),
        regla.lugares ? `en ${regla.lugares}` : null,
        peso,
    ]
        .filter(Boolean)
        .join(' · ');
}

/**
 * La pizarra de abastecimiento: cuántos galones son un tanque lleno, los
 * puntos donde se carga y las tarjetas de dotación, con los mismos colores
 * que el papel de la oficina.
 */
export default function AbastecimientoConfiguracion({
    galonesTanqueLleno,
    puntos,
    reglas,
    opciones,
}: Props) {
    const [punto, setPunto] = useState<PuntoAbastecimiento | null | false>(
        false,
    );
    const [regla, setRegla] = useState<ReglaAbastecimiento | null | false>(
        false,
    );
    const tanque = useForm({ galones: galonesTanqueLleno ?? '' });

    setLayoutProps({
        breadcrumbs: [
            { title: 'Abastecimiento', href: abastecimiento.index().url },
            { title: 'Pizarra', href: configuracion.index().url },
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

    return (
        <div className="flex h-full flex-1 flex-col gap-8 p-4 md:p-6">
            <Head title="Pizarra de abastecimiento" />

            <div className="grid gap-6 lg:grid-cols-[minmax(0,20rem)_1fr]">
                <section className="flex flex-col gap-3 rounded-xl border bg-card p-4">
                    <h2 className="text-sm font-semibold">Tanque lleno</h2>
                    <p className="text-xs text-muted-foreground">
                        Cuántos galones son «full» en las tarjetas.
                    </p>
                    <form
                        className="flex gap-2"
                        onSubmit={(evento) => {
                            evento.preventDefault();
                            tanque.put(configuracion.actualizarTanque().url, {
                                preserveScroll: true,
                            });
                        }}
                    >
                        <Input
                            type="number"
                            min="1"
                            placeholder="Sin configurar"
                            value={tanque.data.galones}
                            aria-invalid={
                                Boolean(tanque.errors.galones) || undefined
                            }
                            onChange={(evento) =>
                                tanque.setData('galones', evento.target.value)
                            }
                        />
                        <Button type="submit" disabled={tanque.processing}>
                            Guardar
                        </Button>
                    </form>
                </section>

                <section className="flex flex-col gap-3 rounded-xl border bg-card p-4">
                    <div className="flex items-center justify-between">
                        <h2 className="text-sm font-semibold">
                            Puntos de abastecimiento
                        </h2>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => setPunto(null)}
                        >
                            <Plus className="size-4" />
                            Punto
                        </Button>
                    </div>
                    <ul className="flex flex-wrap gap-2">
                        {puntos.map((elegido) => (
                            <li
                                key={elegido.id}
                                className={cn(
                                    'flex items-center gap-1 rounded-md border py-1 pr-1 pl-3 text-sm',
                                    !elegido.activo && 'opacity-50',
                                )}
                                title={elegido.detalle ?? undefined}
                            >
                                <span>{elegido.nombre}</span>
                                {elegido.zona_label && (
                                    <span className="text-xs text-muted-foreground">
                                        · {elegido.zona_label}
                                    </span>
                                )}
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    className="size-7"
                                    aria-label={`Editar ${elegido.nombre}`}
                                    onClick={() => setPunto(elegido)}
                                >
                                    <Pencil className="size-3.5" />
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    className="size-7"
                                    aria-label={`Quitar ${elegido.nombre}`}
                                    onClick={() => quitarPunto(elegido)}
                                >
                                    <Trash2 className="size-3.5" />
                                </Button>
                            </li>
                        ))}
                    </ul>
                </section>
            </div>

            <section className="flex flex-col gap-4">
                <div className="flex items-center justify-between">
                    <h2 className="text-sm font-semibold">
                        Tarjetas de dotación
                    </h2>
                    <Button size="sm" onClick={() => setRegla(null)}>
                        <Plus className="size-4" />
                        Tarjeta
                    </Button>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    {reglas.map((tarjeta) => {
                        const color = colorTarjeta(tarjeta.color);

                        return (
                            <button
                                key={tarjeta.id}
                                type="button"
                                onClick={() => setRegla(tarjeta)}
                                className={cn(
                                    'flex flex-col overflow-hidden rounded-xl border-2 bg-card text-left transition hover:shadow-md',
                                    color.borde,
                                    !tarjeta.activa && 'opacity-50',
                                )}
                            >
                                <div
                                    className={cn(
                                        'px-3 py-2 text-sm font-semibold uppercase',
                                        color.cabecera,
                                    )}
                                >
                                    {tarjeta.nombre}
                                </div>
                                <div className="flex flex-1 flex-col gap-2 p-3">
                                    <p className="text-xs text-muted-foreground">
                                        {tarjeta.se_aplica_sola
                                            ? criterios(tarjeta, opciones)
                                            : 'Referencia: no se aplica sola.'}
                                    </p>
                                    <table className="w-full text-sm">
                                        <tbody>
                                            {tarjeta.lineas.map(
                                                (linea, indice) => (
                                                    <tr
                                                        key={indice}
                                                        className="border-b last:border-0"
                                                    >
                                                        <td className="py-1">
                                                            {linea.concepto}
                                                            {linea.punto &&
                                                                linea.punto !==
                                                                    linea.concepto && (
                                                                    <span className="text-xs text-muted-foreground">
                                                                        {' '}
                                                                        ·{' '}
                                                                        {
                                                                            linea.punto
                                                                        }
                                                                    </span>
                                                                )}
                                                        </td>
                                                        <td className="py-1 text-right font-medium tabular-nums">
                                                            {linea.tipo ===
                                                            'combustible'
                                                                ? linea.tanque_lleno
                                                                    ? 'Full'
                                                                    : `${linea.galones ?? '—'} gal`
                                                                : `S/ ${linea.monto?.toFixed(2) ?? '—'}`}
                                                        </td>
                                                    </tr>
                                                ),
                                            )}
                                        </tbody>
                                    </table>
                                    {tarjeta.nota && (
                                        <p className="text-xs text-muted-foreground italic">
                                            {tarjeta.nota}
                                        </p>
                                    )}
                                </div>
                            </button>
                        );
                    })}
                </div>
            </section>

            {punto !== false && (
                <PuntoDialog
                    key={punto?.id ?? 'nuevo'}
                    punto={punto}
                    zonas={opciones.zonas}
                    onCerrar={() => setPunto(false)}
                />
            )}

            {regla !== false && (
                <ReglaDialog
                    key={regla?.id ?? 'nueva'}
                    open
                    onOpenChange={(abierto) => !abierto && setRegla(false)}
                    regla={regla}
                    puntos={puntos.filter((elegido) => elegido.activo)}
                    opciones={opciones}
                />
            )}
        </div>
    );
}
