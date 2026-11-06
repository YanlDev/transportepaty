import { router, useForm } from '@inertiajs/react';
import { Plus, X } from 'lucide-react';
import rutas from '@/actions/App/Http/Controllers/RutaController';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
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
import { Textarea } from '@/components/ui/textarea';
import { COLORES_TARJETA } from '@/lib/color-tarjeta';
import type {
    LineaRuta,
    OpcionesRutas,
    ParadaRutaCatalogo,
    RutaCatalogo,
    VarianteRutaCatalogo,
} from '@/types/abastecimiento';
import type { Opcion } from '@/types/vueltas';

/** El valor de los selectores para «ninguno»: Radix no acepta vacío. */
const NINGUNO = '__ninguno';

type FormData = {
    nombre: string;
    descripcion: string;
    sentido: '' | 'ida' | 'regreso';
    es_complemento: boolean;
    dias_viaje: string;
    km_estandar: string;
    color: string;
    nota: string;
    activa: boolean;
    paradas: ParadaRutaCatalogo[];
    variantes: VarianteRutaCatalogo[];
    lineas: LineaRuta[];
};

function Elegir({
    valor,
    opciones,
    placeholder,
    invalido,
    onCambiar,
}: {
    valor: string;
    opciones: Opcion[];
    placeholder?: string;
    invalido?: boolean;
    onCambiar: (valor: string) => void;
}) {
    return (
        <Select value={valor || undefined} onValueChange={onCambiar}>
            <SelectTrigger
                className="w-full min-w-0"
                aria-invalid={invalido || undefined}
            >
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

const numeroONulo = (valor: string): number | null =>
    valor === '' ? null : Number(valor);

/**
 * Agregar o editar una ruta del catálogo: el recorrido (sus paradas), la
 * dotación (combustible por punto, viáticos y peajes) y, si lo que se da
 * depende del peso, sus variantes. Una línea sin variante vale para toda la
 * ruta. Los tickets que ya la tienen no cambian al guardar.
 */
export function RutaDialog({
    ruta,
    opciones,
    onCerrar,
}: {
    ruta: RutaCatalogo | null;
    opciones: OpcionesRutas;
    onCerrar: () => void;
}) {
    const { data, setData, post, put, processing, errors, setError } =
        useForm<FormData>({
            nombre: ruta?.nombre ?? '',
            descripcion: ruta?.descripcion ?? '',
            sentido: ruta?.sentido ?? '',
            es_complemento: ruta?.es_complemento ?? false,
            dias_viaje: ruta?.dias_viaje?.toString() ?? '',
            km_estandar: ruta?.km_estandar?.toString() ?? '',
            color: ruta?.color ?? '',
            nota: ruta?.nota ?? '',
            activa: ruta?.activa ?? true,
            paradas: ruta?.paradas ?? [
                { zona_origen: '', zona_destino: '', tipo_carga: '' },
            ],
            variantes: ruta?.variantes ?? [],
            lineas: ruta?.lineas ?? [],
        });
    const errores = errors as Record<string, string | undefined>;
    const opcionesVariante: Opcion[] = [
        { value: NINGUNO, label: 'Toda la ruta' },
        ...data.variantes.map((variante, indice) => ({
            value: String(indice),
            label: variante.nombre || `Variante ${indice + 1}`,
        })),
    ];

    const cambiarParada = (
        indice: number,
        cambios: Partial<ParadaRutaCatalogo>,
    ) =>
        setData(
            'paradas',
            data.paradas.map((parada, i) =>
                i === indice ? { ...parada, ...cambios } : parada,
            ),
        );
    const cambiarVariante = (
        indice: number,
        cambios: Partial<VarianteRutaCatalogo>,
    ) =>
        setData(
            'variantes',
            data.variantes.map((variante, i) =>
                i === indice ? { ...variante, ...cambios } : variante,
            ),
        );
    const cambiarLinea = (indice: number, cambios: Partial<LineaRuta>) =>
        setData(
            'lineas',
            data.lineas.map((linea, i) =>
                i === indice ? { ...linea, ...cambios } : linea,
            ),
        );

    /** Quitar una variante también corre las líneas que apuntaban a las siguientes. */
    const quitarVariante = (indice: number) =>
        setData((anterior) => ({
            ...anterior,
            variantes: anterior.variantes.filter((_, i) => i !== indice),
            lineas: anterior.lineas
                .filter((linea) => linea.variante !== indice)
                .map((linea) =>
                    linea.variante !== null && linea.variante > indice
                        ? { ...linea, variante: linea.variante - 1 }
                        : linea,
                ),
        }));

    const enviar = (evento: React.FormEvent) => {
        evento.preventDefault();

        // Un destino vacío no es lo mismo que «lo elige quien programa».
        if (data.paradas.some((parada) => parada.zona_destino === '')) {
            setError(
                'paradas',
                'Elige el destino de cada parada (o «Lo elige quien programa»).',
            );

            return;
        }

        const opcionesEnvio = { preserveScroll: true, onSuccess: onCerrar };

        if (ruta) {
            put(rutas.update(ruta.id).url, opcionesEnvio);

            return;
        }

        post(rutas.store().url, opcionesEnvio);
    };

    const quitar = () => {
        if (
            !ruta ||
            !window.confirm(
                ruta.usada
                    ? `«${ruta.nombre}» ya está en tickets: se desactiva (deja de ofrecerse). ¿Seguir?`
                    : `¿Quitar la ruta «${ruta.nombre}»?`,
            )
        ) {
            return;
        }

        router.delete(rutas.destroy(ruta.id).url, {
            preserveScroll: true,
            onSuccess: onCerrar,
        });
    };

    return (
        <Dialog open onOpenChange={(abierto) => !abierto && onCerrar()}>
            <DialogContent className="flex max-h-[92dvh] flex-col overflow-hidden md:max-w-4xl">
                <DialogHeader>
                    <DialogTitle>
                        {ruta ? `Editar «${ruta.nombre}»` : 'Nueva ruta'}
                    </DialogTitle>
                    <DialogDescription>
                        El recorrido y lo que se autoriza. Los tickets que ya
                        tienen esta ruta no cambian.
                    </DialogDescription>
                </DialogHeader>

                <form
                    id="ruta"
                    onSubmit={enviar}
                    className="flex min-h-0 flex-1 flex-col gap-5 overflow-y-auto px-1"
                >
                    <div className="grid gap-4 sm:grid-cols-[1fr_10rem_9rem]">
                        <Field label="Nombre" error={errors.nombre} required>
                            {(id) => (
                                <Input
                                    id={id}
                                    placeholder="Escoria a mina"
                                    value={data.nombre}
                                    onChange={(evento) =>
                                        setData('nombre', evento.target.value)
                                    }
                                />
                            )}
                        </Field>
                        <Field label="Tramo" error={errors.sentido}>
                            {() => (
                                <Elegir
                                    valor={data.sentido || NINGUNO}
                                    opciones={[
                                        { value: 'ida', label: 'Ida' },
                                        { value: 'regreso', label: 'Vuelta' },
                                        {
                                            value: NINGUNO,
                                            label: 'Cualquiera',
                                        },
                                    ]}
                                    onCambiar={(valor) =>
                                        setData(
                                            'sentido',
                                            valor === NINGUNO
                                                ? ''
                                                : (valor as 'ida' | 'regreso'),
                                        )
                                    }
                                />
                            )}
                        </Field>
                        <Field label="Color">
                            {() => (
                                <Elegir
                                    valor={data.color || NINGUNO}
                                    opciones={[
                                        { value: NINGUNO, label: 'Sin color' },
                                        ...COLORES_TARJETA.map((color) => ({
                                            value: color,
                                            label: color,
                                        })),
                                    ]}
                                    onCambiar={(valor) =>
                                        setData(
                                            'color',
                                            valor === NINGUNO ? '' : valor,
                                        )
                                    }
                                />
                            )}
                        </Field>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-[1fr_8rem_8rem]">
                        <Field label="Descripción" error={errors.descripcion}>
                            {(id) => (
                                <Input
                                    id={id}
                                    placeholder="Pisco → Mina"
                                    value={data.descripcion}
                                    onChange={(evento) =>
                                        setData(
                                            'descripcion',
                                            evento.target.value,
                                        )
                                    }
                                />
                            )}
                        </Field>
                        <Field
                            label="Días de viaje"
                            error={errors.dias_viaje}
                            ayuda="Para la llegada prevista."
                        >
                            {(id) => (
                                <Input
                                    id={id}
                                    type="number"
                                    min="0"
                                    value={data.dias_viaje}
                                    onChange={(evento) =>
                                        setData(
                                            'dias_viaje',
                                            evento.target.value,
                                        )
                                    }
                                />
                            )}
                        </Field>
                        <Field
                            label="Km"
                            error={errors.km_estandar}
                            ayuda="Lo normal del recorrido."
                        >
                            {(id) => (
                                <Input
                                    id={id}
                                    type="number"
                                    min="0"
                                    value={data.km_estandar}
                                    onChange={(evento) =>
                                        setData(
                                            'km_estandar',
                                            evento.target.value,
                                        )
                                    }
                                />
                            )}
                        </Field>
                    </div>

                    <label className="flex items-start gap-2 text-sm">
                        <Checkbox
                            className="mt-0.5"
                            checked={data.es_complemento}
                            onCheckedChange={(marcado) =>
                                setData('es_complemento', marcado === true)
                            }
                        />
                        <span>
                            Es un complemento
                            <span className="block text-xs text-muted-foreground">
                                Se suma a la ruta principal del tramo (un tramo
                                extra, una carga de paso) en vez de definirlo.
                            </span>
                        </span>
                    </label>

                    <section className="flex flex-col gap-2">
                        <span className="text-sm font-medium">Recorrido</span>
                        {data.paradas.map((parada, indice) => (
                            <div
                                key={indice}
                                className="grid grid-cols-2 gap-2 sm:grid-cols-[1fr_1fr_1fr_1fr_1fr_auto]"
                            >
                                <Elegir
                                    valor={parada.zona_origen}
                                    opciones={opciones.zonas}
                                    placeholder="Desde"
                                    invalido={Boolean(
                                        errores[
                                            `paradas.${indice}.zona_origen`
                                        ],
                                    )}
                                    onCambiar={(valor) =>
                                        cambiarParada(indice, {
                                            zona_origen: valor,
                                        })
                                    }
                                />
                                <Input
                                    placeholder="Lugar (opcional)"
                                    value={parada.lugar_origen ?? ''}
                                    onChange={(evento) =>
                                        cambiarParada(indice, {
                                            lugar_origen: evento.target.value,
                                        })
                                    }
                                />
                                <Elegir
                                    valor={parada.zona_destino ?? NINGUNO}
                                    opciones={[
                                        {
                                            value: NINGUNO,
                                            label: 'Lo elige quien programa',
                                        },
                                        ...opciones.zonas,
                                    ]}
                                    placeholder="Hasta"
                                    onCambiar={(valor) =>
                                        cambiarParada(indice, {
                                            zona_destino:
                                                valor === NINGUNO
                                                    ? null
                                                    : valor,
                                        })
                                    }
                                />
                                <Input
                                    placeholder="Lugar (opcional)"
                                    value={parada.lugar_destino ?? ''}
                                    onChange={(evento) =>
                                        cambiarParada(indice, {
                                            lugar_destino: evento.target.value,
                                        })
                                    }
                                />
                                <Elegir
                                    valor={parada.tipo_carga}
                                    opciones={opciones.cargas}
                                    placeholder="Carga"
                                    invalido={Boolean(
                                        errores[`paradas.${indice}.tipo_carga`],
                                    )}
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
                        {errores.paradas && (
                            <p className="text-sm text-destructive">
                                {errores.paradas}
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
                    </section>

                    <section className="flex flex-col gap-2">
                        <span className="text-sm font-medium">
                            Variantes por peso
                            <span className="block text-xs font-normal text-muted-foreground">
                                Solo si lo que se da depende de las toneladas
                                (Ransa). Sin variantes, la dotación es una sola.
                            </span>
                        </span>
                        {data.variantes.map((variante, indice) => (
                            <div
                                key={indice}
                                className="grid grid-cols-[1fr_7rem_7rem_auto] gap-2"
                            >
                                <Input
                                    placeholder="hasta 8 t"
                                    aria-invalid={
                                        Boolean(
                                            errores[
                                                `variantes.${indice}.nombre`
                                            ],
                                        ) || undefined
                                    }
                                    value={variante.nombre}
                                    onChange={(evento) =>
                                        cambiarVariante(indice, {
                                            nombre: evento.target.value,
                                        })
                                    }
                                />
                                <Input
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    placeholder="más de (t)"
                                    value={variante.peso_desde ?? ''}
                                    onChange={(evento) =>
                                        cambiarVariante(indice, {
                                            peso_desde: numeroONulo(
                                                evento.target.value,
                                            ),
                                        })
                                    }
                                />
                                <Input
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    placeholder="hasta (t)"
                                    aria-invalid={
                                        Boolean(
                                            errores[
                                                `variantes.${indice}.peso_hasta`
                                            ],
                                        ) || undefined
                                    }
                                    value={variante.peso_hasta ?? ''}
                                    onChange={(evento) =>
                                        cambiarVariante(indice, {
                                            peso_hasta: numeroONulo(
                                                evento.target.value,
                                            ),
                                        })
                                    }
                                />
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    aria-label="Quitar variante"
                                    onClick={() => quitarVariante(indice)}
                                >
                                    <X className="size-4" />
                                </Button>
                            </div>
                        ))}
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className="self-start"
                            onClick={() =>
                                setData('variantes', [
                                    ...data.variantes,
                                    {
                                        id: null,
                                        nombre: '',
                                        peso_desde:
                                            data.variantes.at(-1)?.peso_hasta ??
                                            null,
                                        peso_hasta: null,
                                    },
                                ])
                            }
                        >
                            <Plus className="size-4" />
                            Variante
                        </Button>
                    </section>

                    <section className="flex flex-col gap-2">
                        <span className="text-sm font-medium">
                            Dotación
                            <span className="block text-xs font-normal text-muted-foreground">
                                Combustible sin galones = «por definir»: el
                                tramo no tiene tope hasta completarlo. «Tanque
                                full» (Kio): sin galones, va aparte del tope y
                                lo dice el voucher.
                            </span>
                        </span>
                        {data.lineas.map((linea, indice) => {
                            const combustible = linea.tipo === 'combustible';

                            return (
                                <div
                                    key={linea.id ?? `n${indice}`}
                                    className="grid grid-cols-2 gap-2 sm:grid-cols-[8rem_9rem_1fr_9rem_7rem_auto]"
                                >
                                    <Elegir
                                        valor={linea.tipo}
                                        opciones={opciones.tiposLinea}
                                        onCambiar={(valor) =>
                                            cambiarLinea(indice, {
                                                tipo: valor as LineaRuta['tipo'],
                                            })
                                        }
                                    />
                                    <Elegir
                                        valor={
                                            linea.variante === null
                                                ? NINGUNO
                                                : String(linea.variante)
                                        }
                                        opciones={opcionesVariante}
                                        onCambiar={(valor) =>
                                            cambiarLinea(indice, {
                                                variante:
                                                    valor === NINGUNO
                                                        ? null
                                                        : Number(valor),
                                            })
                                        }
                                    />
                                    <Input
                                        placeholder="Concepto"
                                        aria-invalid={
                                            Boolean(
                                                errores[
                                                    `lineas.${indice}.concepto`
                                                ],
                                            ) || undefined
                                        }
                                        value={linea.concepto}
                                        onChange={(evento) =>
                                            cambiarLinea(indice, {
                                                concepto: evento.target.value,
                                            })
                                        }
                                    />
                                    {combustible ? (
                                        <Elegir
                                            valor={
                                                linea.punto_abastecimiento_id ===
                                                null
                                                    ? NINGUNO
                                                    : String(
                                                          linea.punto_abastecimiento_id,
                                                      )
                                            }
                                            opciones={[
                                                {
                                                    value: NINGUNO,
                                                    label: 'Total del tramo',
                                                },
                                                ...opciones.puntos,
                                            ]}
                                            onCambiar={(valor) =>
                                                cambiarLinea(indice, {
                                                    punto_abastecimiento_id:
                                                        valor === NINGUNO
                                                            ? null
                                                            : Number(valor),
                                                })
                                            }
                                        />
                                    ) : (
                                        <span />
                                    )}
                                    <div className="flex flex-col gap-1">
                                        <Input
                                            type="number"
                                            disabled={
                                                combustible &&
                                                linea.tanque_lleno
                                            }
                                            min="0"
                                            step="0.01"
                                            placeholder={
                                                combustible
                                                    ? linea.tanque_lleno
                                                        ? 'full'
                                                        : 'gal (¿?)'
                                                    : 'S/'
                                            }
                                            aria-invalid={
                                                Boolean(
                                                    errores[
                                                        `lineas.${indice}.monto`
                                                    ] ??
                                                    errores[
                                                        `lineas.${indice}.galones`
                                                    ],
                                                ) || undefined
                                            }
                                            value={
                                                (combustible
                                                    ? linea.galones
                                                    : linea.monto) ?? ''
                                            }
                                            onChange={(evento) =>
                                                cambiarLinea(
                                                    indice,
                                                    combustible
                                                        ? {
                                                              galones:
                                                                  numeroONulo(
                                                                      evento
                                                                          .target
                                                                          .value,
                                                                  ),
                                                          }
                                                        : {
                                                              monto: numeroONulo(
                                                                  evento.target
                                                                      .value,
                                                              ),
                                                          },
                                                )
                                            }
                                        />
                                        {combustible && (
                                            <label
                                                className="flex items-center gap-1.5 text-[11px] text-muted-foreground"
                                                title="Se llena el tanque: sin galones fijos, lo dice el voucher; no suma al tope"
                                            >
                                                <Checkbox
                                                    checked={linea.tanque_lleno}
                                                    onCheckedChange={(
                                                        marcado,
                                                    ) =>
                                                        cambiarLinea(indice, {
                                                            tanque_lleno:
                                                                marcado ===
                                                                true,
                                                            galones:
                                                                marcado === true
                                                                    ? null
                                                                    : linea.galones,
                                                        })
                                                    }
                                                />
                                                Tanque full
                                            </label>
                                        )}
                                    </div>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        aria-label="Quitar línea"
                                        onClick={() =>
                                            setData(
                                                'lineas',
                                                data.lineas.filter(
                                                    (_, i) => i !== indice,
                                                ),
                                            )
                                        }
                                    >
                                        <X className="size-4" />
                                    </Button>
                                </div>
                            );
                        })}
                        {Object.entries(errores).find(([clave]) =>
                            clave.startsWith('lineas.'),
                        ) && (
                            <p className="text-sm text-destructive">
                                {
                                    Object.entries(errores).find(([clave]) =>
                                        clave.startsWith('lineas.'),
                                    )?.[1]
                                }
                            </p>
                        )}
                        <div className="flex gap-2">
                            {(
                                [
                                    ['combustible', 'Combustible'],
                                    ['viatico', 'Viático'],
                                    ['peaje', 'Peaje'],
                                ] as const
                            ).map(([tipo, etiqueta]) => (
                                <Button
                                    key={tipo}
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() =>
                                        setData('lineas', [
                                            ...data.lineas,
                                            {
                                                id: null,
                                                variante: null,
                                                tipo,
                                                concepto: etiqueta,
                                                punto_abastecimiento_id: null,
                                                galones: null,
                                                tanque_lleno: false,
                                                monto: null,
                                            },
                                        ])
                                    }
                                >
                                    <Plus className="size-4" />
                                    {etiqueta}
                                </Button>
                            ))}
                        </div>
                    </section>

                    <Field label="Nota" error={errors.nota}>
                        {(id) => (
                            <Textarea
                                id={id}
                                rows={2}
                                value={data.nota}
                                onChange={(evento) =>
                                    setData('nota', evento.target.value)
                                }
                            />
                        )}
                    </Field>

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
                    {ruta ? (
                        <Button type="button" variant="ghost" onClick={quitar}>
                            {ruta.usada ? 'Desactivar' : 'Quitar ruta'}
                        </Button>
                    ) : (
                        <span />
                    )}
                    <Button type="submit" form="ruta" disabled={processing}>
                        Guardar
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
