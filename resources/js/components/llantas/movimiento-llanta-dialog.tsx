import { useForm } from '@inertiajs/react';
import { store } from '@/actions/App/Http/Controllers/LlantaController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialogo-responsivo';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import type { EnumOption } from '@/types/fleet';
import type { PosicionLlanta } from '@/types/llantas';

export type TipoMovimiento =
    | 'instalacion'
    | 'rotacion'
    | 'reparacion'
    | 'retiro';

type Formulario = {
    tipo: TipoMovimiento;
    posicion: string;
    posicion_destino: string;
    fecha: string;
    kilometraje: string;
    profundidad_mm: string;
    marca: string;
    medida: string;
    codigo: string;
    condicion: string;
    motivo_retiro: string;
    observacion: string;
};

const titulos: Record<TipoMovimiento, string> = {
    instalacion: 'Instalar llanta',
    rotacion: 'Rotar llanta',
    reparacion: 'Registrar reparación',
    retiro: 'Retirar llanta',
};

/**
 * Un movimiento sobre una posición. Lo que pide cambia con el tipo: los datos
 * de la llanta al instalarla, a dónde va al rotarla, qué se hizo al
 * repararla, y por qué sale al retirarla.
 */
export function MovimientoLlantaDialog({
    vehiculoId,
    esTracto,
    kmActual,
    hoy,
    tipo,
    posicion,
    posiciones,
    condiciones,
    motivos,
    abierto,
    onAbiertoChange,
}: {
    vehiculoId: number;
    /** Las carretas no llevan km. */
    esTracto: boolean;
    kmActual: number | null;
    hoy: string;
    tipo: TipoMovimiento;
    posicion: PosicionLlanta;
    posiciones: PosicionLlanta[];
    condiciones: EnumOption[];
    motivos: EnumOption[];
    abierto: boolean;
    onAbiertoChange: (abierto: boolean) => void;
}) {
    const { data, setData, post, transform, processing, errors, reset } =
        useForm<Formulario>({
            tipo,
            posicion: posicion.codigo,
            posicion_destino: '',
            fecha: hoy,
            kilometraje: kmActual === null ? '' : String(kmActual),
            profundidad_mm: '',
            marca: '',
            medida: '',
            codigo: '',
            condicion: 'nueva',
            motivo_retiro: '',
            observacion: '',
        });

    const guardar = (evento: React.FormEvent) => {
        evento.preventDefault();

        // Solo lo que corresponde al tipo: el resto lo rechaza el servidor.
        transform((datos) => ({
            tipo: datos.tipo,
            posicion: datos.posicion,
            fecha: datos.fecha,
            kilometraje:
                esTracto && datos.kilometraje ? datos.kilometraje : null,
            observacion: datos.observacion || null,
            ...(datos.tipo !== 'rotacion' && {
                profundidad_mm: datos.profundidad_mm
                    ? datos.profundidad_mm.replace(',', '.')
                    : null,
            }),
            ...(datos.tipo === 'rotacion' && {
                posicion_destino: datos.posicion_destino,
            }),
            ...(datos.tipo === 'instalacion' && {
                marca: datos.marca || null,
                medida: datos.medida || null,
                codigo: datos.codigo || null,
                condicion: datos.condicion,
            }),
            ...(datos.tipo === 'retiro' && {
                motivo_retiro: datos.motivo_retiro,
            }),
        }));

        post(store(vehiculoId).url, {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                onAbiertoChange(false);
            },
        });
    };

    const destinos = posiciones.filter((p) => p.codigo !== posicion.codigo);

    return (
        <Dialog open={abierto} onOpenChange={onAbiertoChange}>
            <DialogContent className="sm:max-w-lg">
                <form onSubmit={guardar} className="grid gap-4">
                    <DialogHeader>
                        <DialogTitle>{titulos[tipo]}</DialogTitle>
                        <DialogDescription>
                            Posición {posicion.codigo} · {posicion.nombre}
                        </DialogDescription>
                    </DialogHeader>

                    <InputError message={errors.posicion} />

                    {tipo === 'instalacion' && (
                        <>
                            <Opciones
                                etiqueta="La llanta es"
                                opciones={condiciones}
                                valor={data.condicion}
                                onCambio={(valor) =>
                                    setData('condicion', valor)
                                }
                            />
                            <InputError message={errors.condicion} />
                            <div className="grid grid-cols-2 gap-3">
                                <Campo
                                    id="llanta-marca"
                                    etiqueta="Marca"
                                    error={errors.marca}
                                >
                                    <Input
                                        id="llanta-marca"
                                        placeholder="Michelin"
                                        value={data.marca}
                                        onChange={(e) =>
                                            setData('marca', e.target.value)
                                        }
                                    />
                                </Campo>
                                <Campo
                                    id="llanta-medida"
                                    etiqueta="Medida"
                                    error={errors.medida}
                                >
                                    <Input
                                        id="llanta-medida"
                                        placeholder="295/80R22.5"
                                        value={data.medida}
                                        onChange={(e) =>
                                            setData('medida', e.target.value)
                                        }
                                    />
                                </Campo>
                            </div>
                            <Campo
                                id="llanta-codigo"
                                etiqueta="Código o serie (opcional)"
                                error={errors.codigo}
                            >
                                <Input
                                    id="llanta-codigo"
                                    value={data.codigo}
                                    onChange={(e) =>
                                        setData('codigo', e.target.value)
                                    }
                                />
                            </Campo>
                        </>
                    )}

                    {tipo === 'rotacion' && (
                        <div className="grid gap-1.5">
                            <Label>Pasa a la posición</Label>
                            <div className="grid grid-cols-6 gap-1.5">
                                {destinos.map((destino) => (
                                    <button
                                        key={destino.codigo}
                                        type="button"
                                        title={destino.nombre}
                                        onClick={() =>
                                            setData(
                                                'posicion_destino',
                                                destino.codigo,
                                            )
                                        }
                                        aria-pressed={
                                            data.posicion_destino ===
                                            destino.codigo
                                        }
                                        className={cn(
                                            'h-11 rounded-lg border text-sm font-semibold transition-colors',
                                            data.posicion_destino ===
                                                destino.codigo
                                                ? 'border-primary bg-primary text-primary-foreground'
                                                : 'hover:bg-accent',
                                        )}
                                    >
                                        {destino.codigo}
                                    </button>
                                ))}
                            </div>
                            {data.posicion_destino !== '' &&
                                destinos.find(
                                    (d) => d.codigo === data.posicion_destino,
                                )?.llanta && (
                                    <p className="text-xs text-muted-foreground">
                                        La {data.posicion_destino} tiene llanta:
                                        se intercambian.
                                    </p>
                                )}
                            <InputError message={errors.posicion_destino} />
                        </div>
                    )}

                    {tipo === 'retiro' && (
                        <>
                            <Opciones
                                etiqueta="Se retira por"
                                opciones={motivos}
                                valor={data.motivo_retiro}
                                onCambio={(valor) =>
                                    setData('motivo_retiro', valor)
                                }
                                columnas={2}
                            />
                            <InputError message={errors.motivo_retiro} />
                        </>
                    )}

                    <div className="grid grid-cols-2 gap-3">
                        <Campo
                            id="llanta-fecha"
                            etiqueta="Fecha"
                            error={errors.fecha}
                        >
                            <Input
                                id="llanta-fecha"
                                type="date"
                                max={hoy}
                                value={data.fecha}
                                onChange={(e) =>
                                    setData('fecha', e.target.value)
                                }
                            />
                        </Campo>
                        {tipo !== 'rotacion' && (
                            <Campo
                                id="llanta-mm"
                                etiqueta={
                                    tipo === 'reparacion'
                                        ? 'Dibujo (mm, opcional)'
                                        : 'Dibujo (mm)'
                                }
                                error={errors.profundidad_mm}
                            >
                                <Input
                                    id="llanta-mm"
                                    inputMode="decimal"
                                    placeholder="Ej. 14"
                                    value={data.profundidad_mm}
                                    onChange={(e) =>
                                        setData(
                                            'profundidad_mm',
                                            e.target.value,
                                        )
                                    }
                                    className="tabular-nums"
                                />
                            </Campo>
                        )}
                        {esTracto && (
                            <Campo
                                id="llanta-km"
                                etiqueta="Km del tracto"
                                error={errors.kilometraje}
                            >
                                <Input
                                    id="llanta-km"
                                    inputMode="numeric"
                                    value={data.kilometraje}
                                    onChange={(e) =>
                                        setData(
                                            'kilometraje',
                                            e.target.value.replace(/\D/g, ''),
                                        )
                                    }
                                    className="tabular-nums"
                                />
                            </Campo>
                        )}
                    </div>

                    <Campo
                        id="llanta-observacion"
                        etiqueta={
                            tipo === 'reparacion'
                                ? 'Qué se reparó'
                                : 'Observación (opcional)'
                        }
                        error={errors.observacion}
                    >
                        <Textarea
                            id="llanta-observacion"
                            rows={2}
                            placeholder={
                                tipo === 'reparacion'
                                    ? 'Parche por pinchazo, válvula…'
                                    : undefined
                            }
                            value={data.observacion}
                            onChange={(e) =>
                                setData('observacion', e.target.value)
                            }
                        />
                    </Campo>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={() => onAbiertoChange(false)}
                        >
                            Cancelar
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing && <Spinner />}
                            Guardar
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function Campo({
    id,
    etiqueta,
    error,
    children,
}: {
    id: string;
    etiqueta: string;
    error?: string;
    children: React.ReactNode;
}) {
    return (
        <div className="grid gap-1.5">
            <Label htmlFor={id}>{etiqueta}</Label>
            {children}
            <InputError message={error} />
        </div>
    );
}

/** Botones en fila: se aciertan mejor con el pulgar que un select. */
function Opciones({
    etiqueta,
    opciones,
    valor,
    onCambio,
    columnas,
}: {
    etiqueta: string;
    opciones: EnumOption[];
    valor: string;
    onCambio: (valor: string) => void;
    columnas?: number;
}) {
    return (
        <div className="grid gap-1.5">
            <Label>{etiqueta}</Label>
            <div
                className="grid gap-2"
                style={{
                    gridTemplateColumns: `repeat(${columnas ?? opciones.length}, minmax(0, 1fr))`,
                }}
            >
                {opciones.map((opcion) => (
                    <button
                        key={opcion.value}
                        type="button"
                        onClick={() => onCambio(opcion.value)}
                        aria-pressed={valor === opcion.value}
                        className={cn(
                            'min-h-11 rounded-lg border px-2 text-sm font-medium transition-colors',
                            valor === opcion.value
                                ? 'border-primary bg-primary text-primary-foreground'
                                : 'hover:bg-accent',
                        )}
                    >
                        {opcion.label}
                    </button>
                ))}
            </div>
        </div>
    );
}
