import { useForm } from '@inertiajs/react';
import { Ban, Fuel, Pencil, Receipt } from 'lucide-react';
import { useState } from 'react';
import abastecimiento from '@/actions/App/Http/Controllers/AbastecimientoController';
import { galones, soles } from '@/components/abastecimiento/barra-combustible';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { colorTarjeta } from '@/lib/color-tarjeta';
import { colorDeSentido } from '@/lib/etapas';
import { formatearFecha } from '@/lib/format';
import { cn } from '@/lib/utils';
import type {
    CombustibleDelTicket,
    PuntoRegistro,
    RutaDelTramo,
    TramoCombustible,
} from '@/types/abastecimiento';

function hoy(): string {
    const ahora = new Date();
    const mes = String(ahora.getMonth() + 1).padStart(2, '0');
    const dia = String(ahora.getDate()).padStart(2, '0');

    return `${ahora.getFullYear()}-${mes}-${dia}`;
}

const nombreTramo = (sentido: 'ida' | 'regreso') =>
    sentido === 'ida' ? 'Ida' : 'Vuelta';

/** Qué diálogo del combustible del ticket está abierto. */
export type AccionCombustible =
    | {
          tipo: 'variante';
          codigo: string | null;
          ruta: RutaDelTramo;
      }
    | { tipo: 'despacho'; registro: CombustibleDelTicket }
    | { tipo: 'precio'; registro: CombustibleDelTicket }
    | { tipo: 'editar'; registro: CombustibleDelTicket }
    | { tipo: 'anular'; registro: CombustibleDelTicket };

/** El valor del selector de punto para un grifo fuera de la lista. */
const OTRO_PUNTO = 'otro';

/**
 * Las rutas de cada tramo, como se eligieron al programar: son lo autorizado.
 * Si a una le falta la variante (la dotación por peso), se elige acá; el
 * sistema sugiere la que corresponde al peso de las GR. La ruta no se cambia
 * acá: eso es de programación.
 */
export function RutasDelTicket({
    codigo,
    tramos,
    puedeElegirVariante,
    onAccion,
}: {
    codigo: string | null;
    tramos: TramoCombustible[];
    puedeElegirVariante: boolean;
    onAccion: (accion: AccionCombustible) => void;
}) {
    return (
        <ul className="flex flex-col gap-2">
            {tramos.map((tramo) => (
                <li
                    key={tramo.sentido}
                    className="flex flex-col gap-1.5 rounded-lg border p-2"
                >
                    <span className="flex items-center justify-between gap-2">
                        <span
                            className={cn(
                                'text-[11px] font-medium',
                                colorDeSentido(tramo.sentido),
                            )}
                        >
                            {nombreTramo(tramo.sentido)}
                        </span>
                        <span className="text-[11px] text-muted-foreground tabular-nums">
                            {tramo.previsto === null
                                ? (tramo.pendiente ?? 'Sin tope')
                                : `Tope ${galones(tramo.previsto)}`}
                        </span>
                    </span>
                    {tramo.rutas.length === 0 && (
                        <span className="text-xs text-amber-700 dark:text-amber-400">
                            Sin ruta: se elige al programar el ticket.
                        </span>
                    )}
                    {tramo.rutas.map((ruta) => (
                        <span
                            key={ruta.id}
                            className="flex flex-wrap items-center justify-between gap-2"
                        >
                            <span
                                className={cn(
                                    'truncate rounded px-1.5 py-0.5 text-xs font-medium',
                                    colorTarjeta(ruta.color).cabecera,
                                )}
                            >
                                {ruta.es_complemento && '+ '}
                                {ruta.nombre}
                                {ruta.variante && ` · ${ruta.variante}`}
                            </span>
                            {ruta.variante_pendiente ? (
                                puedeElegirVariante ? (
                                    <Button
                                        size="sm"
                                        onClick={() =>
                                            onAccion({
                                                tipo: 'variante',
                                                codigo,
                                                ruta,
                                            })
                                        }
                                    >
                                        Elegir variante
                                    </Button>
                                ) : (
                                    <span className="text-xs text-amber-700 dark:text-amber-400">
                                        Falta la variante
                                    </span>
                                )
                            ) : (
                                <span className="text-[11px] text-muted-foreground tabular-nums">
                                    {ruta.tope === null
                                        ? 'galones por definir'
                                        : galones(ruta.tope)}
                                </span>
                            )}
                        </span>
                    ))}
                </li>
            ))}
        </ul>
    );
}

