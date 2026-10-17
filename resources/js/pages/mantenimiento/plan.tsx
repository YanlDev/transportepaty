import { Head, Link, setLayoutProps, useForm } from '@inertiajs/react';
import { Plus, Trash2, TriangleAlert } from 'lucide-react';
import mantenimiento from '@/actions/App/Http/Controllers/MantenimientoController';
import { update } from '@/actions/App/Http/Controllers/PlanMantenimientoController';
import InputError from '@/components/input-error';
import { kmCorto, MarcaLogo } from '@/components/mantenimiento/marca-logo';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import type { EnumOption } from '@/types/fleet';
import type { PlanEditable } from '@/types/mantenimiento';

type Props = {
    plan: PlanEditable;
    /** X, S, M y L, en orden. */
    niveles: EnumOption[];
};

type RepuestoForm = {
    descripcion: string;
    codigo: string;
    cantidad: string;
    unidad: string;
};

type ServicioForm = {
    codigo: string;
    nombre: string;
    intervalo_km: string;
    intervalo_meses: string;
    ordenes: number;
    repuestos: RepuestoForm[];
};

type Formulario = {
    nombre: string;
    servicios: ServicioForm[];
};

const repuestoVacio: RepuestoForm = {
    descripcion: '',
    codigo: '',
    cantidad: '',
    unidad: '',
};

/**
 * El plan preventivo de una marca. Todas usan los mismos niveles X · S · M ·
 * L; acá se decide a cuántos km (y meses) va cada uno y qué lleva. Cada
 * nivel incluye a los anteriores, así que solo se anota lo que suma.
 */
