import { useForm } from '@inertiajs/react';
import { update } from '@/actions/App/Http/Controllers/ParametroCostoController';
import { Button } from '@/components/ui/button';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import type { ParametroFlota } from '@/types/fleet';

type FormData = {
    tamano_flota: string;
    dias_ano: string;
    dias_mantenimiento: string;
    dias_certificaciones: string;
    dias_sincronizacion: string;
    igv_pct: string;
    margen_pct_default: string;
};

/**
 * Los porcentajes del negocio (IGV, margen sugerido) y los supuestos de la
 * flota que usan las calculadoras de apoyo: cuántas unidades y cuántos días
 * del año quedan realmente disponibles. Cambiar la flota no mueve ninguna
 * tasa del tarifario; solo lo que las calculadoras sugieren.
 *
 * Los porcentajes se editan en enteros (18, no 0.18) y se convierten al
 * enviar: nadie escribe tasas en decimales.
 */
export function FlotaForm({ flota }: { flota: ParametroFlota }) {
    const { data, setData, put, transform, processing, errors } =
        useForm<FormData>({
            tamano_flota: flota.tamano_flota.toString(),
            dias_ano: flota.dias_ano.toString(),
            dias_mantenimiento: flota.dias_mantenimiento.toString(),
            dias_certificaciones: flota.dias_certificaciones.toString(),
            dias_sincronizacion: flota.dias_sincronizacion.toString(),
            igv_pct: (flota.igv_pct * 100).toString(),
            margen_pct_default: (flota.margen_pct_default * 100).toString(),
        });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        transform((datos) => ({
            ...datos,
            igv_pct: (Number(datos.igv_pct) / 100).toString(),
            margen_pct_default: (
                Number(datos.margen_pct_default) / 100
            ).toString(),
        }));

        put(update().url, { preserveScroll: true });
    };

    // El divisor de toda la estructura: se muestra mientras se editan los días
    // perdidos porque es el número que mueve todas las tasas fijas a la vez.
    const diasDisponibles =
        Number(data.dias_ano) -
        Number(data.dias_mantenimiento) -
        Number(data.dias_certificaciones) -
        Number(data.dias_sincronizacion);

    return (
        <form
            onSubmit={submit}
            className="flex flex-col gap-4 rounded-xl border border-border bg-card p-5"
        >
            <div>
                <h2 className="text-sm font-semibold text-foreground">
                    Supuestos de las calculadoras
                </h2>
                <p className="text-xs text-muted-foreground">
                    Un camión no factura los 365 días: las calculadoras reparten
                    los montos anuales sobre los días disponibles de la flota.
                    Cambiarlos solo mueve lo que sugieren, no las tasas.
                </p>
            </div>

            <div className="grid gap-4 sm:grid-cols-3">
                <Field label="Unidades de la flota" error={errors.tamano_flota}>
                    {(id) => (
                        <Input
                            id={id}
                            type="number"
                            min={1}
                            value={data.tamano_flota}
                            onChange={(e) =>
                                setData('tamano_flota', e.target.value)
                            }
                        />
                    )}
                </Field>
                <Field label="Días del año" error={errors.dias_ano}>
                    {(id) => (
                        <Input
                            id={id}
                            type="number"
                            step="0.01"
                            value={data.dias_ano}
                            onChange={(e) =>
                                setData('dias_ano', e.target.value)
                            }
                        />
                    )}
                </Field>
                <Field
                    label="Días en mantenimiento"
                    error={errors.dias_mantenimiento}
                >
                    {(id) => (
                        <Input
                            id={id}
                            type="number"
                            step="0.01"
                            value={data.dias_mantenimiento}
                            onChange={(e) =>
                                setData('dias_mantenimiento', e.target.value)
                            }
                        />
                    )}
                </Field>
                <Field
                    label="Días en certificaciones"
                    error={errors.dias_certificaciones}
                >
                    {(id) => (
                        <Input
                            id={id}
                            type="number"
                            step="0.01"
                            value={data.dias_certificaciones}
                            onChange={(e) =>
                                setData('dias_certificaciones', e.target.value)
                            }
                        />
                    )}
                </Field>
                <Field
                    label="Días por sincronización"
                    error={errors.dias_sincronizacion}
                    ayuda="Esperas de retorno entre viaje y viaje."
                >
                    {(id) => (
                        <Input
                            id={id}
                            type="number"
                            step="0.01"
                            value={data.dias_sincronizacion}
                            onChange={(e) =>
                                setData('dias_sincronizacion', e.target.value)
                            }
                        />
                    )}
                </Field>
                <div className="grid content-start gap-1.5">
                    <Label>Días disponibles</Label>
                    <p className="flex h-9 items-center font-mono text-sm text-foreground tabular-nums">
                        {Number.isFinite(diasDisponibles)
                            ? diasDisponibles.toFixed(2)
                            : '—'}
                    </p>
                    <p className="text-xs text-muted-foreground">
                        Días vendibles al año por unidad.
                    </p>
                </div>
            </div>

            <div className="grid gap-4 border-t border-border pt-4 sm:grid-cols-2">
                <Field label="IGV (%)" error={errors.igv_pct}>
                    {(id) => (
                        <Input
                            id={id}
                            type="number"
                            step="0.5"
                            value={data.igv_pct}
                            onChange={(e) => setData('igv_pct', e.target.value)}
                        />
                    )}
                </Field>
                <Field
                    label="Margen sugerido (%)"
                    error={errors.margen_pct_default}
                    ayuda="Sobre la tarifa. Se precarga en cada cotización."
                >
                    {(id) => (
                        <Input
                            id={id}
                            type="number"
                            step="0.5"
                            value={data.margen_pct_default}
                            onChange={(e) =>
                                setData('margen_pct_default', e.target.value)
                            }
                        />
                    )}
                </Field>
            </div>

            <div className="flex justify-end border-t border-border pt-4">
                <Button type="submit" disabled={processing}>
                    {processing && <Spinner />}
                    Guardar
                </Button>
            </div>
        </form>
    );
}
