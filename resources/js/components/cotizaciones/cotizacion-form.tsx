import { Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import cotizaciones, {
    store,
    update,
} from '@/actions/App/Http/Controllers/CotizacionController';
import { EntradaRuta, HojaTarifa } from '@/components/cotizaciones/hoja-tarifa';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import { calcularTarifa, formatearMonto } from '@/lib/tarifa';
import { cn } from '@/lib/utils';
import type {
    Cliente,
    Cotizacion,
    EnumOption,
    LineaTarifa,
    PuntoTraslado,
} from '@/types/fleet';

/** Lo que la lista de clientes trae para elegir en el formulario. */
type ClienteOpcion = Pick<
    Cliente,
    'id' | 'alias' | 'razon_social' | 'ruc' | 'direccion'
>;
type PuntoOpcion = Pick<PuntoTraslado, 'id' | 'nombre' | 'direccion'>;

/** Lo que llega de la hoja rápida para no volver a tipearlo. */
export type BorradorCotizacion = Partial<
    Record<
        | 'cliente_nombre'
        | 'destino'
        | 'material'
        | 'km'
        | 'dias'
        | 'km_retorno'
        | 'dias_retorno'
        | 'margen_pct'
        | 'precio_unitario',
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
    /** `VIAJE` → «Viaje», `TN` → «Tonelada». */
    unidades: Record<string, string>;
    flota: { margen_pct_default: number; igv_pct: number };
};

