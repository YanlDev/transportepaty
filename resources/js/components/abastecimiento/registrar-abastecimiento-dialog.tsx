import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import { store } from '@/actions/App/Http/Controllers/AbastecimientoController';
import { CampoSelector, PanelBuscador } from '@/components/selector-en-dialogo';
import type { OpcionBuscable } from '@/components/selector-en-dialogo';
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
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { TractoAbastecimiento } from '@/types/abastecimiento';

type FormData = {
    vehiculo_id: number | null;
    vuelta_id: number | null;
    punto_abastecimiento_id: number | null;
    fecha: string;
    galones: string;
    tanque_lleno: boolean;
    observacion: string;
};

/** Lo que se sabe al abrir: desde la tarjeta de una unidad, o nada. */
export type InicialAbastecimiento = {
    vehiculo_id?: number;
    vuelta_id?: number | null;
    punto_abastecimiento_id?: number | null;
    galones?: number | null;
};

function hoy(): string {
    const ahora = new Date();
    const mes = String(ahora.getMonth() + 1).padStart(2, '0');
    const dia = String(ahora.getDate()).padStart(2, '0');

    return `${ahora.getFullYear()}-${mes}-${dia}`;
}

/**
 * Registrar lo que abastecimiento autorizó por el QR de Repsol (o en Kio):
 * unidad, punto y galones. Desde la tarjeta de una unidad llega con todo
 * puesto, incluidos los galones que le faltan en ese punto.
 */
