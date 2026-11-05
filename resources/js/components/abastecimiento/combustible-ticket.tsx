import { useForm } from '@inertiajs/react';
import { Ban, Fuel, Receipt } from 'lucide-react';
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
    ReglaParaConfirmar,
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
          tipo: 'tarjeta';
          vueltaId: number;
          codigo: string | null;
          sentido: 'ida' | 'regreso';
          sugeridaId: number | null;
      }
    | { tipo: 'despacho'; registro: CombustibleDelTicket }
    | { tipo: 'anular'; registro: CombustibleDelTicket };

/**
 * La tarjeta de cada tramo: la confirmada (con quién y cuándo) o la sugerida,
 * con el botón para confirmarla o elegir otra. Lo confirmado es lo
 * autorizado: si la pizarra cambia después, el ticket no.
 */
export function TarjetasDelTicket({
    vueltaId,
    codigo,
    tramos,
    puedeConfirmar,
    onAccion,
}: {
    vueltaId: number;
    codigo: string | null;
    tramos: TramoCombustible[];
    puedeConfirmar: boolean;
    onAccion: (accion: AccionCombustible) => void;
}) {
    return (
        <ul className="flex flex-col gap-2">
            {tramos.map((tramo) => (
                <li
                    key={tramo.sentido}
                    className="flex flex-wrap items-center justify-between gap-2 rounded-lg border p-2"
                >
                    <span className="flex min-w-0 flex-col gap-0.5">
                        <span
                            className={cn(
                                'text-[11px] font-medium',
                                colorDeSentido(tramo.sentido),
                            )}
                        >
                            {nombreTramo(tramo.sentido)}
                        </span>
                        {tramo.confirmada ? (
                            <>
                                <span
                                    className={cn(
                                        'truncate rounded px-1.5 py-0.5 text-xs font-medium',
                                        colorTarjeta(tramo.color).cabecera,
                                    )}
                                >
                                    {tramo.tarjeta}
                                </span>
                                <span className="text-[11px] text-muted-foreground">
                                    Tope {galones(tramo.previsto)} · confirmó{' '}
                                    {tramo.confirmada_por ?? '—'}
                                </span>
                            </>
                        ) : (
                            <span className="text-xs text-amber-700 dark:text-amber-400">
                                Por confirmar
                                {tramo.sugerida
                                    ? ` · sugerida: ${tramo.sugerida.nombre}`
                                    : ' · ninguna tarjeta calza sola'}
                            </span>
                        )}
                    </span>
                    {puedeConfirmar && (
                        <Button
                            size="sm"
                            variant={tramo.confirmada ? 'ghost' : 'default'}
                            onClick={() =>
                                onAccion({
                                    tipo: 'tarjeta',
                                    vueltaId,
                                    codigo,
                                    sentido: tramo.sentido,
                                    sugeridaId: tramo.sugerida?.id ?? null,
                                })
                            }
                        >
                            {tramo.confirmada ? 'Cambiar' : 'Confirmar'}
                        </Button>
                    )}
                </li>
            ))}
        </ul>
    );
}

/**
 * Cada combustible autorizado del ticket, con su voucher (lo que de verdad
 * cargó, a qué precio y con qué km) o el botón para registrarlo. Los
 * anulados quedan tachados, con su motivo.
 */