/**
 * Cada combustible autorizado del ticket, con su voucher (lo que de verdad
 * cargó, a qué precio y con qué km) o el botón para registrarlo. Los
 * anulados quedan tachados, con su motivo.
 *
 * Quien registra (`puedeGestionar`) carga el voucher una vez y después solo
 * corrige el precio; editar, corregir el voucher y anular es de quien
 * corrige registros (`puedeCorregir`).
 */
export function CombustiblesDelTicket({
    registros,
    puedeGestionar,
    puedeCorregir = false,
    onAccion,
}: {
    registros: CombustibleDelTicket[];
    puedeGestionar: boolean;
    puedeCorregir?: boolean;
    onAccion: (accion: AccionCombustible) => void;
}) {
    if (registros.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                Todavía no se autorizó combustible.
            </p>
        );
    }

    return (
        <ul className="flex flex-col divide-y rounded-lg border">
            {registros.map((registro) => (
                <li
                    key={registro.id}
                    className={cn(
                        'flex flex-col gap-1 p-2 text-sm',
                        registro.anulado && 'text-muted-foreground',
                    )}
                >
                    <div className="flex items-start justify-between gap-2">
                        <span className="flex flex-col">
                            <span
                                className={cn(
                                    registro.anulado && 'line-through',
                                )}
                            >
                                <Fuel className="mr-1 inline size-3.5" />
                                {galones(registro.galones)} ·{' '}
                                {registro.punto ?? 'Sin punto'}
                            </span>
                            <span className="text-[11px] text-muted-foreground">
                                {formatearFecha(registro.fecha)}
                                {registro.sentido &&
                                    ` · ${nombreTramo(registro.sentido)}`}
                                {registro.registrado_por &&
                                    ` · ${registro.registrado_por}`}
                            </span>
                        </span>
                        {(puedeGestionar || puedeCorregir) &&
                            !registro.anulado && (
                                <span className="flex shrink-0 gap-1">
                                    {!registro.despacho ? (
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() =>
                                                onAccion({
                                                    tipo: 'despacho',
                                                    registro,
                                                })
                                            }
                                        >
                                            <Receipt className="size-3.5" />
                                            Voucher
                                        </Button>
                                    ) : puedeCorregir ? (
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            onClick={() =>
                                                onAccion({
                                                    tipo: 'despacho',
                                                    registro,
                                                })
                                            }
                                        >
                                            <Receipt className="size-3.5" />
                                            Corregir voucher
                                        </Button>
                                    ) : (
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            onClick={() =>
                                                onAccion({
                                                    tipo: 'precio',
                                                    registro,
                                                })
                                            }
                                        >
                                            <Receipt className="size-3.5" />
                                            Precio
                                        </Button>
                                    )}
                                    {puedeCorregir && (
                                        <>
                                            <Button
                                                size="icon"
                                                variant="ghost"
                                                aria-label="Editar"
                                                title="Editar lo autorizado"
                                                onClick={() =>
                                                    onAccion({
                                                        tipo: 'editar',
                                                        registro,
                                                    })
                                                }
                                            >
                                                <Pencil className="size-3.5" />
                                            </Button>
                                            <Button
                                                size="icon"
                                                variant="ghost"
                                                aria-label="Anular"
                                                title="Anular"
                                                onClick={() =>
                                                    onAccion({
                                                        tipo: 'anular',
                                                        registro,
                                                    })
                                                }
                                            >
                                                <Ban className="size-3.5" />
                                            </Button>
                                        </>
                                    )}
                                </span>
                            )}
                    </div>
                    {registro.motivo_exceso && (
                        <span className="text-[11px] text-amber-700 dark:text-amber-400">
                            Pasó el tope: {registro.motivo_exceso}
                        </span>
                    )}
                    {registro.anulado ? (
                        <span className="text-[11px]">
                            Anulado
                            {registro.anulado.por &&
                                ` por ${registro.anulado.por}`}
                            {registro.anulado.motivo &&
                                `: ${registro.anulado.motivo}`}
                        </span>
                    ) : registro.despacho ? (
                        <span className="text-[11px] text-emerald-700 tabular-nums dark:text-emerald-400">
                            Voucher: {galones(registro.despacho.galones)} ×{' '}
                            {registro.despacho.precio_galon?.toFixed(3)} ={' '}
                            {soles(registro.despacho.importe)}
                            {registro.despacho.kilometraje !== null &&
                                ` · ${registro.despacho.kilometraje.toLocaleString('es-PE')} km`}
                        </span>
                    ) : (
                        <span className="text-[11px] text-amber-700 tabular-nums dark:text-amber-400">
                            Falta el voucher
                            {registro.precio_autorizado !== null &&
                                registro.costo !== null &&
                                ` · estimado ${galones(registro.galones)} × ${registro.precio_autorizado.toFixed(3)} = ${soles(registro.costo)}`}
                        </span>
                    )}
                </li>
            ))}
        </ul>
    );
}

