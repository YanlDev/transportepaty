import { useForm } from '@inertiajs/react';
import { AlertTriangle, Plus, Send, Trash2 } from 'lucide-react';
import { useId } from 'react';
import { store } from '@/actions/App/Http/Controllers/GuiaController';
import { UbigeoPicker } from '@/components/guias/ubigeo-picker';
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
import type { EnumOption } from '@/types/fleet';

type ClienteOption = EnumOption & { ruc: string; razon_social: string };
type VehiculoOption = EnumOption & { tuc: string | null };
type ConductorOption = EnumOption & {
    documento: string;
    licencia: string | null;
};
type PuntoOption = EnumOption & { ubigeo: string; direccion: string };

type Props = {
    serie: string;
    emisorConfigurado: boolean;
    clientes: ClienteOption[];
    tractos: VehiculoOption[];
    carretas: VehiculoOption[];
    conductores: ConductorOption[];
    puntosFrecuentes: PuntoOption[];
    tiposCarga: EnumOption[];
    motivosTraslado: EnumOption[];
};

type Punto = {
    ubigeo: string;
    direccion: string;
    nombre: string;
    /** Solo para mostrar el distrito elegido; no se envía. */
    etiqueta: string;
};

type FormData = {
    fecha_traslado: string;
    cliente_id: string;
    destinatario: string;
    destinatario_ruc: string;
    partida: Punto;
    llegada: Punto;
    tracto_id: string;
    carreta_id: string;
    conductor_id: string;
    peso: string;
    unidad_peso: string;
    tipo_carga: string;
    motivo_traslado: string;
    guias_remitente: { numero: string; ruc: string }[];
    observaciones: string;
};

const PUNTO_VACIO: Punto = {
    ubigeo: '',
    direccion: '',
    nombre: '',
    etiqueta: '',
};

