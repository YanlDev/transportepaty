import { Head, Link, setLayoutProps, useForm } from '@inertiajs/react';
import { Check, Minus, Plus, StickyNote } from 'lucide-react';
import { useState } from 'react';
import equipamiento, {
    store,
} from '@/actions/App/Http/Controllers/EquipamientoCarretaController';
import mantenimiento from '@/actions/App/Http/Controllers/MantenimientoController';
import { CampoArchivo } from '@/components/campo-archivo';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { formatearPlaca } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { EquipamientoCarreta, ItemCarreta } from '@/types/equipamiento';

type Props = {
    carreta: EquipamientoCarreta;
    maximoFotos: number;
    hoy: string;
};

type Fila = {
    id: number;
    buenas: number;
    danadas: number;
    nota: string;
    vence_el: string;
    fotos: File[];
    /** Ya se revisó: sin esto no se puede guardar. */
    revisado: boolean;
    /** El campo de la nota está a la vista. */
    conNota: boolean;
};

type Formulario = { fecha: string; observacion: string; items: Fila[] };

type Marca = 'esta' | 'falta' | 'danado';

const MARCAS: { clave: Marca; texto: string; activo: string }[] = [
    {
        clave: 'esta',
        texto: 'Está',
        activo: 'border-emerald-600 bg-emerald-600 text-white hover:bg-emerald-600 hover:text-white',
    },
    {
        clave: 'falta',
        texto: 'Falta',
        activo: 'border-red-600 bg-red-600 text-white hover:bg-red-600 hover:text-white',
    },
    {
        clave: 'danado',
        texto: 'Dañado',
        activo: 'border-amber-500 bg-amber-500 text-white hover:bg-amber-500 hover:text-white',
    },
];

/** Lo que es uno solo se marca con un toque; lo que son varios, se cuenta. */
const esDeUno = (item: ItemCarreta) => item.minimo === 1 && item.asignado <= 1;

const marcaDe = (fila: Fila): Marca | null =>
    !fila.revisado
        ? null
        : fila.danadas > 0
          ? 'danado'
          : fila.buenas > 0
            ? 'esta'
            : 'falta';

/**
 * El levantamiento de una unidad, desde el celular: cada cosa que debe
 * llevar se marca (está, falta o dañada) y se le toma su foto. No toca el
 * almacén: son cosas que la unidad ya tiene.
 */