/** Los diálogos del combustible del ticket, según la acción. */
export function DialogoCombustible({
    accion,
    puntos,
    onCerrar,
}: {
    accion: AccionCombustible;
    puntos: PuntoRegistro[];
    onCerrar: () => void;
}) {
    return (
        <Dialog open onOpenChange={(abierto) => !abierto && onCerrar()}>
            <DialogContent className="flex flex-col overflow-hidden md:max-h-[85dvh] md:max-w-md">
                {accion.tipo === 'variante' && (
                    <FormVariante accion={accion} onCerrar={onCerrar} />
                )}
                {accion.tipo === 'despacho' && (
                    <FormDespacho
                        registro={accion.registro}
                        puntos={puntos}
                        onCerrar={onCerrar}
                    />
                )}
                {accion.tipo === 'precio' && (
                    <FormPrecio
                        registro={accion.registro}
                        onCerrar={onCerrar}
                    />
                )}
                {accion.tipo === 'editar' && (
                    <FormEditar
                        registro={accion.registro}
                        puntos={puntos}
                        onCerrar={onCerrar}
                    />
                )}
                {accion.tipo === 'anular' && (
                    <FormAnular
                        registro={accion.registro}
                        onCerrar={onCerrar}
                    />
                )}
            </DialogContent>
        </Dialog>
    );
}

/**
 * La variante de una ruta del ticket: la dotación según el peso. Viene
 * puesta la que corresponde al peso de las GR, si ya hay.
 */
