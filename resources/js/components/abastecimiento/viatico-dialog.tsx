import { useForm } from '@inertiajs/react';
import viaticos from '@/actions/App/Http/Controllers/ViaticoController';
import { Button } from '@/components/ui/button';
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
import { Textarea } from '@/components/ui/textarea';

/**
 * Qué se abre: depositar uno de la pizarra, agregar un imprevisto, corregir
 * un depósito o anularlo.
 */
export type AccionViatico =
    | {
          tipo: 'depositar';
          vueltaId: number;
          codigo: string | null;
          lineaId: number;
          concepto: string;
          previsto: number | null;
      }
    | { tipo: 'imprevisto'; vueltaId: number; codigo: string | null }
    | {
          tipo: 'corregir';
          id: number;
          concepto: string;
          imprevisto: boolean;
          previsto: number | null;
          monto: number;
          fecha: string;
          numero_operacion: string | null;
          observacion: string | null;
      }
    | { tipo: 'anular'; id: number; concepto: string; monto: number };

function hoy(): string {
    const ahora = new Date();
    const mes = String(ahora.getMonth() + 1).padStart(2, '0');
    const dia = String(ahora.getDate()).padStart(2, '0');

    return `${ahora.getFullYear()}-${mes}-${dia}`;
}

const soles = (valor: number) =>
    `S/ ${valor.toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

export function ViaticoDialog({
    accion,
    onCerrar,
}: {
    accion: AccionViatico;
    onCerrar: () => void;
}) {
    return (
        <Dialog open onOpenChange={(abierto) => !abierto && onCerrar()}>
            <DialogContent className="flex flex-col overflow-hidden md:max-h-[85dvh] md:max-w-md">
                {accion.tipo === 'anular' ? (
                    <FormAnular accion={accion} onCerrar={onCerrar} />
                ) : (
                    <FormDeposito accion={accion} onCerrar={onCerrar} />
                )}
            </DialogContent>
        </Dialog>
    );
}

/**
 * El depósito: el monto viene puesto con el de la pizarra y se cambia si se
 * depositó otro. El concepto solo se escribe en un imprevisto.
 */
function FormDeposito({
    accion,
    onCerrar,
}: {
    accion: Exclude<AccionViatico, { tipo: 'anular' }>;
    onCerrar: () => void;
}) {
    const corrigiendo = accion.tipo === 'corregir';
    const conConcepto =
        accion.tipo === 'imprevisto' ||
        (accion.tipo === 'corregir' && accion.imprevisto);
    const previsto = accion.tipo === 'imprevisto' ? null : accion.previsto;

    const { data, setData, post, put, processing, errors } = useForm({
        vuelta_id: accion.tipo === 'corregir' ? null : accion.vueltaId,
        linea_regla_abastecimiento_id:
            accion.tipo === 'depositar' ? accion.lineaId : null,
        concepto: accion.tipo === 'corregir' ? accion.concepto : '',
        monto:
            accion.tipo === 'corregir'
                ? String(accion.monto)
                : previsto !== null
                  ? String(previsto)
                  : '',
        fecha: accion.tipo === 'corregir' ? accion.fecha : hoy(),
        numero_operacion:
            accion.tipo === 'corregir' ? (accion.numero_operacion ?? '') : '',
        observacion:
            accion.tipo === 'corregir' ? (accion.observacion ?? '') : '',
    });

    const diferencia =
        previsto !== null && data.monto !== ''
            ? Number(data.monto) - previsto
            : 0;

    const enviar = (evento: React.FormEvent) => {
        evento.preventDefault();
        const opciones = { preserveScroll: true, onSuccess: onCerrar };

        if (accion.tipo === 'corregir') {
            put(viaticos.update(accion.id).url, opciones);
        } else {
            post(viaticos.store().url, opciones);
        }
    };

    return (
        <>
            <DialogHeader>
                <DialogTitle>
                    {corrigiendo
                        ? `Corregir · ${accion.concepto}`
                        : accion.tipo === 'imprevisto'
                          ? 'Viático imprevisto'
                          : `Depositar · ${accion.concepto}`}
                </DialogTitle>
                <DialogDescription>
                    {corrigiendo
                        ? 'El cambio queda en el historial con el valor anterior.'
                        : `Ticket ${accion.codigo ?? ''}${previsto !== null ? ` · la pizarra dice ${soles(previsto)}` : ''}.`}
                </DialogDescription>
            </DialogHeader>

            <form
                id="viatico"
                onSubmit={enviar}
                className="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto px-1"
            >
                {errors.linea_regla_abastecimiento_id && (
                    <p className="text-sm text-destructive">
                        {errors.linea_regla_abastecimiento_id}
                    </p>
                )}

                {conConcepto && (
                    <Field label="Concepto" error={errors.concepto} required>
                        {(id) => (
                            <Input
                                id={id}
                                value={data.concepto}
                                maxLength={120}
                                placeholder="Ej.: peaje extra, alojamiento"
                                onChange={(evento) =>
                                    setData('concepto', evento.target.value)
                                }
                            />
                        )}
                    </Field>
                )}

                <div className="grid grid-cols-2 gap-4">
                    <Field
                        label="Monto depositado (S/)"
                        error={errors.monto}
                        required
                    >
                        {(id) => (
                            <Input
                                id={id}
                                type="number"
                                inputMode="decimal"
                                min="0"
                                step="0.01"
                                value={data.monto}
                                onChange={(evento) =>
                                    setData('monto', evento.target.value)
                                }
                            />
                        )}
                    </Field>
                    <Field label="Fecha" error={errors.fecha} required>
                        {(id) => (
                            <Input
                                id={id}
                                type="date"
                                value={data.fecha}
                                onChange={(evento) =>
                                    setData('fecha', evento.target.value)
                                }
                            />
                        )}
                    </Field>
                </div>

                {diferencia !== 0 && (
                    <p className="-mt-2 text-xs text-amber-700 dark:text-amber-400">
                        {diferencia > 0 ? '+' : '−'}
                        {soles(Math.abs(diferencia))} respecto a la pizarra.
                    </p>
                )}

                <Field label="N.º de operación" error={errors.numero_operacion}>
                    {(id) => (
                        <Input
                            id={id}
                            value={data.numero_operacion}
                            maxLength={40}
                            onChange={(evento) =>
                                setData('numero_operacion', evento.target.value)
                            }
                        />
                    )}
                </Field>

                <Field label="Observación" error={errors.observacion}>
                    {(id) => (
                        <Textarea
                            id={id}
                            rows={2}
                            maxLength={500}
                            value={data.observacion}
                            onChange={(evento) =>
                                setData('observacion', evento.target.value)
                            }
                        />
                    )}
                </Field>
            </form>

            <DialogFooter>
                <Button type="submit" form="viatico" disabled={processing}>
                    {corrigiendo ? 'Guardar' : 'Depositar'}
                </Button>
            </DialogFooter>
        </>
    );
}

/** Anular no borra: el depósito queda tachado, con quién y por qué. */
function FormAnular({
    accion,
    onCerrar,
}: {
    accion: Extract<AccionViatico, { tipo: 'anular' }>;
    onCerrar: () => void;
}) {
    const { data, setData, post, processing, errors } = useForm({
        motivo: '',
    });

    const enviar = (evento: React.FormEvent) => {
        evento.preventDefault();
        post(viaticos.anular(accion.id).url, {
            preserveScroll: true,
            onSuccess: onCerrar,
        });
    };

    return (
        <>
            <DialogHeader>
                <DialogTitle>Anular · {accion.concepto}</DialogTitle>
                <DialogDescription>
                    El depósito de {soles(accion.monto)} queda anulado, no se
                    borra: se ve tachado y en el historial.
                </DialogDescription>
            </DialogHeader>

            <form
                id="anular-viatico"
                onSubmit={enviar}
                className="flex flex-col gap-4 px-1"
            >
                <Field label="Motivo" error={errors.motivo} required>
                    {(id) => (
                        <Textarea
                            id={id}
                            rows={2}
                            maxLength={255}
                            autoFocus
                            value={data.motivo}
                            placeholder="Ej.: depósito duplicado, monto equivocado"
                            onChange={(evento) =>
                                setData('motivo', evento.target.value)
                            }
                        />
                    )}
                </Field>
            </form>

            <DialogFooter>
                <Button
                    type="submit"
                    form="anular-viatico"
                    variant="destructive"
                    disabled={processing}
                >
                    Anular
                </Button>
            </DialogFooter>
        </>
    );
}
