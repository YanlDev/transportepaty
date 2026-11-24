import { useForm } from '@inertiajs/react';
import { FileCheck } from 'lucide-react';
import { useState } from 'react';
import { completar } from '@/actions/App/Http/Controllers/Almacen/IngresoAlmacenController';
import { CampoArchivo } from '@/components/campo-archivo';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';

export type TipoDeComprobante = {
    value: string;
    label: string;
    exige_numero: boolean;
    lleva_igv: boolean;
};

/**
 * Completar el comprobante de una recepción que entró sin él: qué papel es,
 * su número, su fecha, su total y la foto. El costo de lo recibido se
 * corrige con lo que dice el papel.
 */
export function CompletarComprobante({
    ingresoId,
    totalDelPedido,
    tipos,
    hoy,
    tipoCambio = null,
}: {
    ingresoId: number;
    totalDelPedido: number;
    tipos: TipoDeComprobante[];
    hoy: string;
    /** Solo en dólares: el tipo de cambio con que entró la recepción. */
    tipoCambio?: number | null;
}) {
    const [preparando, setPreparando] = useState(false);
    const { data, setData, post, processing, progress, errors } = useForm<{
        tipo_comprobante: string;
        serie_numero: string;
        fecha_emision: string;
        precios_con_igv: boolean;
        total_comprobante: string;
        tipo_cambio: string;
        comprobante: File | null;
    }>({
        tipo_comprobante: 'factura',
        serie_numero: '',
        fecha_emision: hoy,
        precios_con_igv: true,
        total_comprobante: totalDelPedido.toFixed(2),
        tipo_cambio: tipoCambio === null ? '' : String(tipoCambio),
        comprobante: null,
    });
    const errores = errors as Record<string, string | undefined>;
    const tipo = tipos.find((t) => t.value === data.tipo_comprobante);

    const enviar = (evento: React.FormEvent) => {
        evento.preventDefault();
        post(completar(ingresoId).url, {
            preserveScroll: true,
            forceFormData: true,
        });
    };

    return (
        <form
            onSubmit={enviar}
            className="flex flex-col gap-4 rounded-xl border border-amber-500/40 bg-amber-500/5 p-5"
        >
            <div>
                <h2 className="flex items-center gap-2 text-sm font-semibold">
                    <FileCheck className="size-4" />
                    Falta el comprobante
                </h2>
                <p className="text-sm text-muted-foreground">
                    La mercadería ya está en el stock con los precios del
                    pedido. Cuando tengas el comprobante, complétalo acá: el
                    costo queda con lo que dice el papel.
                </p>
            </div>

            <div className="grid gap-1.5">
                <Label>¿Qué comprobante es?</Label>
                <div className="flex flex-wrap gap-2">
                    {tipos.map((opcion) => (
                        <button
                            key={opcion.value}
                            type="button"
                            aria-pressed={
                                data.tipo_comprobante === opcion.value
                            }
                            onClick={() =>
                                setData('tipo_comprobante', opcion.value)
                            }
                            className={cn(
                                'min-h-11 rounded-full border bg-background px-4 text-sm font-medium transition-colors',
                                data.tipo_comprobante === opcion.value
                                    ? 'border-primary bg-primary text-primary-foreground'
                                    : 'hover:bg-accent',
                            )}
                        >
                            {opcion.label}
                        </button>
                    ))}
                </div>
                <InputError message={errors.tipo_comprobante} />
            </div>

            <div className="grid gap-3 sm:grid-cols-3">
                <div className="grid gap-1.5">
                    <Label htmlFor="completar-numero">
                        Serie y número
                        {!tipo?.exige_numero && ' (opcional)'}
                    </Label>
                    <Input
                        id="completar-numero"
                        className="h-11 bg-background uppercase"
                        value={data.serie_numero}
                        onChange={(evento) =>
                            setData('serie_numero', evento.target.value)
                        }
                        placeholder="F001-00123"
                    />
                    <InputError message={errors.serie_numero} />
                </div>
                <div className="grid gap-1.5">
                    <Label htmlFor="completar-fecha">
                        Fecha del comprobante
                    </Label>
                    <Input
                        id="completar-fecha"
                        type="date"
                        max={hoy}
                        className="h-11 bg-background"
                        value={data.fecha_emision}
                        onChange={(evento) =>
                            setData('fecha_emision', evento.target.value)
                        }
                    />
                    <InputError message={errors.fecha_emision} />
                </div>
                <div className="grid gap-1.5">
                    <Label htmlFor="completar-total">
                        Total del comprobante
                    </Label>
                    <Input
                        id="completar-total"
                        inputMode="decimal"
                        className="h-11 bg-background text-lg tabular-nums"
                        value={data.total_comprobante}
                        onChange={(evento) =>
                            setData(
                                'total_comprobante',
                                evento.target.value.replace(/[^0-9.]/g, ''),
                            )
                        }
                    />
                    <InputError message={errors.total_comprobante} />
                </div>
            </div>

            {tipoCambio !== null && (
                <div className="grid gap-1.5">
                    <Label htmlFor="completar-tipo-cambio">
                        Tipo de cambio del comprobante
                    </Label>
                    <Input
                        id="completar-tipo-cambio"
                        inputMode="decimal"
                        className="h-11 w-32 bg-background tabular-nums"
                        value={data.tipo_cambio}
                        onChange={(evento) =>
                            setData(
                                'tipo_cambio',
                                evento.target.value.replace(/[^0-9.]/g, ''),
                            )
                        }
                    />
                    <InputError message={errores.tipo_cambio} />
                </div>
            )}

            <div className="grid gap-1.5">
                <Label>Foto o PDF del comprobante</Label>
                <CampoArchivo
                    acepta="imagen-pdf"
                    archivos={data.comprobante ? [data.comprobante] : []}
                    onArchivos={(archivos) =>
                        setData('comprobante', archivos[0] ?? null)
                    }
                    progreso={progress?.percentage ?? null}
                    error={errors.comprobante}
                    onPreparando={setPreparando}
                />
            </div>

            <InputError message={errores.vale_compra_id ?? errores.descuento} />

            <Button
                type="submit"
                className="h-12 self-start"
                disabled={processing || preparando || data.comprobante === null}
            >
                {processing && <Spinner />}
                Completar el comprobante
            </Button>
        </form>
    );
}