function FormVariante({
    accion,
    onCerrar,
}: {
    accion: Extract<AccionCombustible, { tipo: 'variante' }>;
    onCerrar: () => void;
}) {
    const { data, setData, post, processing, errors } = useForm({
        variante_ruta_id: accion.ruta.sugerida,
    });

    const enviar = (evento: React.FormEvent) => {
        evento.preventDefault();
        post(abastecimiento.elegirVariante(accion.ruta.id).url, {
            preserveScroll: true,
            onSuccess: onCerrar,
        });
    };

    return (
        <>
            <DialogHeader>
                <DialogTitle>
                    {accion.ruta.nombre} · {accion.codigo}
                </DialogTitle>
                <DialogDescription>
                    La dotación depende del peso de la carga. Elige la variante:
                    desde ahí el tramo tiene tope.
                </DialogDescription>
            </DialogHeader>

            <form
                id="elegir-variante"
                onSubmit={enviar}
                className="flex flex-col gap-4 px-1"
            >
                <Field
                    label="Variante"
                    error={errors.variante_ruta_id}
                    required
                    ayuda={
                        accion.ruta.sugerida !== null
                            ? 'Viene puesta la que corresponde al peso de las GR.'
                            : 'Todavía no hay GR para saber el peso.'
                    }
                >
                    {() => (
                        <Select
                            value={
                                data.variante_ruta_id
                                    ? String(data.variante_ruta_id)
                                    : undefined
                            }
                            onValueChange={(valor) =>
                                setData('variante_ruta_id', Number(valor))
                            }
                        >
                            <SelectTrigger className="w-full">
                                <SelectValue placeholder="Elegir…" />
                            </SelectTrigger>
                            <SelectContent>
                                {accion.ruta.variantes.map((variante) => (
                                    <SelectItem
                                        key={variante.id}
                                        value={String(variante.id)}
                                    >
                                        {variante.nombre}
                                        {variante.id === accion.ruta.sugerida &&
                                            ' (según el peso)'}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    )}
                </Field>
            </form>

            <DialogFooter>
                <Button
                    type="submit"
                    form="elegir-variante"
                    disabled={processing || !data.variante_ruta_id}
                >
                    Guardar variante
                </Button>
            </DialogFooter>
        </>
    );
}

/**
 * El voucher del grifo: los galones que de verdad cargó (puede ser distinto
 * de lo autorizado), el precio y el kilometraje. El precio se propone con el
 * último que se pagó en ese punto.
 */
function FormDespacho({
    registro,
    puntos,
    onCerrar,
}: {
    registro: CombustibleDelTicket;
    puntos: PuntoRegistro[];
    onCerrar: () => void;
}) {
    const ultimoPrecio = puntos.find(
        (punto) => punto.id === registro.punto_id,
    )?.ultimo_precio;
    const { data, setData, put, processing, errors } = useForm({
        galones_despachados: String(
            registro.despacho?.galones ?? registro.galones,
        ),
        precio_galon: String(
            registro.despacho?.precio_galon ??
                registro.precio_autorizado ??
                ultimoPrecio ??
                '',
        ),
        kilometraje: registro.despacho?.kilometraje
            ? String(registro.despacho.kilometraje)
            : '',
        despachado_el: registro.despacho?.fecha ?? hoy(),
    });
    const importe =
        data.galones_despachados !== '' && data.precio_galon !== ''
            ? Number(data.galones_despachados) * Number(data.precio_galon)
            : null;
    const diferencia =
        data.galones_despachados !== ''
            ? Number(data.galones_despachados) - registro.galones
            : 0;

    const enviar = (evento: React.FormEvent) => {
        evento.preventDefault();
        put(abastecimiento.despacho(registro.id).url, {
            preserveScroll: true,
            onSuccess: onCerrar,
        });
    };

    return (
        <>
            <DialogHeader>
                <DialogTitle>
                    Voucher · {registro.punto ?? 'Sin punto'}
                </DialogTitle>
                <DialogDescription>
                    Se autorizaron {galones(registro.galones)}. Anota lo que
                    dice el voucher del grifo.
                </DialogDescription>
            </DialogHeader>

            <form
                id="registrar-despacho"
                onSubmit={enviar}
                className="flex flex-col gap-4 px-1"
            >
                <div className="grid grid-cols-2 gap-4">
                    <Field
                        label="Galones cargados"
                        error={errors.galones_despachados}
                        required
                        ayuda={
                            diferencia !== 0
                                ? `${diferencia > 0 ? '+' : ''}${diferencia.toFixed(1)} gal contra lo autorizado`
                                : undefined
                        }
                    >
                        {(id) => (
                            <Input
                                id={id}
                                type="number"
                                inputMode="decimal"
                                min="0"
                                step="0.01"
                                value={data.galones_despachados}
                                onChange={(evento) =>
                                    setData(
                                        'galones_despachados',
                                        evento.target.value,
                                    )
                                }
                            />
                        )}
                    </Field>
                    <Field
                        label="Precio por galón (S/)"
                        error={errors.precio_galon}
                        required
                        ayuda={
                            importe !== null
                                ? `Importe: ${soles(importe)}`
                                : undefined
                        }
                    >
                        {(id) => (
                            <Input
                                id={id}
                                type="number"
                                inputMode="decimal"
                                min="0"
                                step="0.001"
                                value={data.precio_galon}
                                onChange={(evento) =>
                                    setData('precio_galon', evento.target.value)
                                }
                            />
                        )}
                    </Field>
                </div>
                <div className="grid grid-cols-2 gap-4">
                    <Field
                        label="Kilometraje"
                        error={errors.kilometraje}
                        ayuda="El odómetro al cargar: sirve para el rendimiento y el mantenimiento."
                    >
                        {(id) => (
                            <Input
                                id={id}
                                type="number"
                                inputMode="numeric"
                                min="0"
                                step="1"
                                value={data.kilometraje}
                                onChange={(evento) =>
                                    setData('kilometraje', evento.target.value)
                                }
                            />
                        )}
                    </Field>
                    <Field
                        label="Fecha del voucher"
                        error={errors.despachado_el}
                        required
                    >
                        {(id) => (
                            <Input
                                id={id}
                                type="date"
                                value={data.despachado_el}
                                onChange={(evento) =>
                                    setData(
                                        'despachado_el',
                                        evento.target.value,
                                    )
                                }
                            />
                        )}
                    </Field>
                </div>
            </form>

            <DialogFooter>
                <Button
                    type="submit"
                    form="registrar-despacho"
                    disabled={processing}
                >
                    Guardar voucher
                </Button>
            </DialogFooter>
        </>
    );
}

/**
 * Solo el precio del voucher: los galones y el kilometraje quedan como
 * están, y el importe se recalcula.
 */
function FormPrecio({
    registro,
    onCerrar,
}: {
    registro: CombustibleDelTicket;
    onCerrar: () => void;
}) {
    const galonesCargados = registro.despacho?.galones ?? registro.galones;
    const { data, setData, put, processing, errors } = useForm({
        precio_galon: String(registro.despacho?.precio_galon ?? ''),
    });
    const importe =
        data.precio_galon !== ''
            ? galonesCargados * Number(data.precio_galon)
            : null;

    const enviar = (evento: React.FormEvent) => {
        evento.preventDefault();
        put(abastecimiento.precio(registro.id).url, {
            preserveScroll: true,
            onSuccess: onCerrar,
        });
    };

    return (
        <>
            <DialogHeader>
                <DialogTitle>
                    Precio · {registro.punto ?? 'Sin punto'}
                </DialogTitle>
                <DialogDescription>
                    Cargó {galones(galonesCargados)}. Corrige el precio por
                    galón del voucher; lo demás lo corrige el administrador.
                </DialogDescription>
            </DialogHeader>

            <form
                id="corregir-precio"
                onSubmit={enviar}
                className="flex flex-col gap-4 px-1"
            >
                <Field
                    label="Precio por galón (S/)"
                    error={errors.precio_galon}
                    required
                    ayuda={
                        importe !== null
                            ? `Importe: ${soles(importe)}`
                            : undefined
                    }
                >
                    {(id) => (
                        <Input
                            id={id}
                            type="number"
                            inputMode="decimal"
                            min="0"
                            step="0.001"
                            autoFocus
                            value={data.precio_galon}
                            onChange={(evento) =>
                                setData('precio_galon', evento.target.value)
                            }
                        />
                    )}
                </Field>
            </form>

            <DialogFooter>
                <Button
                    type="submit"
                    form="corregir-precio"
                    disabled={processing}
                >
                    Guardar precio
                </Button>
            </DialogFooter>
        </>
    );
}

/**
 * Corregir lo autorizado cuando se registró mal: el punto, los galones, la
 * fecha o el tramo. El ticket no cambia: si era de otro, se anula y se
 * registra en el correcto.
 */
function FormEditar({
    registro,
    puntos,
    onCerrar,
}: {
    registro: CombustibleDelTicket;
    puntos: PuntoRegistro[];
    onCerrar: () => void;
}) {
    const [fueraDeLista, setFueraDeLista] = useState(registro.fuera_de_lista);
    const { data, setData, put, processing, errors } = useForm({
        sentido: registro.sentido ?? 'ida',
        punto_abastecimiento_id: registro.punto_id,
        punto_libre: registro.fuera_de_lista ? (registro.punto ?? '') : '',
        fecha: registro.fecha,
        galones: String(registro.galones),
        precio_galon:
            registro.precio_autorizado !== null
                ? String(registro.precio_autorizado)
                : '',
        motivo_exceso: registro.motivo_exceso ?? '',
        observacion: registro.observacion ?? '',
    });

    const enviar = (evento: React.FormEvent) => {
        evento.preventDefault();
        put(abastecimiento.update(registro.id).url, {
            preserveScroll: true,
            onSuccess: onCerrar,
        });
    };

    return (
        <>
            <DialogHeader>
                <DialogTitle>
                    Editar {galones(registro.galones)} ·{' '}
                    {registro.punto ?? 'Sin punto'}
                </DialogTitle>
                <DialogDescription>
                    Corrige lo que se autorizó. Si pasa el tope del tramo, pide
                    el motivo como al registrar.
                </DialogDescription>
            </DialogHeader>

            <form
                id="editar-combustible"
                onSubmit={enviar}
                className="flex flex-col gap-4 overflow-y-auto px-1"
            >
                <div className="grid grid-cols-2 gap-4">
                    <Field label="Tramo" error={errors.sentido} required>
                        {() => (
                            <Select
                                value={data.sentido}
                                onValueChange={(valor) =>
                                    setData(
                                        'sentido',
                                        valor as 'ida' | 'regreso',
                                    )
                                }
                            >
                                <SelectTrigger className="w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="ida">Ida</SelectItem>
                                    <SelectItem value="regreso">
                                        Vuelta
                                    </SelectItem>
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
                                onChange={(evento) =>
                                    setData('fecha', evento.target.value)
                                }
                            />
                        )}
                    </Field>
                </div>

                <div className="grid grid-cols-2 gap-4">
                    <Field label="Punto" error={errors.punto_abastecimiento_id}>
                        {() => (
                            <Select
                                value={
                                    fueraDeLista
                                        ? OTRO_PUNTO
                                        : data.punto_abastecimiento_id
                                          ? String(data.punto_abastecimiento_id)
                                          : undefined
                                }
                                onValueChange={(valor) => {
                                    const otro = valor === OTRO_PUNTO;

                                    setFueraDeLista(otro);
                                    setData((anterior) => ({
                                        ...anterior,
                                        punto_abastecimiento_id: otro
                                            ? null
                                            : Number(valor),
                                        punto_libre: otro
                                            ? anterior.punto_libre
                                            : '',
                                    }));
                                }}
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
                                    <SelectItem value={OTRO_PUNTO}>
                                        Otro punto (fuera de la lista)…
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        )}
                    </Field>
                    <Field
                        label="Galones autorizados"
                        error={errors.galones}
                        required
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
                                    setData('galones', evento.target.value)
                                }
                            />
                        )}
                    </Field>
                </div>

                <Field
                    label="Precio por galón autorizado (S/)"
                    error={errors.precio_galon}
                    required
                >
                    {(id) => (
                        <Input
                            id={id}
                            type="number"
                            inputMode="decimal"
                            min="0"
                            step="0.001"
                            value={data.precio_galon}
                            onChange={(evento) =>
                                setData('precio_galon', evento.target.value)
                            }
                        />
                    )}
                </Field>

                {fueraDeLista && (
                    <Field
                        label="¿Dónde cargó?"
                        error={errors.punto_libre}
                        required
                    >
                        {(id) => (
                            <Input
                                id={id}
                                placeholder="Ej.: Primax Camaná"
                                maxLength={80}
                                value={data.punto_libre}
                                onChange={(evento) =>
                                    setData('punto_libre', evento.target.value)
                                }
                            />
                        )}
                    </Field>
                )}

                {(registro.motivo_exceso || errors.motivo_exceso) && (
                    <Field
                        label="¿Por qué pasa el tope?"
                        error={errors.motivo_exceso}
                        required
                    >
                        {(id) => (
                            <Textarea
                                id={id}
                                rows={2}
                                maxLength={500}
                                value={data.motivo_exceso}
                                onChange={(evento) =>
                                    setData(
                                        'motivo_exceso',
                                        evento.target.value,
                                    )
                                }
                            />
                        )}
                    </Field>
                )}

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
                <Button
                    type="submit"
                    form="editar-combustible"
                    disabled={processing}
                >
                    Guardar cambios
                </Button>
            </DialogFooter>
        </>
    );
}

