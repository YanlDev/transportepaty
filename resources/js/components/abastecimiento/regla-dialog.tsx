import { router, useForm } from '@inertiajs/react';
import { Plus, X } from 'lucide-react';
import configuracion from '@/actions/App/Http/Controllers/ConfiguracionAbastecimientoController';
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
import { COLORES_TARJETA, colorTarjeta } from '@/lib/color-tarjeta';
import { cn } from '@/lib/utils';
import type {
    LineaRegla,
    OpcionesConfiguracion,
    PuntoAbastecimiento,
    ReglaAbastecimiento,
} from '@/types/abastecimiento';
import type { Opcion } from '@/types/vueltas';

/** El valor de «cualquiera» en los criterios: el Select no admite vacío. */
const CUALQUIERA = '__cualquiera';

type FormData = {
    nombre: string;
    color: string;
    sentido: string;
    zona_origen: string;
    zona_destino: string;
    tipo_carga: string;
    lugares: string;
    peso_desde: string;
    peso_hasta: string;
    nota: string;
    activa: boolean;
    lineas: LineaRegla[];
};

function Criterio({
    label,
    valor,
    opciones,
    onCambiar,
}: {
    label: string;
    valor: string;
    opciones: Opcion[];
    onCambiar: (valor: string) => void;
}) {
    return (
        <Field label={label}>
            {() => (
                <Select
                    value={valor || CUALQUIERA}
                    onValueChange={(nuevo) =>
                        onCambiar(nuevo === CUALQUIERA ? '' : nuevo)
                    }
                >
                    <SelectTrigger className="w-full">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value={CUALQUIERA}>Cualquiera</SelectItem>
                        {opciones.map((opcion) => (
                            <SelectItem key={opcion.value} value={opcion.value}>
                                {opcion.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            )}
        </Field>
    );
}

/**
 * Agregar o editar una tarjeta de la pizarra: a qué trayecto se aplica (todo
 * opcional) y sus filas de galones, viáticos y peajes.
 */
export function ReglaDialog({
    open,
    onOpenChange,
    regla,
    puntos,
    opciones,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** `null` para una nueva. */
    regla: ReglaAbastecimiento | null;
    puntos: PuntoAbastecimiento[];
    opciones: OpcionesConfiguracion;
}) {
    const { data, setData, post, put, processing, errors } = useForm<FormData>({
        nombre: regla?.nombre ?? '',
        color: regla?.color ?? 'gris',
        sentido: regla?.sentido ?? '',
        zona_origen: regla?.zona_origen ?? '',
        zona_destino: regla?.zona_destino ?? '',
        tipo_carga: regla?.tipo_carga ?? '',
        lugares: regla?.lugares ?? '',
        peso_desde: regla?.peso_desde?.toString() ?? '',
        peso_hasta: regla?.peso_hasta?.toString() ?? '',
        nota: regla?.nota ?? '',
        activa: regla?.activa ?? true,
        lineas: regla?.lineas ?? [],
    });
    const errores = errors as Record<string, string | undefined>;

    const cambiarLinea = (indice: number, cambios: Partial<LineaRegla>) =>
        setData(
            'lineas',
            data.lineas.map((linea, i) =>
                i === indice ? { ...linea, ...cambios } : linea,
            ),
        );

    const agregarLinea = (tipo: LineaRegla['tipo']) =>
        setData('lineas', [
            ...data.lineas,
            {
                tipo,
                concepto: '',
                punto_abastecimiento_id: null,
                galones: null,
                tanque_lleno: false,
                monto: null,
            },
        ]);

    const enviar = (evento: React.FormEvent) => {
        evento.preventDefault();
        const opcionesEnvio = {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        };

        if (regla) {
            put(configuracion.updateRegla(regla.id).url, opcionesEnvio);

            return;
        }

        post(configuracion.storeRegla().url, opcionesEnvio);
    };

    const quitar = () => {
        if (
            !regla ||
            !window.confirm(`¿Quitar la tarjeta «${regla.nombre}»?`)
        ) {
            return;
        }

        router.delete(configuracion.destroyRegla(regla.id).url, {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="flex flex-col overflow-hidden md:max-h-[90dvh] md:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>
                        {regla ? 'Editar tarjeta' : 'Nueva tarjeta'}
                    </DialogTitle>
                    <DialogDescription>
                        Lo que no se elija vale para cualquiera. Si varias
                        tarjetas calzan, gana la que pide más cosas.
                    </DialogDescription>
                </DialogHeader>

                <form
                    id="regla-abastecimiento"
                    onSubmit={enviar}
                    className="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto px-1"
                >
                    <div className="grid gap-4 sm:grid-cols-[1fr_auto]">
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
                        <Field label="Color">
                            {() => (
                                <div className="flex gap-1.5 pt-1">
                                    {COLORES_TARJETA.map((color) => (
                                        <button
                                            key={color}
                                            type="button"
                                            aria-label={color}
                                            onClick={() =>
                                                setData('color', color)
                                            }
                                            className={cn(
                                                'size-7 rounded-full border-2',
                                                colorTarjeta(color).cabecera,
                                                data.color === color
                                                    ? 'border-foreground'
                                                    : 'border-transparent',
                                            )}
                                        />
                                    ))}
                                </div>
                            )}
                        </Field>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <Criterio
                            label="Tramo"
                            valor={data.sentido}
                            opciones={opciones.sentidos}
                            onCambiar={(valor) => setData('sentido', valor)}
                        />
                        <Criterio
                            label="Carga"
                            valor={data.tipo_carga}
                            opciones={opciones.cargas}
                            onCambiar={(valor) => setData('tipo_carga', valor)}
                        />
                        <Criterio
                            label="Desde"
                            valor={data.zona_origen}
                            opciones={opciones.zonas}
                            onCambiar={(valor) => setData('zona_origen', valor)}
                        />
                        <Criterio
                            label="Hasta"
                            valor={data.zona_destino}
                            opciones={opciones.zonas}
                            onCambiar={(valor) =>
                                setData('zona_destino', valor)
                            }
                        />
                    </div>

                    <div className="grid gap-4 sm:grid-cols-3">
                        <Field
                            label="Lugares"
                            error={errors.lugares}
                            ayuda="Separados por coma: CHILCA, ILO."
                        >
                            {(id) => (
                                <Input
                                    id={id}
                                    value={data.lugares}
                                    onChange={(evento) =>
                                        setData('lugares', evento.target.value)
                                    }
                                />
                            )}
                        </Field>
                        <Field label="Más de (t)" error={errors.peso_desde}>
                            {(id) => (
                                <Input
                                    id={id}
                                    type="number"
                                    min="0"
                                    step="0.5"
                                    value={data.peso_desde}
                                    onChange={(evento) =>
                                        setData(
                                            'peso_desde',
                                            evento.target.value,
                                        )
                                    }
                                />
                            )}
                        </Field>
                        <Field label="Hasta (t)" error={errors.peso_hasta}>
                            {(id) => (
                                <Input
                                    id={id}
                                    type="number"
                                    min="0"
                                    step="0.5"
                                    value={data.peso_hasta}
                                    onChange={(evento) =>
                                        setData(
                                            'peso_hasta',
                                            evento.target.value,
                                        )
                                    }
                                />
                            )}
                        </Field>
                    </div>

                    <div className="flex flex-col gap-2">
                        <span className="text-sm font-medium">Filas</span>
                        {data.lineas.map((linea, indice) => {
                            const combustible = linea.tipo === 'combustible';

                            return (
                                <div
                                    key={indice}
                                    className="grid grid-cols-[auto_1fr_auto] items-center gap-2 rounded-md border p-2"
                                >
                                    <span className="w-20 text-xs text-muted-foreground">
                                        {opciones.tiposLinea.find(
                                            (tipo) => tipo.value === linea.tipo,
                                        )?.label ?? linea.tipo}
                                    </span>
                                    <div className="grid min-w-0 gap-2 sm:grid-cols-3">
                                        <Input
                                            placeholder="Concepto"
                                            value={linea.concepto}
                                            aria-invalid={
                                                Boolean(
                                                    errores[
                                                        `lineas.${indice}.concepto`
                                                    ],
                                                ) || undefined
                                            }
                                            onChange={(evento) =>
                                                cambiarLinea(indice, {
                                                    concepto:
                                                        evento.target.value,
                                                })
                                            }
                                        />
                                        {combustible ? (
                                            <>
                                                <Select
                                                    value={
                                                        linea.punto_abastecimiento_id
                                                            ? String(
                                                                  linea.punto_abastecimiento_id,
                                                              )
                                                            : CUALQUIERA
                                                    }
                                                    onValueChange={(valor) =>
                                                        cambiarLinea(indice, {
                                                            punto_abastecimiento_id:
                                                                valor ===
                                                                CUALQUIERA
                                                                    ? null
                                                                    : Number(
                                                                          valor,
                                                                      ),
                                                        })
                                                    }
                                                >
                                                    <SelectTrigger className="w-full">
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem
                                                            value={CUALQUIERA}
                                                        >
                                                            Sin punto
                                                        </SelectItem>
                                                        {puntos.map((punto) => (
                                                            <SelectItem
                                                                key={punto.id}
                                                                value={String(
                                                                    punto.id,
                                                                )}
                                                            >
                                                                {punto.nombre}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                                <div className="flex items-center gap-2">
                                                    <Input
                                                        type="number"
                                                        min="0"
                                                        placeholder="gal"
                                                        disabled={
                                                            linea.tanque_lleno
                                                        }
                                                        value={
                                                            linea.galones ?? ''
                                                        }
                                                        onChange={(evento) =>
                                                            cambiarLinea(
                                                                indice,
                                                                {
                                                                    galones:
                                                                        evento
                                                                            .target
                                                                            .value ===
                                                                        ''
                                                                            ? null
                                                                            : Number(
                                                                                  evento
                                                                                      .target
                                                                                      .value,
                                                                              ),
                                                                },
                                                            )
                                                        }
                                                    />
                                                    <label className="flex items-center gap-1 text-xs whitespace-nowrap">
                                                        <Checkbox
                                                            checked={
                                                                linea.tanque_lleno
                                                            }
                                                            onCheckedChange={(
                                                                marcado,
                                                            ) =>
                                                                cambiarLinea(
                                                                    indice,
                                                                    {
                                                                        tanque_lleno:
                                                                            marcado ===
                                                                            true,
                                                                    },
                                                                )
                                                            }
                                                        />
                                                        full
                                                    </label>
                                                </div>
                                            </>
                                        ) : (
                                            <Input
                                                type="number"
                                                min="0"
                                                step="0.01"
                                                placeholder="S/"
                                                value={linea.monto ?? ''}
                                                onChange={(evento) =>
                                                    cambiarLinea(indice, {
                                                        monto:
                                                            evento.target
                                                                .value === ''
                                                                ? null
                                                                : Number(
                                                                      evento
                                                                          .target
                                                                          .value,
                                                                  ),
                                                    })
                                                }
                                            />
                                        )}
                                    </div>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        aria-label="Quitar fila"
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
                        <div className="flex flex-wrap gap-2">
                            {opciones.tiposLinea.map((tipo) => (
                                <Button
                                    key={tipo.value}
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() =>
                                        agregarLinea(
                                            tipo.value as LineaRegla['tipo'],
                                        )
                                    }
                                >
                                    <Plus className="size-4" />
                                    {tipo.label}
                                </Button>
                            ))}
                        </div>
                    </div>

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
                        Activa
                    </label>
                </form>

                <DialogFooter className="gap-2 sm:justify-between">
                    {regla ? (
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={quitar}
                            disabled={processing}
                        >
                            Quitar tarjeta
                        </Button>
                    ) : (
                        <span />
                    )}
                    <Button
                        type="submit"
                        form="regla-abastecimiento"
                        disabled={processing}
                    >
                        Guardar
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