export default function PlanMantenimiento({ plan, niveles }: Props) {
    const { data, setData, put, transform, processing, errors, isDirty } =
        useForm<Formulario>({
            nombre: plan.nombre,
            servicios: plan.servicios.map((servicio) => ({
                codigo: servicio.codigo,
                nombre: servicio.nombre,
                intervalo_km: String(servicio.intervalo_km),
                intervalo_meses:
                    servicio.intervalo_meses === null
                        ? ''
                        : String(servicio.intervalo_meses),
                ordenes: servicio.ordenes,
                repuestos: servicio.repuestos.map((repuesto) => ({
                    descripcion: repuesto.descripcion,
                    codigo: repuesto.codigo ?? '',
                    cantidad:
                        repuesto.cantidad === null
                            ? ''
                            : String(repuesto.cantidad),
                    unidad: repuesto.unidad ?? '',
                })),
            })),
        });

    setLayoutProps({
        breadcrumbs: [
            { title: 'Mantenimiento', href: mantenimiento.index().url },
            {
                title: 'Preventivo',
                href: mantenimiento.preventivo({
                    query: { marca: plan.marca },
                }).url,
            },
            { title: `Plan ${plan.marca}`, href: '#' },
        ],
    });

    const siguiente = niveles[data.servicios.length] ?? null;
    const ultimo = data.servicios[data.servicios.length - 1];

    const cambiarServicio = (
        indice: number,
        cambios: Partial<ServicioForm>,
    ) => {
        setData(
            'servicios',
            data.servicios.map((servicio, i) =>
                i === indice ? { ...servicio, ...cambios } : servicio,
            ),
        );
    };

    const cambiarRepuesto = (
        indice: number,
        fila: number,
        cambios: Partial<RepuestoForm>,
    ) => {
        cambiarServicio(indice, {
            repuestos: data.servicios[indice].repuestos.map((repuesto, i) =>
                i === fila ? { ...repuesto, ...cambios } : repuesto,
            ),
        });
    };

    const agregarNivel = () => {
        if (siguiente === null) {
            return;
        }

        const anterior = Number(ultimo?.intervalo_km) || 0;

        setData('servicios', [
            ...data.servicios,
            {
                codigo: siguiente.value,
                nombre: siguiente.label,
                // Lo habitual: el doble del nivel anterior.
                intervalo_km: anterior > 0 ? String(anterior * 2) : '',
                intervalo_meses: '',
                ordenes: 0,
                repuestos: [{ ...repuestoVacio }],
            },
        ]);
    };

    const quitarUltimoNivel = () => {
        setData('servicios', data.servicios.slice(0, -1));
    };

    const guardar = (evento: React.FormEvent) => {
        evento.preventDefault();

        transform((datos) => ({
            nombre: datos.nombre,
            servicios: datos.servicios.map((servicio) => ({
                codigo: servicio.codigo,
                nombre: servicio.nombre,
                intervalo_km: Number(servicio.intervalo_km) || null,
                intervalo_meses: Number(servicio.intervalo_meses) || null,
                repuestos: servicio.repuestos
                    .filter(
                        (repuesto) =>
                            repuesto.descripcion.trim() !== '' ||
                            repuesto.codigo.trim() !== '' ||
                            repuesto.cantidad.trim() !== '',
                    )
                    .map((repuesto) => ({
                        descripcion: repuesto.descripcion,
                        codigo: repuesto.codigo || null,
                        cantidad:
                            repuesto.cantidad === ''
                                ? null
                                : Number(repuesto.cantidad.replace(',', '.')),
                        unidad: repuesto.unidad || null,
                    })),
            })),
        }));

        put(update(plan.id).url, { preserveScroll: true });
    };

    return (
        <div className="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6 p-4 md:p-6">
            <Head title={`Plan ${plan.marca}`} />

            <div className="flex items-center gap-4">
                <MarcaLogo
                    marca={plan.marca}
                    logoUrl={plan.logo_url}
                    className="h-16 w-20"
                />
                <div className="flex flex-col gap-0.5">
                    <h1 className="text-xl font-semibold">
                        Plan preventivo {plan.marca}
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {plan.unidades}{' '}
                        {plan.unidades === 1
                            ? 'tracto lo sigue'
                            : 'tractos lo siguen'}
                        . Al guardar, el semáforo de todos se recalcula con
                        estos km.
                    </p>
                </div>
            </div>

            <form onSubmit={guardar} className="flex flex-col gap-6">
                <div className="grid gap-1.5">
                    <Label htmlFor="plan-nombre">Nombre del plan</Label>
                    <Input
                        id="plan-nombre"
                        value={data.nombre}
                        onChange={(e) => setData('nombre', e.target.value)}
                    />
                    <InputError message={errors.nombre} />
                </div>

                <Calendario servicios={data.servicios} />

                <InputError message={errors.servicios} />

                {data.servicios.map((servicio, indice) => (
                    <section
                        key={servicio.codigo}
                        className="flex flex-col gap-4 rounded-xl border bg-card p-4"
                    >
                        <div className="flex items-start gap-3">
                            <span className="flex size-11 shrink-0 items-center justify-center rounded-lg bg-primary text-xl font-bold text-primary-foreground">
                                {servicio.codigo}
                            </span>
                            <div className="flex min-w-0 flex-1 flex-col gap-0.5">
                                <Input
                                    aria-label={`Nombre del servicio ${servicio.codigo}`}
                                    value={servicio.nombre}
                                    onChange={(e) =>
                                        cambiarServicio(indice, {
                                            nombre: e.target.value,
                                        })
                                    }
                                    className="h-9 font-medium"
                                />
                                <InputError
                                    message={
                                        errors[`servicios.${indice}.nombre`]
                                    }
                                />
                                <span className="text-xs text-muted-foreground">
                                    {indice === 0
                                        ? 'El servicio base.'
                                        : `Incluye todo lo de ${data.servicios
                                              .slice(0, indice)
                                              .map((s) => s.codigo)
                                              .join(', ')}, más lo de abajo.`}
                                    {servicio.ordenes > 0 &&
                                        ` · ${servicio.ordenes} ${servicio.ordenes === 1 ? 'orden registrada' : 'órdenes registradas'}`}
                                </span>
                            </div>
                            {indice === data.servicios.length - 1 &&
                                indice > 0 && (
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        onClick={quitarUltimoNivel}
                                        disabled={servicio.ordenes > 0}
                                        title={
                                            servicio.ordenes > 0
                                                ? 'Tiene órdenes registradas: no se puede quitar'
                                                : `Quitar la ${servicio.codigo}`
                                        }
                                        aria-label={`Quitar la ${servicio.codigo}`}
                                    >
                                        <Trash2 className="size-4" />
                                    </Button>
                                )}
                        </div>

                        <div className="grid grid-cols-2 gap-3 sm:max-w-md">
                            <div className="grid gap-1.5">
                                <Label htmlFor={`km-${servicio.codigo}`}>
                                    Cada (km)
                                </Label>
                                <Input
                                    id={`km-${servicio.codigo}`}
                                    type="number"
                                    inputMode="numeric"
                                    min={1000}
                                    step={1000}
                                    value={servicio.intervalo_km}
                                    onChange={(e) =>
                                        cambiarServicio(indice, {
                                            intervalo_km: e.target.value,
                                        })
                                    }
                                    className="tabular-nums"
                                />
                                <InputError
                                    message={
                                        errors[
                                            `servicios.${indice}.intervalo_km`
                                        ]
                                    }
                                />
                            </div>
                            <div className="grid gap-1.5">
                                <Label htmlFor={`meses-${servicio.codigo}`}>
                                    O cada (meses)
                                </Label>
                                <Input
                                    id={`meses-${servicio.codigo}`}
                                    type="number"
                                    inputMode="numeric"
                                    min={1}
                                    placeholder="Solo por km"
                                    value={servicio.intervalo_meses}
                                    onChange={(e) =>
                                        cambiarServicio(indice, {
                                            intervalo_meses: e.target.value,
                                        })
                                    }
                                    className="tabular-nums"
                                />
                                <InputError
                                    message={
                                        errors[
                                            `servicios.${indice}.intervalo_meses`
                                        ]
                                    }
                                />
                            </div>
                        </div>

                        <div className="flex flex-col gap-2">
                            <span className="text-sm font-medium">
                                Qué lleva la {servicio.codigo}
                            </span>
                            <div className="hidden grid-cols-[1fr_8rem_5rem_5rem_2.25rem] gap-2 px-0.5 text-xs text-muted-foreground sm:grid">
                                <span>Descripción</span>
                                <span>Código</span>
                                <span>Cantidad</span>
                                <span>Unidad</span>
                            </div>
                            {servicio.repuestos.map((repuesto, fila) => (
                                <div
                                    key={fila}
                                    className="grid grid-cols-[1fr_5rem_4.5rem_2.25rem] gap-2 border-b pb-2 last:border-b-0 sm:grid-cols-[1fr_8rem_5rem_5rem_2.25rem] sm:border-b-0 sm:pb-0"
                                >
                                    <Input
                                        aria-label="Descripción"
                                        placeholder="Filtro de aceite…"
                                        value={repuesto.descripcion}
                                        onChange={(e) =>
                                            cambiarRepuesto(indice, fila, {
                                                descripcion: e.target.value,
                                            })
                                        }
                                        className="col-span-4 sm:col-span-1"
                                    />
                                    <Input
                                        aria-label="Código"
                                        placeholder="Código"
                                        value={repuesto.codigo}
                                        onChange={(e) =>
                                            cambiarRepuesto(indice, fila, {
                                                codigo: e.target.value,
                                            })
                                        }
                                        className="max-sm:col-span-1"
                                    />
                                    <Input
                                        aria-label="Cantidad"
                                        placeholder="Cant."
                                        inputMode="decimal"
                                        value={repuesto.cantidad}
                                        onChange={(e) =>
                                            cambiarRepuesto(indice, fila, {
                                                cantidad: e.target.value,
                                            })
                                        }
                                        className="tabular-nums"
                                    />
                                    <Input
                                        aria-label="Unidad"
                                        placeholder="und"
                                        value={repuesto.unidad}
                                        onChange={(e) =>
                                            cambiarRepuesto(indice, fila, {
                                                unidad: e.target.value,
                                            })
                                        }
                                    />
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        aria-label="Quitar el ítem"
                                        onClick={() =>
                                            cambiarServicio(indice, {
                                                repuestos:
                                                    servicio.repuestos.filter(
                                                        (_, i) => i !== fila,
                                                    ),
                                            })
                                        }
                                    >
                                        <Trash2 className="size-4" />
                                    </Button>
                                    <InputError
                                        className="col-span-full"
                                        message={
                                            errors[
                                                `servicios.${indice}.repuestos.${fila}.descripcion`
                                            ] ??
                                            errors[
                                                `servicios.${indice}.repuestos.${fila}.cantidad`
                                            ]
                                        }
                                    />
                                </div>
                            ))}
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                className="self-start"
                                onClick={() =>
                                    cambiarServicio(indice, {
                                        repuestos: [
                                            ...servicio.repuestos,
                                            { ...repuestoVacio },
                                        ],
                                    })
                                }
                            >
                                <Plus className="size-4" />
                                Agregar ítem
                            </Button>
                        </div>
                    </section>
                ))}

                {siguiente && (
                    <Button
                        type="button"
                        variant="outline"
                        className="h-12 border-dashed"
                        onClick={agregarNivel}
                    >
                        <Plus className="size-4" />
                        Agregar la {siguiente.value}
                    </Button>
                )}

                <div className="sticky bottom-0 -mx-4 flex justify-end gap-2 border-t bg-background/95 px-4 py-3 backdrop-blur max-md:pb-[calc(env(safe-area-inset-bottom)+5rem)] md:-mx-6 md:px-6">
                    <Button variant="ghost" asChild>
                        <Link
                            href={mantenimiento.preventivo({
                                query: { marca: plan.marca },
                            })}
                        >
                            Cancelar
                        </Link>
                    </Button>
                    <Button type="submit" disabled={processing || !isDirty}>
                        {processing && <Spinner />}
                        Guardar plan
                    </Button>
                </div>
            </form>
        </div>
    );
}

