import { Link, useForm, useHttp } from '@inertiajs/react';
import { useEffect, useId, useRef, useState } from 'react';
import cotizaciones, {
    previsualizar,
    store,
    update,
} from '@/actions/App/Http/Controllers/CotizacionController';
import { DesglosePanel } from '@/components/cotizaciones/desglose-panel';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import type {
    Cliente,
    Cotizacion,
    CostosRuta,
    DesgloseCotizacion,
    EnumOption,
    ParametroFlota,
    PuntoTraslado,
} from '@/types/fleet';

/** Lo que la lista de clientes trae para elegir en el formulario. */
type ClienteOpcion = Pick<Cliente, 'id' | 'alias' | 'razon_social' | 'ruc'>;
type PuntoOpcion = Pick<PuntoTraslado, 'id' | 'nombre' | 'direccion'>;

type Props = {
    mode: 'create' | 'edit';
    cotizacion?: Cotizacion;
    clientes: ClienteOpcion[];
    puntos: PuntoOpcion[];
    estados: EnumOption[];
    flota: ParametroFlota;
};

/**
 * Los conceptos propios del tramo, en el orden en que se cargan. Desglosarlos
 * no cambia la tarifa, pero es lo primero que se revisa cuando el cliente
 * pregunta por qué una ruta cuesta más que otra de los mismos kilómetros.
 */
const CONCEPTOS_RUTA: {
    campo: keyof CostosRuta;
    label: string;
    ayuda?: string;
}[] = [
    { campo: 'peajes', label: 'Peajes' },
    {
        campo: 'viaticos',
        label: 'Viáticos',
        ayuda: 'Se precarga con los días de ruta.',
    },
    { campo: 'alojamiento', label: 'Alojamiento' },
    { campo: 'cochera', label: 'Cochera' },
    { campo: 'carga_descarga', label: 'Carga y descarga' },
    { campo: 'otros_ruta', label: 'Otros' },
];

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
    peajes: string;
    viaticos: string;
    alojamiento: string;
    cochera: string;
    carga_descarga: string;
    otros_ruta: string;
    margen_pct: string;
    estado: string;
    notas: string;
};

const SIN_PUNTO = 'ninguno';

/** Espera entre tecla y tecla antes de pedirle el desglose al servidor. */
const ESPERA_PREVISUALIZACION = 400;