export function GuiaForm({
    serie,
    emisorConfigurado,
    clientes,
    tractos,
    carretas,
    conductores,
    puntosFrecuentes,
    tiposCarga,
    motivosTraslado,
}: Props) {
    const { data, setData, post, processing, errors } = useForm<FormData>({
        fecha_traslado: new Date().toISOString().slice(0, 10),
        cliente_id: '',
        destinatario: '',
        destinatario_ruc: '',
        partida: { ...PUNTO_VACIO },
        llegada: { ...PUNTO_VACIO },
        tracto_id: '',
        carreta_id: '',
        conductor_id: '',
        peso: '',
        unidad_peso: 'KGM',
        tipo_carga: 'concentrado',
        motivo_traslado: '04',
        guias_remitente: [{ numero: '', ruc: '' }],
        observaciones: '',
    });

    const cliente = clientes.find((c) => c.value === data.cliente_id);
    const tracto = tractos.find((t) => t.value === data.tracto_id);
    const carreta = carretas.find((c) => c.value === data.carreta_id);
    const conductor = conductores.find((c) => c.value === data.conductor_id);

    /** Lo que SUNAT rechazaría y el formulario puede avisar antes. */
    const avisos = [
        tracto && !tracto.tuc
            ? `El tracto ${tracto.label} no tiene TUC cargado.`
            : null,
        carreta && !carreta.tuc
            ? `La carreta ${carreta.label} no tiene TUC cargado.`
            : null,
        conductor && !conductor.licencia
            ? `${conductor.label} no tiene licencia registrada.`
            : null,
        emisorConfigurado
            ? null
            : 'Faltan los datos del emisor en la configuración (RUC, razón social, registro MTC).',
    ].filter(Boolean) as string[];

    const usarPunto = (lado: 'partida' | 'llegada', punto: PuntoOption) => {
        setData(lado, {
            ubigeo: punto.ubigeo,
            direccion: punto.direccion,
            nombre: punto.label,
            etiqueta: `${punto.label} · ${punto.ubigeo}`,
        });
    };

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        post(store().url);
    };

    return (
        <form onSubmit={submit} className="flex flex-col gap-6">
            {avisos.length > 0 && (
                <div className="rounded-lg border border-amber-500/40 bg-amber-50 p-3 dark:bg-amber-950/30">
                    <p className="mb-1 flex items-center gap-1.5 text-sm font-medium text-amber-900 dark:text-amber-200">
                        <AlertTriangle className="size-4" />
                        Revisa antes de emitir
                    </p>
                    <ul className="ml-6 list-disc space-y-0.5 text-xs text-amber-900/90 dark:text-amber-200/90">
                        {avisos.map((aviso) => (
                            <li key={aviso}>{aviso}</li>
                        ))}
                    </ul>
                </div>
            )}

            <Section
                title="Traslado"
                description={`La guía se numera sola en la serie ${serie}.`}
            >
                <Campo label="Fecha de traslado" error={errors.fecha_traslado}>
                    {(id) => (
                        <Input
                            id={id}
                            type="date"
                            value={data.fecha_traslado}
                            onChange={(e) =>
                                setData('fecha_traslado', e.target.value)
                            }
                        />
                    )}
                </Campo>

                <Campo label="Motivo" error={errors.motivo_traslado}>
                    {(id) => (
                        <Selector
                            id={id}
                            value={data.motivo_traslado}
                            onChange={(v) => setData('motivo_traslado', v)}
                            options={motivosTraslado}
                        />
                    )}
                </Campo>

                <Campo label="Tipo de carga" error={errors.tipo_carga}>
                    {(id) => (
                        <Selector
                            id={id}
                            value={data.tipo_carga}
                            onChange={(v) => setData('tipo_carga', v)}
                            options={tiposCarga}
                        />
                    )}
                </Campo>

                <div className="grid grid-cols-[1fr_auto] gap-2">
                    <Campo label="Peso bruto" error={errors.peso}>
                        {(id) => (
                            <Input
                                id={id}
                                inputMode="decimal"
                                value={data.peso}
                                onChange={(e) =>
                                    setData('peso', e.target.value)
                                }
                                placeholder="10150.000"
                            />
                        )}
                    </Campo>
                    <Campo label="Unidad" error={errors.unidad_peso}>
                        {(id) => (
                            <Selector
                                id={id}
                                value={data.unidad_peso}
                                onChange={(v) => setData('unidad_peso', v)}
                                options={[
                                    { value: 'KGM', label: 'KGM' },
                                    { value: 'TNE', label: 'TNE' },
                                ]}
                            />
                        )}
                    </Campo>
                </div>
            </Section>

            <Section
                title="Ruta"
                description="El distrito define el ubigeo que SUNAT exige. Los lugares ya usados se reutilizan con un clic."
            >
                <PuntoCampos
                    titulo="Punto de partida"
                    lado="partida"
                    punto={data.partida}
                    frecuentes={puntosFrecuentes}
                    onUsar={usarPunto}
                    onChange={(punto) => setData('partida', punto)}
                    errorUbigeo={errors['partida.ubigeo']}
                    errorDireccion={errors['partida.direccion']}
                />
                <PuntoCampos
                    titulo="Punto de llegada"
                    lado="llegada"
                    punto={data.llegada}
                    frecuentes={puntosFrecuentes}
                    onUsar={usarPunto}
                    onChange={(punto) => setData('llegada', punto)}
                    errorUbigeo={errors['llegada.ubigeo']}
                    errorDireccion={errors['llegada.direccion']}
                />
            </Section>

            <Section
                title="Remitente y destinatario"
                description="El remitente es el dueño de la carga; el destinatario, quien la recibe."
            >
                <Campo label="Remitente (cliente)" error={errors.cliente_id}>
                    {(id) => (
                        <Selector
                            id={id}
                            value={data.cliente_id}
                            onChange={(valor) => {
                                setData('cliente_id', valor);

                                const elegido = clientes.find(
                                    (c) => c.value === valor,
                                );

                                // El caso normal es que remitente y
                                // destinatario sean el mismo; si no, se corrige.
                                if (elegido && !data.destinatario) {
                                    setData(
                                        'destinatario',
                                        elegido.razon_social,
                                    );
                                    setData('destinatario_ruc', elegido.ruc);
                                }
                            }}
                            options={clientes}
                            placeholder="Elige el cliente"
                        />
                    )}
                </Campo>

                <div className="grid gap-1.5">
                    <Label className="text-xs text-muted-foreground">
                        RUC del remitente
                    </Label>
                    <p className="font-mono text-sm">
                        {cliente?.ruc ?? (
                            <span className="text-muted-foreground">—</span>
                        )}
                    </p>
                </div>

                <Campo label="Destinatario" error={errors.destinatario}>
                    {(id) => (
                        <Input
                            id={id}
                            value={data.destinatario}
                            onChange={(e) =>
                                setData('destinatario', e.target.value)
                            }
                            placeholder="MINSUR S.A."
                        />
                    )}
                </Campo>

                <Campo
                    label="RUC del destinatario"
                    error={errors.destinatario_ruc}
                >
                    {(id) => (
                        <Input
                            id={id}
                            inputMode="numeric"
                            maxLength={11}
                            value={data.destinatario_ruc}
                            onChange={(e) =>
                                setData(
                                    'destinatario_ruc',
                                    e.target.value.replace(/\D/g, ''),
                                )
                            }
                            placeholder="20100136741"
                        />
                    )}
                </Campo>
            </Section>

            <Section
                title="Unidad y conductor"
                description="El TUC y la licencia salen del padrón: no hay que escribirlos."
            >
                <Campo label="Tracto" error={errors.tracto_id}>
                    {(id) => (
                        <Selector
                            id={id}
                            value={data.tracto_id}
                            onChange={(v) => setData('tracto_id', v)}
                            options={tractos}
                            placeholder="Elige el tracto"
                        />
                    )}
                </Campo>
                <Dato etiqueta="TUC del tracto" valor={tracto?.tuc} />

                <Campo label="Carreta" error={errors.carreta_id}>
                    {(id) => (
                        <Selector
                            id={id}
                            value={data.carreta_id}
                            onChange={(v) => setData('carreta_id', v)}
                            options={carretas}
                            placeholder="Sin carreta"
                        />
                    )}
                </Campo>
                <Dato etiqueta="TUC de la carreta" valor={carreta?.tuc} />

                <Campo label="Conductor" error={errors.conductor_id}>
                    {(id) => (
                        <Selector
                            id={id}
                            value={data.conductor_id}
                            onChange={(v) => setData('conductor_id', v)}
                            options={conductores}
                            placeholder="Elige el conductor"
                        />
                    )}
                </Campo>
                <Dato etiqueta="Licencia" valor={conductor?.licencia} />
            </Section>

            <Section
                title="Guías del remitente"
                description="El detalle de la carga vive en estas guías; la del transportista solo las referencia."
                columnaUnica
            >
                <div className="flex flex-col gap-2">
                    {data.guias_remitente.map((guia, indice) => (
                        <div key={indice} className="flex items-start gap-2">
                            <Input
                                value={guia.numero}
                                onChange={(e) => {
                                    const copia = [...data.guias_remitente];
                                    copia[indice] = {
                                        ...copia[indice],
                                        numero: e.target.value.toUpperCase(),
                                    };
                                    setData('guias_remitente', copia);
                                }}
                                placeholder="T012-855"
                                className="flex-1"
                            />
                            <Input
                                value={guia.ruc}
                                onChange={(e) => {
                                    const copia = [...data.guias_remitente];
                                    copia[indice] = {
                                        ...copia[indice],
                                        ruc: e.target.value.replace(/\D/g, ''),
                                    };
                                    setData('guias_remitente', copia);
                                }}
                                inputMode="numeric"
                                maxLength={11}
                                placeholder="RUC del remitente"
                                className="w-48"
                            />
                            <Button
                                type="button"
                                size="icon"
                                variant="ghost"
                                aria-label="Quitar guía"
                                onClick={() =>
                                    setData(
                                        'guias_remitente',
                                        data.guias_remitente.filter(
                                            (_, i) => i !== indice,
                                        ),
                                    )
                                }
                            >
                                <Trash2 className="size-4 text-destructive" />
                            </Button>
                        </div>
                    ))}

                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        className="self-start"
                        onClick={() =>
                            setData('guias_remitente', [
                                ...data.guias_remitente,
                                { numero: '', ruc: cliente?.ruc ?? '' },
                            ])
                        }
                    >
                        <Plus className="size-4" />
                        Agregar guía
                    </Button>
                </div>

                <div className="mt-4">
                    <Campo label="Observaciones" error={errors.observaciones}>
                        {(id) => (
                            <Textarea
                                id={id}
                                value={data.observaciones}
                                onChange={(e) =>
                                    setData('observaciones', e.target.value)
                                }
                                rows={2}
                            />
                        )}
                    </Campo>
                </div>
            </Section>

            <div className="flex items-center justify-end gap-3 border-t pt-4">
                <Button type="submit" size="lg" disabled={processing}>
                    {processing ? <Spinner /> : <Send className="size-4" />}
                    {processing ? 'Emitiendo...' : 'Emitir guía'}
                </Button>
            </div>
        </form>
    );
}