export function RegistrarAbastecimientoDialog({
    open,
    onOpenChange,
    inicial,
    tractos,
    puntos,
    galonesTanqueLleno,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    inicial: InicialAbastecimiento;
    tractos: TractoAbastecimiento[];
    puntos: { id: number; nombre: string }[];
    galonesTanqueLleno: number | null;
}) {
    const { data, setData, post, processing, errors } = useForm<FormData>({
        vehiculo_id: inicial.vehiculo_id ?? null,
        vuelta_id: inicial.vuelta_id ?? null,
        punto_abastecimiento_id: inicial.punto_abastecimiento_id ?? null,
        fecha: hoy(),
        galones: inicial.galones ? String(inicial.galones) : '',
        tanque_lleno: false,
        observacion: '',
    });
    const [eligiendo, setEligiendo] = useState(false);

    const opcionesTractos: OpcionBuscable[] = tractos.map((tracto) => ({
        valor: tracto.id,
        etiqueta: tracto.placa,
        detalle: tracto.vuelta ?? undefined,
    }));
    const tracto = tractos.find((opcion) => opcion.id === data.vehiculo_id);

    const enviar = (evento: React.FormEvent) => {
        evento.preventDefault();
        post(store().url, {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="flex flex-col overflow-hidden md:max-h-[85dvh] md:max-w-md">
                {eligiendo ? (
                    <>
                        <DialogHeader className="sr-only">
                            <DialogTitle>Elegir unidad</DialogTitle>
                        </DialogHeader>
                        <PanelBuscador
                            titulo="Unidad"
                            valor={data.vehiculo_id}
                            opciones={opcionesTractos}
                            onElegir={(valor) => {
                                const elegido = tractos.find(
                                    (opcion) => opcion.id === valor,
                                );
                                setData((anterior) => ({
                                    ...anterior,
                                    vehiculo_id: valor,
                                    vuelta_id: elegido?.vuelta_id ?? null,
                                }));
                                setEligiendo(false);
                            }}
                            onVolver={() => setEligiendo(false)}
                        />
                    </>
                ) : (
                    <>
                        <DialogHeader>
                            <DialogTitle>Registrar abastecimiento</DialogTitle>
                            <DialogDescription>
                                Lo que se autorizó por el QR de Repsol o en Kio.
                            </DialogDescription>
                        </DialogHeader>

                        <form
                            id="registrar-abastecimiento"
                            onSubmit={enviar}
                            className="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto px-1"
                        >
                            <Field
                                label="Unidad"
                                error={errors.vehiculo_id}
                                required
                                ayuda={
                                    tracto
                                        ? tracto.vuelta
                                            ? `Va a la vuelta ${tracto.vuelta}.`
                                            : 'No tiene una vuelta abierta: queda suelto.'
                                        : undefined
                                }
                            >
                                {(id) => (
                                    <CampoSelector
                                        id={id}
                                        etiqueta="Unidad"
                                        elegida={opcionesTractos.find(
                                            (opcion) =>
                                                opcion.valor ===
                                                data.vehiculo_id,
                                        )}
                                        invalido={Boolean(errors.vehiculo_id)}
                                        onAbrir={() => setEligiendo(true)}
                                    />
                                )}
                            </Field>

                            <div className="grid grid-cols-2 gap-4">
                                <Field
                                    label="Punto"
                                    error={errors.punto_abastecimiento_id}
                                >
                                    {() => (
                                        <Select
                                            value={
                                                data.punto_abastecimiento_id
                                                    ? String(
                                                          data.punto_abastecimiento_id,
                                                      )
                                                    : undefined
                                            }
                                            onValueChange={(valor) =>
                                                setData(
                                                    'punto_abastecimiento_id',
                                                    Number(valor),
                                                )
                                            }
                                        >
                                            <SelectTrigger className="w-full">
                                                <SelectValue placeholder="Elegir…" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {puntos.map((punto) => (
                                                    <SelectItem
                                                        key={punto.id}
                                                        value={String(punto.id)}
                                                    >
                                                        {punto.nombre}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    )}
                                </Field>

                                <Field
                                    label="Fecha"
                                    error={errors.fecha}
                                    required
                                >
                                    {(id) => (
                                        <Input
                                            id={id}
                                            type="date"
                                            value={data.fecha}
                                            onChange={(evento) =>
                                                setData(
                                                    'fecha',
                                                    evento.target.value,
                                                )
                                            }
                                        />
                                    )}
                                </Field>
                            </div>

                            <div className="flex items-center gap-2">
                                <Checkbox
                                    id="tanque-lleno"
                                    checked={data.tanque_lleno}
                                    onCheckedChange={(marcado) =>
                                        setData(
                                            'tanque_lleno',
                                            marcado === true,
                                        )
                                    }
                                />
                                <Label htmlFor="tanque-lleno">
                                    Tanque lleno (full)
                                    {galonesTanqueLleno !== null &&
                                        ` · ${galonesTanqueLleno} gal`}
                                </Label>
                            </div>

                            <Field
                                label="Galones"
                                error={errors.galones}
                                required={!data.tanque_lleno}
                                ayuda={
                                    data.tanque_lleno &&
                                    galonesTanqueLleno !== null
                                        ? 'Vacío: se registra el tanque lleno configurado.'
                                        : undefined
                                }
                            >
                                {(id) => (
                                    <Input
                                        id={id}
                                        type="number"
                                        inputMode="decimal"
                                        min="0"
                                        step="0.1"
                                        value={data.galones}
                                        onChange={(evento) =>
                                            setData(
                                                'galones',
                                                evento.target.value,
                                            )
                                        }
                                    />
                                )}
                            </Field>

                            <Field
                                label="Observación"
                                error={errors.observacion}
                            >
                                {(id) => (
                                    <Input
                                        id={id}
                                        value={data.observacion}
                                        onChange={(evento) =>
                                            setData(
                                                'observacion',
                                                evento.target.value,
                                            )
                                        }
                                    />
                                )}
                            </Field>
                        </form>

                        <DialogFooter>
                            <Button
                                type="submit"
                                form="registrar-abastecimiento"
                                disabled={processing}
                            >
                                Registrar
                            </Button>
                        </DialogFooter>
                    </>
                )}
            </DialogContent>
        </Dialog>
    );
}