/** Anular no borra: el registro queda tachado, con quién y por qué. */
function FormAnular({
    registro,
    onCerrar,
}: {
    registro: CombustibleDelTicket;
    onCerrar: () => void;
}) {
    const { data, setData, post, processing, errors } = useForm({ motivo: '' });

    const enviar = (evento: React.FormEvent) => {
        evento.preventDefault();
        post(abastecimiento.anular(registro.id).url, {
            preserveScroll: true,
            onSuccess: onCerrar,
        });
    };

    return (
        <>
            <DialogHeader>
                <DialogTitle>
                    Anular {galones(registro.galones)} ·{' '}
                    {registro.punto ?? 'Sin punto'}
                </DialogTitle>
                <DialogDescription>
                    No se borra: queda tachado en el ticket, con tu nombre y el
                    motivo, y deja de contar.
                </DialogDescription>
            </DialogHeader>

            <form
                id="anular-combustible"
                onSubmit={enviar}
                className="flex flex-col gap-4 px-1"
            >
                <Field label="Motivo" error={errors.motivo} required>
                    {(id) => (
                        <Textarea
                            id={id}
                            rows={2}
                            maxLength={500}
                            autoFocus
                            value={data.motivo}
                            placeholder="Ej.: se registró dos veces"
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
                    form="anular-combustible"
                    variant="destructive"
                    disabled={processing}
                >
                    Anular
                </Button>
            </DialogFooter>
        </>
    );
}