function PuntoCampos({
    titulo,
    lado,
    punto,
    frecuentes,
    onUsar,
    onChange,
    errorUbigeo,
    errorDireccion,
}: {
    titulo: string;
    lado: 'partida' | 'llegada';
    punto: Punto;
    frecuentes: PuntoOption[];
    onUsar: (lado: 'partida' | 'llegada', punto: PuntoOption) => void;
    onChange: (punto: Punto) => void;
    errorUbigeo?: string;
    errorDireccion?: string;
}) {
    return (
        <div className="col-span-full grid gap-3 rounded-lg border border-border p-4">
            <p className="text-sm font-medium">{titulo}</p>

            {frecuentes.length > 0 && (
                <div className="flex flex-wrap gap-1.5">
                    {frecuentes.slice(0, 6).map((frecuente) => (
                        <button
                            key={frecuente.value}
                            type="button"
                            onClick={() => onUsar(lado, frecuente)}
                            className="rounded-full border border-border px-2.5 py-1 text-xs hover:bg-accent hover:text-accent-foreground"
                        >
                            {frecuente.label}
                        </button>
                    ))}
                </div>
            )}

            <div className="grid gap-3 sm:grid-cols-2">
                <UbigeoPicker
                    label="Distrito"
                    value={punto.ubigeo}
                    etiqueta={punto.etiqueta || punto.ubigeo}
                    onChange={(ubigeo, etiqueta) =>
                        onChange({ ...punto, ubigeo, etiqueta })
                    }
                    error={errorUbigeo}
                />

                <div className="grid gap-1.5">
                    <Label>
                        Dirección<span className="text-destructive"> *</span>
                    </Label>
                    <Textarea
                        value={punto.direccion}
                        onChange={(e) =>
                            onChange({ ...punto, direccion: e.target.value })
                        }
                        rows={2}
                        placeholder="CAR. PANAMERICANA SUR KM. 238 ZONA INDUSTRIAL"
                    />
                    <InputError message={errorDireccion} />
                </div>
            </div>
        </div>
    );
}