export function CotizacionForm({
    mode,
    cotizacion,
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
            cliente_nombre: cotizacion?.cliente_nombre ?? '',
            cliente_ruc: cotizacion?.cliente_ruc ?? '',
            punto_partida_id:
                cotizacion?.punto_partida_id?.toString() ?? SIN_PUNTO,
            punto_llegada_id:
                cotizacion?.punto_llegada_id?.toString() ?? SIN_PUNTO,
            origen: cotizacion?.origen ?? '',
            destino: cotizacion?.destino ?? '',
            material: cotizacion?.material ?? '',
            km: cotizacion?.km?.toString() ?? '',
            dias: cotizacion?.dias?.toString() ?? '',
            peajes: cotizacion?.peajes?.toString() ?? '0',
            viaticos: cotizacion?.viaticos?.toString() ?? '0',
            alojamiento: cotizacion?.alojamiento?.toString() ?? '0',
            cochera: cotizacion?.cochera?.toString() ?? '0',
            carga_descarga: cotizacion?.carga_descarga?.toString() ?? '0',
            otros_ruta: cotizacion?.otros_ruta?.toString() ?? '0',
            margen_pct: (
                (cotizacion?.margen_pct ?? flota.margen_pct_default) * 100
            ).toString(),
            estado: cotizacion?.estado ?? 'borrador',
            notas: cotizacion?.notas ?? '',
        });

    // El desglose se pide al servidor —y no se recalcula acá— para que la
    // fórmula viva en un solo lugar: la que cotiza es la que factura.
    const preview = useHttp<
        CostosRuta & { km: number; dias: number; margen_pct: number },
        DesgloseCotizacion
    >({
        km: 0,
        dias: 0,
        peajes: 0,
        viaticos: 0,
        alojamiento: 0,
        cochera: 0,
        carga_descarga: 0,
        otros_ruta: 0,
        margen_pct: 0,
    });

    const km = Number(data.km);
    const dias = Number(data.dias);
    const margenPct = Number(data.margen_pct) / 100;
    const rutaCompleta = km > 0 && dias > 0;

    const costosRuta: CostosRuta = {
        peajes: Number(data.peajes) || 0,
        viaticos: Number(data.viaticos) || 0,
        alojamiento: Number(data.alojamiento) || 0,
        cochera: Number(data.cochera) || 0,
        carga_descarga: Number(data.carga_descarga) || 0,
        otros_ruta: Number(data.otros_ruta) || 0,
    };

    const { setData: setPreviewData, post: pedirDesglose } = preview;
    // Se compara serializado y no por referencia: el objeto se arma en cada
    // render y dispararía el efecto en cada tecla de cualquier campo.
    const costosRutaSerializados = JSON.stringify(costosRuta);

    useEffect(() => {
        if (!rutaCompleta) {
            return;
        }

        const temporizador = setTimeout(() => {
            setPreviewData({
                km,
                dias,
                margen_pct: Number.isFinite(margenPct) ? margenPct : 0,
                ...(JSON.parse(costosRutaSerializados) as CostosRuta),
            });

            pedirDesglose(previsualizar().url);
        }, ESPERA_PREVISUALIZACION);

        return () => clearTimeout(temporizador);
    }, [
        km,
        dias,
        margenPct,
        costosRutaSerializados,
        rutaCompleta,
        setPreviewData,
        pedirDesglose,
    ]);

    // Los viáticos siguen a los días mientras nadie los toque a mano: es la
    // regla de la casa (tantos soles por día), pero hay rutas donde se pactan
    // distinto y ahí manda lo que se escribió.
    const viaticosTocados = useRef(cotizacion !== undefined);

    const cambiarDias = (valor: string) => {
        setData((datos) => ({
            ...datos,
            dias: valor,
            viaticos: viaticosTocados.current
                ? datos.viaticos
                : ((Number(valor) || 0) * flota.viatico_dia).toFixed(2),
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
                        Los días son los que la unidad queda tomada, incluyendo
                        esperas de carga y el retorno.
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
                    <Field label="Kilómetros" error={errors.km} required>
                        {(id) => (
                            <Input
                                id={id}
                                type="number"
                                inputMode="numeric"
                                min={1}
                                value={data.km}
                                onChange={(e) => setData('km', e.target.value)}
                                placeholder="1264"
                            />
                        )}
                    </Field>
                    <Field
                        label="Días de ruta"
                        error={errors.dias}
                        required
                        ayuda="Admite medios días: 5.5 es válido."
                    >
                        {(id) => (
                            <Input
                                id={id}
                                type="number"
                                inputMode="decimal"
                                step="0.5"
                                min={0.5}
                                value={data.dias}
                                onChange={(e) => cambiarDias(e.target.value)}
                                placeholder="5.5"
                            />
                        )}
                    </Field>
                </div>
            </section>

            <section className="rounded-xl border border-border bg-card p-5">
                <div className="mb-4">
                    <h2 className="text-sm font-semibold text-foreground">
                        Costos de la ruta
                    </h2>
                    <p className="text-xs text-muted-foreground">
                        Lo que se paga en este tramo y no en otro. Todos son
                        costos directos del viaje.
                    </p>
                </div>

                <div className="grid gap-4 sm:grid-cols-3">
                    {CONCEPTOS_RUTA.map((concepto) => (
                        <Field
                            key={concepto.campo}
                            label={`${concepto.label} (S/.)`}
                            error={errors[concepto.campo]}
                            required
                            ayuda={concepto.ayuda}
                        >
                            {(id) => (
                                <Input
                                    id={id}
                                    type="number"
                                    inputMode="decimal"
                                    step="0.01"
                                    min={0}
                                    value={data[concepto.campo]}
                                    onChange={(e) => {
                                        if (concepto.campo === 'viaticos') {
                                            viaticosTocados.current = true;
                                        }

                                        setData(concepto.campo, e.target.value);
                                    }}
                                />
                            )}
                        </Field>
                    ))}
                </div>
            </section>

            <DesglosePanel
                desglose={rutaCompleta ? preview.response : null}
                km={km}
                dias={dias}
                igvPct={flota.igv_pct}
                calculando={preview.processing}
            />

            <section className="rounded-xl border border-border bg-card p-5">
                <div className="mb-4">
                    <h2 className="text-sm font-semibold text-foreground">
                        Condiciones
                    </h2>
                </div>

                <div className="grid gap-4 sm:grid-cols-2">
                    <Field
                        label="Margen (%)"
                        error={errors.margen_pct}
                        required
                        ayuda={`Sugerido: ${(flota.margen_pct_default * 100).toFixed(0)}%.`}
                    >
                        {(id) => (
                            <Input
                                id={id}
                                type="number"
                                inputMode="decimal"
                                step="0.5"
                                min={0}
                                max={100}
                                value={data.margen_pct}
                                onChange={(e) =>
                                    setData('margen_pct', e.target.value)
                                }
                            />
                        )}
                    </Field>
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

function Field({
    label,
    error,
    required,
    ayuda,
    children,
}: {
    label: string;
    error?: string;
    required?: boolean;
    ayuda?: string;
    children: (id: string) => React.ReactNode;
}) {
    const id = useId();

    return (
        <div className="grid gap-1.5">
            <Label htmlFor={id}>
                {label}
                {required && <span className="text-destructive"> *</span>}
            </Label>
            {children(id)}
            {ayuda && <p className="text-xs text-muted-foreground">{ayuda}</p>}
            <InputError message={error} />
        </div>
    );
}