type FormData = {
    fecha: string;
    valido_hasta: string;
    cliente_id: string;
    cliente_nombre: string;
    cliente_ruc: string;
    cliente_direccion: string;
    punto_partida_id: string;
    punto_llegada_id: string;
    origen: string;
    destino: string;
    material: string;
    referencia: string;
    km: string;
    dias: string;
    retorno: boolean;
    km_retorno: string;
    dias_retorno: string;
    margen_pct: string;
    cantidad: string;
    unidad: string;
    /** Vacío cobra la tarifa calculada repartida entre la cantidad. */
    precio_unitario: string;
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
    unidades,
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
            cliente_direccion: cotizacion?.cliente_direccion ?? '',
            punto_partida_id:
                cotizacion?.punto_partida_id?.toString() ?? SIN_PUNTO,
            punto_llegada_id:
                cotizacion?.punto_llegada_id?.toString() ?? SIN_PUNTO,
            origen: cotizacion?.origen ?? '',
            destino: cotizacion?.destino ?? borrador.destino ?? '',
            material: cotizacion?.material ?? borrador.material ?? '',
            referencia: cotizacion?.referencia ?? '',
            km: cotizacion?.km?.toString() ?? borrador.km ?? '',
            dias: cotizacion?.dias?.toString() ?? borrador.dias ?? '',
            retorno: cotizacion
                ? cotizacion.km_retorno > 0 || cotizacion.dias_retorno > 0
                : Number(borrador.km_retorno) > 0 ||
                  Number(borrador.dias_retorno) > 0,
            km_retorno:
                cotizacion?.km_retorno?.toString() ?? borrador.km_retorno ?? '',
            dias_retorno:
                cotizacion?.dias_retorno?.toString() ??
                borrador.dias_retorno ??
                '',
            margen_pct: (
                (cotizacion?.margen_pct ??
                    (borrador.margen_pct === undefined
                        ? flota.margen_pct_default
                        : Number(borrador.margen_pct))) * 100
            ).toString(),
            cantidad: cotizacion?.cantidad?.toString() ?? '1',
            unidad: cotizacion?.unidad ?? 'VIAJE',
            // Una cotización que cobraba la tarifa calculada sigue sin precio
            // fijado: corregirle los km tiene que moverle el precio.
            precio_unitario:
                cotizacion && cotizacion.rebaja !== 0
                    ? cotizacion.precio_unitario.toString()
                    : (borrador.precio_unitario ?? ''),
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
    const kmRetorno = data.retorno ? Number(data.km_retorno) || 0 : 0;
    const diasRetorno = data.retorno ? Number(data.dias_retorno) || 0 : 0;
    const margenPct = (Number(data.margen_pct) || 0) / 100;
    const cantidad = Number(data.cantidad) || 0;
    const resultado =
        km > 0 && dias > 0 && margenPct >= 0 && margenPct < 1
            ? calcularTarifa(
                  lineas,
                  {
                      km,
                      dias,
                      kmRetorno,
                      diasRetorno,
                      margenPct,
                      cantidad,
                      precioUnitario: Number(data.precio_unitario) || 0,
                  },
                  flota.igv_pct,
              )
            : null;

    /**
     * Pasar de «1 viaje a 9,000» a «30 TN» no debe cambiar lo que se cobra:
     * con un precio fijado, el unitario se reparte para que el total quede.
     */
    const cambiarCantidad = (valor: string) => {
        const nueva = Number(valor) || 0;

        setData((actual) => ({
            ...actual,
            cantidad: valor,
            precio_unitario:
                actual.precio_unitario !== '' && resultado && nueva > 0
                    ? (
                          Math.round((resultado.subtotal / nueva) * 100) / 100
                      ).toString()
                    : actual.precio_unitario,
        }));
    };

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
            // Desmarcar el retorno lo borra aunque los campos tengan algo.
            km_retorno: datos.retorno ? datos.km_retorno : '0',
            dias_retorno: datos.retorno ? datos.dias_retorno : '0',
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
            cliente_direccion: elegido?.direccion ?? actual.cliente_direccion,
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
                    <Field label="Dirección" error={errors.cliente_direccion}>
                        {(id) => (
                            <Input
                                id={id}
                                value={data.cliente_direccion}
                                onChange={(e) =>
                                    setData('cliente_direccion', e.target.value)
                                }
                                placeholder="Car. Juliaca-Puno Km. 11"
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
                            : 'Los días son los que la unidad queda tomada, incluyendo esperas de carga.'}
                    </p>
                    <label className="mt-3 flex items-center gap-2 text-sm">
                        <Checkbox
                            checked={data.retorno}
                            onCheckedChange={(marcado) => {
                                setData((actual) => ({
                                    ...actual,
                                    retorno: marcado === true,
                                    // Lo más común es volver por el mismo camino.
                                    km_retorno:
                                        marcado === true &&
                                        actual.km_retorno === ''
                                            ? actual.km
                                            : actual.km_retorno,
                                }));
                            }}
                        />
                        La unidad regresa vacía: cobrar los km y días de la
                        vuelta
                    </label>
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
                                <EntradaRuta
                                    etiqueta="Días de ruta"
                                    decimal
                                    valor={data.dias}
                                    onChange={(valor) => setData('dias', valor)}
                                    retorno={
                                        data.retorno
                                            ? data.dias_retorno
                                            : undefined
                                    }
                                    onChangeRetorno={(valor) =>
                                        setData('dias_retorno', valor)
                                    }
                                    placeholder="9"
                                />
                            ),
                            km: (
                                <EntradaRuta
                                    etiqueta="Kilómetros"
                                    valor={data.km}
                                    onChange={(valor) => setData('km', valor)}
                                    retorno={
                                        data.retorno
                                            ? data.km_retorno
                                            : undefined
                                    }
                                    onChangeRetorno={(valor) =>
                                        setData('km_retorno', valor)
                                    }
                                    placeholder="1275"
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
                <InputError message={errors.dias_retorno} />
                <InputError message={errors.km_retorno} />
                <InputError message={errors.margen_pct} />
            </section>

            <section className="rounded-xl border border-border bg-card p-5">
                <div className="mb-4">
                    <h2 className="text-sm font-semibold text-foreground">
                        Precio al cliente
                    </h2>
                    <p className="text-xs text-muted-foreground">
                        Lo que sale en la proforma. Sin precio unitario se cobra
                        la tarifa calculada; con uno, la rebaja queda acá y no
                        se imprime.
                    </p>
                </div>

                <div className="grid gap-4 sm:grid-cols-3">
                    <Field label="Cantidad" error={errors.cantidad} required>
                        {(id) => (
                            <Input
                                id={id}
                                type="number"
                                inputMode="decimal"
                                step="0.01"
                                min={0.01}
                                value={data.cantidad}
                                onChange={(e) =>
                                    cambiarCantidad(e.target.value)
                                }
                                className="text-right font-mono tabular-nums"
                            />
                        )}
                    </Field>
                    <Field label="Unidad" error={errors.unidad} required>
                        {(id) => (
                            <Select
                                value={data.unidad}
                                onValueChange={(value) =>
                                    setData('unidad', value)
                                }
                            >
                                <SelectTrigger id={id}>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {Object.entries(unidades).map(
                                        ([valor, etiqueta]) => (
                                            <SelectItem
                                                key={valor}
                                                value={valor}
                                            >
                                                {etiqueta}
                                            </SelectItem>
                                        ),
                                    )}
                                </SelectContent>
                            </Select>
                        )}
                    </Field>
                    <Field
                        label="Precio unitario (S/, sin IGV)"
                        error={errors.precio_unitario}
                    >
                        {(id) => (
                            <Input
                                id={id}
                                type="number"
                                inputMode="decimal"
                                step="0.01"
                                min={0}
                                value={data.precio_unitario}
                                onChange={(e) =>
                                    setData('precio_unitario', e.target.value)
                                }
                                placeholder={
                                    resultado && cantidad > 0
                                        ? formatearMonto(
                                              resultado.tarifa_calculada /
                                                  cantidad,
                                          )
                                        : ''
                                }
                                className="text-right font-mono tabular-nums"
                            />
                        )}
                    </Field>
                </div>

                {resultado && (
                    <dl className="mt-4 grid gap-x-6 gap-y-2 rounded-lg bg-muted/50 p-3 text-sm sm:grid-cols-2">
                        <Resumen
                            etiqueta="En la proforma"
                            valor={`${data.cantidad} ${data.unidad === 'TN' ? 'TN' : 'viaje(s)'} × S/ ${formatearMonto(resultado.precio_unitario)} = S/ ${formatearMonto(resultado.subtotal)} + IGV`}
                        />
                        <Resumen
                            etiqueta="Total con IGV"
                            valor={`S/ ${formatearMonto(resultado.total)}`}
                        />
                        <Resumen
                            etiqueta="Rebaja sobre la calculada"
                            valor={`S/ ${formatearMonto(resultado.tarifa_calculada - resultado.subtotal)}`}
                            tenue
                        />
                        <Resumen
                            etiqueta="Margen real"
                            valor={
                                resultado.subtotal > 0
                                    ? `S/ ${formatearMonto(resultado.margen)} (${((resultado.margen / resultado.subtotal) * 100).toFixed(1)} %)`
                                    : '—'
                            }
                            tenue
                            alerta={resultado.margen < 0}
                        />
                    </dl>
                )}
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
                    <Field label="Referencia" error={errors.referencia}>
                        {(id) => (
                            <Input
                                id={id}
                                value={data.referencia}
                                onChange={(e) =>
                                    setData('referencia', e.target.value)
                                }
                                placeholder="Su solicitud del 30/09"
                            />
                        )}
                    </Field>
                    <div className="sm:col-span-2">
                        <Field label="Observaciones" error={errors.notas}>
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

function Resumen({
    etiqueta,
    valor,
    tenue,
    alerta,
}: {
    etiqueta: string;
    valor: string;
    tenue?: boolean;
    alerta?: boolean;
}) {
    return (
        <div>
            <dt className="text-xs text-muted-foreground">{etiqueta}</dt>
            <dd
                className={cn(
                    'font-mono tabular-nums',
                    tenue && 'text-muted-foreground',
                    alerta && 'font-semibold text-destructive',
                )}
            >
                {valor}
            </dd>
        </div>
    );
}