/**
 * Qué le toca a una unidad en sus primeras entradas a taller, con los km de
 * arriba: es la forma más rápida de ver si el plan quedó como se pensaba.
 * Si un nivel no es múltiplo del anterior, el calendario se corre, y se avisa.
 */
function Calendario({ servicios }: { servicios: ServicioForm[] }) {
    const intervalos = servicios.map((s) => Number(s.intervalo_km) || 0);

    if (intervalos.length === 0 || intervalos.some((km) => km <= 0)) {
        return null;
    }

    const base = intervalos[0];
    const pasos = Math.min(
        16,
        Math.max(4, Math.round(intervalos[intervalos.length - 1] / base)),
    );

    const entradas = Array.from({ length: pasos }, (_, i) => {
        const km = base * (i + 1);
        let nivel = 0;

        intervalos.forEach((intervalo, indice) => {
            if (km % intervalo === 0) {
                nivel = indice;
            }
        });

        return { km, codigo: servicios[nivel].codigo };
    });

    const descuadrados = servicios.filter(
        (_, i) => i > 0 && intervalos[i] % intervalos[i - 1] !== 0,
    );

    return (
        <div className="flex flex-col gap-2 rounded-xl border bg-muted/40 p-3">
            <span className="text-sm font-medium">
                Cómo queda el calendario
            </span>
            <div className="flex flex-wrap gap-1.5">
                {entradas.map((entrada) => (
                    <span
                        key={entrada.km}
                        className="flex flex-col items-center rounded-md border bg-card px-2 py-1"
                    >
                        <span className="text-sm font-semibold">
                            {entrada.codigo}
                        </span>
                        <span className="text-[11px] text-muted-foreground tabular-nums">
                            {kmCorto(entrada.km)}
                        </span>
                    </span>
                ))}
            </div>
            {descuadrados.length > 0 && (
                <p className="flex items-start gap-1.5 text-xs text-amber-700 dark:text-amber-400">
                    <TriangleAlert className="mt-0.5 size-3.5 shrink-0" />
                    {descuadrados.map((s) => s.codigo).join(', ')} no{' '}
                    {descuadrados.length === 1
                        ? 'es múltiplo'
                        : 'son múltiplos'}{' '}
                    del nivel anterior: no va a caer junto con él y la unidad
                    entraría a taller más seguido.
                </p>
            )}
        </div>
    );
}