export function CombustiblesDelTicket({
    registros,
    puedeGestionar,
    onAccion,
}: {
    registros: CombustibleDelTicket[];
    puedeGestionar: boolean;
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
                        {puedeGestionar && !registro.anulado && (
                            <span className="flex shrink-0 gap-1">
                                <Button
                                    size="sm"
                                    variant={
                                        registro.despacho ? 'ghost' : 'outline'
                                    }
                                    onClick={() =>
                                        onAccion({ tipo: 'despacho', registro })
                                    }
                                >
                                    <Receipt className="size-3.5" />
                                    {registro.despacho ? 'Corregir' : 'Voucher'}
                                </Button>
                                <Button
                                    size="icon"
                                    variant="ghost"
                                    aria-label="Anular"
                                    title="Anular"
                                    onClick={() =>
                                        onAccion({ tipo: 'anular', registro })
                                    }
                                >
                                    <Ban className="size-3.5" />
                                </Button>
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
                        <span className="text-[11px] text-amber-700 dark:text-amber-400">
                            Falta el voucher (galones reales y precio)
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
    reglas,
    puntos,
    onCerrar,
}: {
    accion: AccionCombustible;
    reglas: ReglaParaConfirmar[];
    puntos: PuntoRegistro[];
    onCerrar: () => void;
}) {
    return (
        <Dialog open onOpenChange={(abierto) => !abierto && onCerrar()}>
            <DialogContent className="flex flex-col overflow-hidden md:max-h-[85dvh] md:max-w-md">
                {accion.tipo === 'tarjeta' && (
                    <FormTarjeta
                        accion={accion}
                        reglas={reglas}
                        onCerrar={onCerrar}
                    />
                )}
                {accion.tipo === 'despacho' && (
                    <FormDespacho
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

function FormTarjeta({
    accion,
    reglas,
    onCerrar,
}: {
    accion: Extract<AccionCombustible, { tipo: 'tarjeta' }>;
    reglas: ReglaParaConfirmar[];
    onCerrar: () => void;
}) {
    const { data, setData, post, processing, errors } = useForm({
        sentido: accion.sentido,
        regla_abastecimiento_id: accion.sugeridaId,
    });
    // Primero las del mismo tramo; las demás también se pueden elegir.
    const ordenadas = [...reglas].sort(
        (a, b) =>
            Number(b.sentido === accion.sentido) -
            Number(a.sentido === accion.sentido),
    );
    const elegida = reglas.find(
        (regla) => regla.id === data.regla_abastecimiento_id,
    );

    const enviar = (evento: React.FormEvent) => {
        evento.preventDefault();
        post(abastecimiento.confirmarDotacion(accion.vueltaId).url, {
            preserveScroll: true,
            onSuccess: onCerrar,
        });
    };

    return (
        <>
            <DialogHeader>
                <DialogTitle>
                    Tarjeta de la {nombreTramo(accion.sentido).toLowerCase()} ·{' '}
                    {accion.codigo}
                </DialogTitle>
                <DialogDescription>
                    Lo que dice la tarjeta queda como lo autorizado para este
                    tramo. Si después cambia la pizarra, el ticket no cambia.
                </DialogDescription>
            </DialogHeader>

            <form
                id="confirmar-tarjeta"
                onSubmit={enviar}
                className="flex flex-col gap-4 px-1"
            >
                <Field
                    label="Tarjeta"
                    error={errors.regla_abastecimiento_id}
                    required
                    ayuda={
                        elegida
                            ? elegida.tope === null
                                ? 'Tiene galones por definir: complétala en la pizarra antes de autorizar combustible.'
                                : `${elegida.resumen} · tope ${galones(elegida.tope)}`
                            : undefined
                    }
                >
                    {() => (
                        <Select
                            value={
                                data.regla_abastecimiento_id
                                    ? String(data.regla_abastecimiento_id)
                                    : undefined
                            }
                            onValueChange={(valor) =>
                                setData(
                                    'regla_abastecimiento_id',
                                    Number(valor),
                                )
                            }
                        >
                            <SelectTrigger className="w-full">
                                <SelectValue placeholder="Elegir…" />
                            </SelectTrigger>
                            <SelectContent>
                                {ordenadas.map((regla) => (
                                    <SelectItem
                                        key={regla.id}
                                        value={String(regla.id)}
                                    >
                                        {regla.nombre}
                                        {regla.id === accion.sugeridaId &&
                                            ' (sugerida)'}
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
                    form="confirmar-tarjeta"
                    disabled={processing || !data.regla_abastecimiento_id}
                >
                    Confirmar tarjeta
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
            registro.despacho?.precio_galon ?? ultimoPrecio ?? '',
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