function Dato({
    etiqueta,
    valor,
}: {
    etiqueta: string;
    valor?: string | null;
}) {
    return (
        <div className="grid gap-1.5">
            <Label className="text-xs text-muted-foreground">{etiqueta}</Label>
            <p className="font-mono text-sm">
                {valor ?? <span className="text-muted-foreground">—</span>}
            </p>
        </div>
    );
}

function Section({
    title,
    description,
    columnaUnica,
    children,
}: {
    title: string;
    description?: string;
    columnaUnica?: boolean;
    children: React.ReactNode;
}) {
    return (
        <section className="rounded-xl border border-border bg-card p-5">
            <div className="mb-4">
                <h2 className="text-sm font-semibold text-foreground">
                    {title}
                </h2>
                {description && (
                    <p className="text-xs text-muted-foreground">
                        {description}
                    </p>
                )}
            </div>
            <div className={columnaUnica ? '' : 'grid gap-4 sm:grid-cols-2'}>
                {children}
            </div>
        </section>
    );
}

function Campo({
    label,
    error,
    children,
}: {
    label: string;
    error?: string;
    children: (id: string) => React.ReactNode;
}) {
    const id = useId();

    return (
        <div className="grid gap-1.5">
            <Label htmlFor={id}>{label}</Label>
            {children(id)}
            <InputError message={error} />
        </div>
    );
}

function Selector({
    id,
    value,
    onChange,
    options,
    placeholder,
}: {
    id: string;
    value: string;
    onChange: (valor: string) => void;
    options: EnumOption[];
    placeholder?: string;
}) {
    return (
        <Select value={value} onValueChange={onChange}>
            <SelectTrigger id={id}>
                <SelectValue placeholder={placeholder} />
            </SelectTrigger>
            <SelectContent>
                {options.map((opcion) => (
                    <SelectItem key={opcion.value} value={opcion.value}>
                        {opcion.label}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}
