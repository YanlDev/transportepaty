import { Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import cotizaciones, {
    store,
    update,
} from '@/actions/App/Http/Controllers/CotizacionController';
import { HojaTarifa } from '@/components/cotizaciones/hoja-tarifa';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { calcularTarifa } from '@/lib/tarifa';
import type {
    Cliente,
    Cotizacion,
    EnumOption,
    LineaTarifa,
    PuntoTraslado,
} from '@/types/fleet';

/** Lo que la lista de clientes trae para elegir en el formulario. */
type ClienteOpcion = Pick<Cliente, 'id' | 'alias' | 'razon_social' | 'ruc'>;
type PuntoOpcion = Pick<PuntoTraslado, 'id' | 'nombre' | 'direccion'>;

/** Lo que llega de la hoja rápida para no volver a tipearlo. */
export type BorradorCotizacion = Partial<
    Record<
        | 'cliente_nombre'
        | 'destino'
        | 'material'
        | 'km'
        | 'dias'
        | 'margen_pct',
        string
    >
>;

type Props = {
    mode: 'create' | 'edit';
    cotizacion?: Cotizacion;
    /** El tarifario vigente; al editar se usa el que la cotización tenía. */
    lineas?: LineaTarifa[];
    borrador?: BorradorCotizacion;
    clientes: ClienteOpcion[];
    puntos: PuntoOpcion[];
    estados: EnumOption[];
    flota: { margen_pct_default: number; igv_pct: number };
};

type FormData = {
    fecha: string;
    valido_hasta: string;
    cliente_id: string;
    cliente_nombre: string;
    cliente_ruc: string;
    punto_partida_id: string;
    punto_llegada_id: string;
    origen: string;
    destino: string;
    material: string;
    km: string;
    dias: string;
    margen_pct: string;
    estado: string;
    notas: string;
};

const SIN_PUNTO = 'ninguno';

export function CotizacionForm({
    mode,
    cotizacion,
    lineas: lineasVigentes = [],
    borrador = {},
    clientes,
    puntos,
    estados,
    flota,
}: Props) {
    // Se calculan una sola vez al montar: leer el reloj en cada render deja
    // valores que cambian solos si el componente se vuelve a dibujar.
    const [fechas] = useState(() => {
        const hoy = new Date();

        return {
            hoy: hoy.toISOString().slice(0, 10),
            enQuinceDias: new Date(hoy.getTime() + 15 * 86_400_000)
                .toISOString()
                .slice(0, 10),
        };
    });

    const { data, setData, post, put, transform, processing, errors } =
        useForm<FormData>({
            fecha: cotizacion?.fecha ?? fechas.hoy,
            valido_hasta: cotizacion?.valido_hasta ?? fechas.enQuinceDias,
            cliente_id: cotizacion?.cliente_id?.toString() ?? '',
            cliente_nombre:
                cotizacion?.cliente_nombre ?? borrador.cliente_nombre ?? '',
            cliente_ruc: cotizacion?.cliente_ruc ?? '',
            punto_partida_id:
                cotizacion?.punto_partida_id?.toString() ?? SIN_PUNTO,
            punto_llegada_id:
                cotizacion?.punto_llegada_id?.toString() ?? SIN_PUNTO,
            origen: cotizacion?.origen ?? '',
            destino: cotizacion?.destino ?? borrador.destino ?? '',
            material: cotizacion?.material ?? borrador.material ?? '',
            km: cotizacion?.km?.toString() ?? borrador.km ?? '',
            dias: cotizacion?.dias?.toString() ?? borrador.dias ?? '',
            margen_pct: (
                (cotizacion?.margen_pct ??
                    (borrador.margen_pct === undefined
                        ? flota.margen_pct_default
                        : Number(borrador.margen_pct))) * 100
            ).toString(),
            estado: cotizacion?.estado ?? 'borrador',
            notas: cotizacion?.notas ?? '',
        });

    // Una cotización ya emitida se recalcula con las tasas que tenía, como
    // hace el servidor al guardarla: corregir un kilometraje no le cambia el
    // precio porque mientras tanto se haya tocado el tarifario.
    const lineas: LineaTarifa[] = cotizacion
        ? cotizacion.desglose.componentes
        : lineasVigentes;

    const km = Number(data.km) || 0;
    const dias = Number(data.dias) || 0;
    const margenPct = (Number(data.margen_pct) || 0) / 100;
    const resultado =
        km > 0 && dias > 0 && margenPct >= 0 && margenPct < 1
            ? calcularTarifa(lineas, { km, dias, margenPct }, flota.igv_pct)
            : null;

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        // El centinela de «sin punto del catálogo» no es un id: se manda
        // vacío, que el middleware de Laravel convierte en null.
        transform((datos) => ({
            ...datos,
            punto_partida_id:
                datos.punto_partida_id === SIN_PUNTO
                    ? ''
                    : datos.punto_partida_id,
            punto_llegada_id:
                datos.punto_llegada_id === SIN_PUNTO
                    ? ''
                    : datos.punto_llegada_id,
            margen_pct: (Number(datos.margen_pct) / 100).toString(),
        }));

        if (mode === 'create') {
            post(store().url);
        } else if (cotizacion) {
            put(update(cotizacion.id).url);
        }
    };

    /** Al elegir un cliente del padrón se copian su nombre y su RUC. */
    const elegirCliente = (id: string) => {
        const elegido = clientes.find(
            (cliente) => cliente.id.toString() === id,
        );

        setData((actual) => ({
            ...actual,
            cliente_id: id,
            cliente_nombre: elegido?.razon_social ?? actual.cliente_nombre,
            cliente_ruc: elegido?.ruc ?? actual.cliente_ruc,
        }));
    };

    /** Elegir un punto del catálogo llena la dirección de ese extremo. */
    const elegirPunto = (extremo: 'partida' | 'llegada', id: string) => {
        const punto = puntos.find((p) => p.id.toString() === id);
        const campoTexto = extremo === 'partida' ? 'origen' : 'destino';

        setData((actual) => ({
            ...actual,
            [extremo === 'partida' ? 'punto_partida_id' : 'punto_llegada_id']:
                id,
            [campoTexto]: punto?.direccion ?? actual[campoTexto],
        }));
    };

    return (
        <form onSubmit={submit} className="flex flex-col gap-6">
            <section className="rounded-xl border border-border bg-card p-5">
                <div className="mb-4">
                    <h2 className="text-sm font-semibold text-foreground">
                        Cliente
                    </h2>
                    <p className="text-xs text-muted-foreground">
                        Si todavía no está en el padrón, alcanza con escribir su
                        nombre.
                    </p>
                </div>

                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label="Del padrón" error={errors.cliente_id}>
                        {(id) => (
                            <Select
                                value={data.cliente_id}
                                onValueChange={elegirCliente}
                            >
                                <SelectTrigger id={id}>
                                    <SelectValue placeholder="Elegir cliente..." />
                                </SelectTrigger>
                                <SelectContent>
                                    {clientes.map((cliente) => (
                                        <SelectItem
                                            key={cliente.id}
                                            value={cliente.id.toString()}
                                        >
                                            {cliente.alias}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        )}
                    </Field>
                    <Field
                        label="Nombre o razón social"
                        error={errors.cliente_nombre}
                        required
                    >
                        {(id) => (
                            <Input
                                id={id}
                                value={data.cliente_nombre}
                                onChange={(e) =>
                                    setData(
                                        'cliente_nombre',
                                        e.target.value.toUpperCase(),
                                    )
                                }
                                placeholder="IMPORTACIONES MELMA S.A.C."
                            />
                        )}
                    </Field>
                    <Field label="RUC" error={errors.cliente_ruc}>
                        {(id) => (
                            <Input
                                id={id}
                                inputMode="numeric"
                                value={data.cliente_ruc}
                                onChange={(e) =>
                                    setData('cliente_ruc', e.target.value)
                                }
                                placeholder="20600812913"
                            />
                        )}
                    </Field>
                </div>
            </section>

            <section className="rounded-xl border border-border bg-card p-5">
                <div className="mb-4">
                    <h2 className="text-sm font-semibold text-foreground">
                        Ruta
                    </h2>
                    <p className="text-xs text-muted-foreground">
                        Los puntos del catálogo llenan la dirección; si no está,
                        alcanza con escribirla.
                    </p>
                </div>

                <div className="grid gap-4 sm:grid-cols-2">
                    <Field
                        label="Punto de partida"
                        error={errors.punto_partida_id}
                    >
                        {(id) => (
                            <Select
                                value={data.punto_partida_id}
                                onValueChange={(value) =>
                                    elegirPunto('partida', value)
                                }
                            >
                                <SelectTrigger id={id}>
                                    <SelectValue placeholder="Del catálogo..." />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={SIN_PUNTO}>
                                        Sin punto del catálogo
                                    </SelectItem>
                                    {puntos.map((punto) => (
                                        <SelectItem
                                            key={punto.id}
                                            value={punto.id.toString()}
                                        >
                                            {punto.nombre}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        )}
                    </Field>
                    <Field
                        label="Punto de llegada"
                        error={errors.punto_llegada_id}
                    >
                        {(id) => (
                            <Select
                                value={data.punto_llegada_id}
                                onValueChange={(value) =>
                                    elegirPunto('llegada', value)
                                }
                            >
                                <SelectTrigger id={id}>
                                    <SelectValue placeholder="Del catálogo..." />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={SIN_PUNTO}>
                                        Sin punto del catálogo
                                    </SelectItem>
                                    {puntos.map((punto) => (
                                        <SelectItem
                                            key={punto.id}
                                            value={punto.id.toString()}
                                        >
                                            {punto.nombre}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        )}
                    </Field>
                    <Field label="Origen" error={errors.origen} required>
                        {(id) => (
                            <Input
                                id={id}
                                value={data.origen}
                                onChange={(e) =>
                                    setData('origen', e.target.value)
                                }
                                placeholder="Juliaca"
                            />
                        )}
                    </Field>
                    <Field label="Destino" error={errors.destino} required>
                        {(id) => (
                            <Input
                                id={id}
                                value={data.destino}
                                onChange={(e) =>
                                    setData('destino', e.target.value)
                                }
                                placeholder="Arequipa"
                            />
                        )}
                    </Field>
                    <Field label="Material" error={errors.material}>
                        {(id) => (
                            <Input
                                id={id}
                                value={data.material}
                                onChange={(e) =>
                                    setData('material', e.target.value)
                                }
                                placeholder="Materiales varios"
                            />
                        )}
                    </Field>
                </div>
            </section>

            <section className="flex flex-col gap-3">
                <div>
                    <h2 className="text-sm font-semibold text-foreground">
                        Tarifa
                    </h2>
                    <p className="text-xs text-muted-foreground">
                        {cotizacion
                            ? 'Con las tasas con las que se emitió: cambiar el tarifario no mueve esta cotización.'
                            : 'Los días son los que la unidad queda tomada, incluyendo esperas de carga y el retorno.'}
                    </p>
                </div>

                <HojaTarifa
                    lineas={lineas}
                    igvPct={flota.igv_pct}
                    columnas={[
                        {
                            clave: 'ruta',
                            kmNumero: km,
                            resultado,
                            dias: (
                                <Input
                                    aria-label="Días de ruta"
                                    type="number"
                                    inputMode="decimal"
                                    step="0.5"
                                    min={0.5}
                                    value={data.dias}
                                    onChange={(e) =>
                                        setData('dias', e.target.value)
                                    }
                                    placeholder="9"
                                    className="h-8 bg-background text-right font-mono tabular-nums"
                                />
                            ),
                            km: (
                                <Input
                                    aria-label="Kilómetros"
                                    type="number"
                                    inputMode="numeric"
                                    min={1}
                                    value={data.km}
                                    onChange={(e) =>
                                        setData('km', e.target.value)
                                    }
                                    placeholder="1275"
                                    className="h-8 bg-background text-right font-mono tabular-nums"
                                />
                            ),
                        },
                    ]}
                    margen={
                        <div className="flex items-center justify-end gap-1">
                            <Input
                                aria-label="Margen de operación (%)"
                                type="number"
                                inputMode="decimal"
                                step="0.5"
                                min={0}
                                max={90}
                                value={data.margen_pct}
                                onChange={(e) =>
                                    setData('margen_pct', e.target.value)
                                }
                                className="h-7 w-16 text-right font-mono tabular-nums"
                            />
                            <span className="text-xs">%</span>
                        </div>
                    }
                />

                <InputError message={errors.dias} />
                <InputError message={errors.km} />
                <InputError message={errors.margen_pct} />
            </section>

            <section className="rounded-xl border border-border bg-card p-5">
                <div className="mb-4">
                    <h2 className="text-sm font-semibold text-foreground">
                        Condiciones
                    </h2>
                </div>

                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label="Estado" error={errors.estado} required>
                        {(id) => (
                            <Select
                                value={data.estado}
                                onValueChange={(value) =>
                                    setData('estado', value)
                                }
                            >
                                <SelectTrigger id={id}>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {estados.map((estado) => (
                                        <SelectItem
                                            key={estado.value}
                                            value={estado.value}
                                        >
                                            {estado.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        )}
                    </Field>
                    <Field label="Fecha" error={errors.fecha} required>
                        {(id) => (
                            <Input
                                id={id}
                                type="date"
                                value={data.fecha}
                                onChange={(e) =>
                                    setData('fecha', e.target.value)
                                }
                            />
                        )}
                    </Field>
                    <Field
                        label="Válido hasta"
                        error={errors.valido_hasta}
                        required
                    >
                        {(id) => (
                            <Input
                                id={id}
                                type="date"
                                value={data.valido_hasta}
                                onChange={(e) =>
                                    setData('valido_hasta', e.target.value)
                                }
                            />
                        )}
                    </Field>
                    <div className="sm:col-span-2">
                        <Field label="Notas" error={errors.notas}>
                            {(id) => (
                                <Textarea
                                    id={id}
                                    value={data.notas}
                                    onChange={(e) =>
                                        setData('notas', e.target.value)
                                    }
                                    placeholder="Condiciones particulares que van en la proforma..."
                                    rows={3}
                                />
                            )}
                        </Field>
                    </div>
                </div>
            </section>

            <div className="flex items-center justify-end gap-3 border-t pt-4">
                <Button asChild variant="outline" type="button">
                    <Link href={cotizaciones.index().url}>Cancelar</Link>
                </Button>
                <Button type="submit" disabled={processing}>
                    {processing && <Spinner />}
                    {mode === 'create' ? 'Crear cotización' : 'Guardar cambios'}
                </Button>
            </div>
        </form>
    );
}