export default function LevantamientoEquipamiento({
    carreta,
    maximoFotos,
    hoy,
}: Props) {
    const placa = formatearPlaca(carreta.placa);
    const [preparando, setPreparando] = useState<number[]>([]);

    const { data, setData, post, transform, processing, progress, errors } =
        useForm<Formulario>({
            fecha: hoy,
            observacion: '',
            items: carreta.items.map((item) => ({
                id: item.id,
                buenas: item.buenas ?? 0,
                danadas: item.danadas ?? 0,
                nota: '',
                vence_el: item.vence_el ?? '',
                fotos: [],
                revisado: false,
                conNota: false,
            })),
        });

    setLayoutProps({
        breadcrumbs: [
            { title: 'Mantenimiento', href: mantenimiento.index().url },
            { title: 'Equipamiento', href: equipamiento.index().url },
            { title: placa, href: equipamiento.show(carreta.id).url },
            {
                title: 'Levantamiento',
                href: equipamiento.levantamiento(carreta.id).url,
            },
        ],
    });

    const cambiar = (indice: number, cambios: Partial<Fila>) =>
        setData(
            'items',
            data.items.map((fila, i) =>
                i === indice ? { ...fila, ...cambios } : fila,
            ),
        );

    const marcar = (indice: number, marca: Marca) =>
        cambiar(indice, {
            revisado: true,
            buenas: marca === 'esta' ? 1 : 0,
            danadas: marca === 'danado' ? 1 : 0,
            conNota: data.items[indice].conNota || marca !== 'esta',
            ...(marca === 'falta' && { fotos: [] }),
        });

    const pendientes = data.items.filter((fila) => !fila.revisado).length;
    const errorDe = (indice: number): string | undefined =>
        Object.entries(errors).find(([clave]) =>
            clave.startsWith(`items.${indice}.`),
        )?.[1];

    const guardar = (evento: React.FormEvent) => {
        evento.preventDefault();

        transform((datos) => ({
            fecha: datos.fecha,
            observacion: datos.observacion,
            items: datos.items.map((fila) => ({
                id: fila.id,
                buenas: fila.buenas,
                danadas: fila.danadas,
                nota: fila.nota,
                vence_el: fila.vence_el,
                fotos: fila.fotos,
            })),
        }));

        post(store(carreta.id).url, { preserveScroll: true });
    };

    return (
        <form
            onSubmit={guardar}
            className="mx-auto flex w-full max-w-2xl flex-1 flex-col gap-4 p-4 md:p-6"
        >
            <Head title={`Levantamiento ${placa}`} />

            <div className="flex flex-col gap-1">
                <h1 className="text-xl font-semibold tracking-wide">
                    Levantamiento · {placa}
                </h1>
                <p className="text-sm text-muted-foreground">
                    Marca cada cosa y tómale su foto. No toca el almacén: es lo
                    que la unidad ya tiene.
                </p>
            </div>

            <InputError
                message={(errors as Record<string, string | undefined>).items}
            />

            <ul className="flex flex-col gap-3">
                {carreta.items.map((item, indice) => {
                    const fila = data.items[indice];
                    const marca = marcaDe(fila);
                    const deUno = esDeUno(item);
                    const hayAlgo = fila.buenas + fila.danadas > 0;

                    return (
                        <li
                            key={item.id}
                            className={cn(
                                'flex flex-col gap-3 rounded-xl border border-l-4 bg-card p-3',
                                marca === null && 'border-l-zinc-300',
                                marca === 'esta' && 'border-l-emerald-500',
                                marca === 'falta' && 'border-l-red-500',
                                marca === 'danado' && 'border-l-amber-500',
                            )}
                        >
                            <div className="flex items-baseline justify-between gap-2">
                                <span className="font-medium">
                                    {item.nombre}
                                </span>
                                <span className="shrink-0 text-xs text-muted-foreground">
                                    {item.minimo !== null
                                        ? `Debe llevar ${item.minimo}`
                                        : 'Sin cantidad fija'}
                                </span>
                            </div>

                            {deUno ? (
                                <div className="grid grid-cols-3 gap-2">
                                    {MARCAS.map((opcion) => (
                                        <Button
                                            key={opcion.clave}
                                            type="button"
                                            variant="outline"
                                            className={cn(
                                                'h-11',
                                                marca === opcion.clave &&
                                                    opcion.activo,
                                            )}
                                            aria-pressed={
                                                marca === opcion.clave
                                            }
                                            aria-label={`${item.nombre}: ${opcion.texto}`}
                                            onClick={() =>
                                                marcar(indice, opcion.clave)
                                            }
                                        >
                                            {opcion.texto}
                                        </Button>
                                    ))}
                                </div>
                            ) : (
                                <div className="grid gap-2">
                                    <div className="grid grid-cols-2 gap-3">
                                        <Contador
                                            id={`buenas-${item.id}`}
                                            etiqueta="Buenas"
                                            item={item.nombre}
                                            valor={fila.buenas}
                                            onCambio={(valor) =>
                                                cambiar(indice, {
                                                    buenas: valor,
                                                    revisado: true,
                                                })
                                            }
                                        />
                                        <Contador
                                            id={`danadas-${item.id}`}
                                            etiqueta="Dañadas"
                                            item={item.nombre}
                                            valor={fila.danadas}
                                            onCambio={(valor) =>
                                                cambiar(indice, {
                                                    danadas: valor,
                                                    revisado: true,
                                                })
                                            }
                                        />
                                    </div>
                                    {!fila.revisado && (
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            className="self-start"
                                            onClick={() =>
                                                cambiar(indice, {
                                                    revisado: true,
                                                })
                                            }
                                        >
                                            <Check className="size-4" />
                                            {hayAlgo
                                                ? 'Está igual que la última vez'
                                                : 'No tiene ninguna'}
                                        </Button>
                                    )}
                                </div>
                            )}

                            {fila.revisado && hayAlgo && (
                                <CampoArchivo
                                    acepta="imagen"
                                    multiple={fila.fotos.length < maximoFotos}
                                    archivos={fila.fotos}
                                    onArchivos={(fotos) =>
                                        cambiar(indice, {
                                            fotos: fotos.slice(0, maximoFotos),
                                        })
                                    }
                                    deshabilitado={processing}
                                    onPreparando={(activo) =>
                                        setPreparando((lista) =>
                                            activo
                                                ? [...lista, item.id]
                                                : lista.filter(
                                                      (id) => id !== item.id,
                                                  ),
                                        )
                                    }
                                />
                            )}

                            {item.lleva_vencimiento && hayAlgo && (
                                <div className="grid gap-1.5">
                                    <Label htmlFor={`vence-${item.id}`}>
                                        Vence el
                                    </Label>
                                    <Input
                                        id={`vence-${item.id}`}
                                        type="date"
                                        value={fila.vence_el}
                                        onChange={(e) =>
                                            cambiar(indice, {
                                                vence_el: e.target.value,
                                            })
                                        }
                                        className="w-44"
                                    />
                                </div>
                            )}

                            {fila.conNota ? (
                                <Input
                                    aria-label={`Nota de ${item.nombre}`}
                                    placeholder="Nota: qué le pasa, qué le falta…"
                                    maxLength={300}
                                    value={fila.nota}
                                    onChange={(e) =>
                                        cambiar(indice, {
                                            nota: e.target.value,
                                        })
                                    }
                                />
                            ) : (
                                fila.revisado && (
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        className="self-start text-muted-foreground"
                                        onClick={() =>
                                            cambiar(indice, { conNota: true })
                                        }
                                    >
                                        <StickyNote className="size-4" />
                                        Agregar nota
                                    </Button>
                                )
                            )}

                            <InputError message={errorDe(indice)} />
                        </li>
                    );
                })}
            </ul>

            <div className="grid gap-1.5">
                <Label htmlFor="levantamiento-fecha">Fecha</Label>
                <Input
                    id="levantamiento-fecha"
                    type="date"
                    max={hoy}
                    value={data.fecha}
                    onChange={(e) => setData('fecha', e.target.value)}
                    className="w-44"
                />
                <InputError message={errors.fecha} />
            </div>

            <div className="grid gap-1.5">
                <Label htmlFor="levantamiento-observacion">
                    Observación general (opcional)
                </Label>
                <Textarea
                    id="levantamiento-observacion"
                    rows={2}
                    value={data.observacion}
                    onChange={(e) => setData('observacion', e.target.value)}
                />
                <InputError message={errors.observacion} />
            </div>

            {progress && (
                <p className="text-sm text-muted-foreground" aria-live="polite">
                    Subiendo fotos… {Math.round(progress.percentage ?? 0)} %
                </p>
            )}

            <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-end">
                {pendientes > 0 && (
                    <span className="text-sm text-amber-700 sm:mr-auto dark:text-amber-400">
                        {pendientes === 1
                            ? 'Falta revisar 1 cosa'
                            : `Faltan revisar ${pendientes} cosas`}
                    </span>
                )}
                <Button variant="ghost" asChild>
                    <Link href={equipamiento.show(carreta.id)}>Cancelar</Link>
                </Button>
                <Button
                    type="submit"
                    className="h-11"
                    disabled={
                        processing || pendientes > 0 || preparando.length > 0
                    }
                >
                    {processing && <Spinner />}
                    Guardar levantamiento
                </Button>
            </div>
        </form>
    );
}

function Contador({
    id,
    etiqueta,
    item,
    valor,
    onCambio,
}: {
    id: string;
    etiqueta: string;
    /** Para que cada botón diga de qué ítem es. */
    item: string;
    valor: number;
    onCambio: (valor: number) => void;
}) {
    return (
        <div className="grid gap-1">
            <Label htmlFor={id} className="text-xs text-muted-foreground">
                {etiqueta}
            </Label>
            <div className="flex items-center gap-1">
                <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    className="size-10 shrink-0"
                    onClick={() => onCambio(Math.max(0, valor - 1))}
                    aria-label={`${etiqueta} de ${item}: una menos`}
                >
                    <Minus className="size-4" />
                </Button>
                <Input
                    id={id}
                    inputMode="numeric"
                    value={String(valor)}
                    onChange={(e) =>
                        onCambio(Number(e.target.value.replace(/\D/g, '')) || 0)
                    }
                    className="h-10 text-center text-base tabular-nums"
                />
                <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    className="size-10 shrink-0"
                    onClick={() => onCambio(valor + 1)}
                    aria-label={`${etiqueta} de ${item}: una más`}
                >
                    <Plus className="size-4" />
                </Button>
            </div>
        </div>
    );
}
