import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import { store } from '@/actions/App/Http/Controllers/AbastecimientoController';
import { galones, soles } from '@/components/abastecimiento/barra-combustible';
import { CampoSelector, PanelBuscador } from '@/components/selector-en-dialogo';
import type { OpcionBuscable } from '@/components/selector-en-dialogo';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import type {
    PuntoRegistro,
    TractoAbastecimiento,
    VueltaAbastecimiento,
} from '@/types/abastecimiento';

type Sentido = 'ida' | 'regreso';

type FormData = {
    vehiculo_id: number | null;
    vuelta_id: number | null;
    sentido: Sentido | '';
    punto_abastecimiento_id: number | null;
    /** Un grifo fuera de la lista, escrito a mano. */
    punto_libre: string;
    fecha: string;
    galones: string;
    /** Se llena el tanque (Kio): sin galones, los dice el voucher. */
    tanque_lleno: boolean;
    /** Soles por galón con que se autoriza: el costo del ticket desde ya. */
    precio_galon: string;
    motivo_exceso: string;
    observacion: string;
};

/** El valor del selector para un grifo que no está en la lista. */
const OTRO_PUNTO = 'otro';

/** Lo que se sabe al abrir: desde la fila de un ticket, o nada. */
export type InicialAbastecimiento = {
    vehiculo_id?: number;
    vuelta_id?: number | null;
    sentido?: Sentido | null;
    galones?: number | null;
};

function hoy(): string {
    const ahora = new Date();
    const mes = String(ahora.getMonth() + 1).padStart(2, '0');
    const dia = String(ahora.getDate()).padStart(2, '0');

    return `${ahora.getFullYear()}-${mes}-${dia}`;
}

const nombreTramo = (sentido: Sentido) =>
    sentido === 'ida' ? 'Ida' : 'Vuelta';

/**
 * Autorizar combustible (el QR de Repsol, o Kio). Nada se deduce: quien
 * registra elige el ticket de la unidad y el tramo, y solo se puede en un
 * tramo con su tarjeta confirmada. Lo que pase del tope pide motivo (y solo
 * lo autoriza quien tiene permiso). El precio se pone al autorizar (se
 * propone el último del grifo) y el voucher después lo confirma.
 */
export function RegistrarAbastecimientoDialog({
    open,
    onOpenChange,
    inicial,
    tractos,
    puntos,
    vueltas,
    puedeExcederTope,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    inicial: InicialAbastecimiento;
    tractos: TractoAbastecimiento[];
    puntos: PuntoRegistro[];
    /** Los tickets del tablero: de ahí sale cuánto le queda a cada tramo. */
    vueltas: VueltaAbastecimiento[];
    puedeExcederTope: boolean;
}) {
    const ticketsDe = (vehiculoId: number | null) =>
        tractos.find((tracto) => tracto.id === vehiculoId)?.tickets ?? [];
    const unicoTicket = (vehiculoId: number | null) => {
        const tickets = ticketsDe(vehiculoId);

        return tickets.length === 1 ? tickets[0].id : null;
    };
    const unicoTramo = (vueltaId: number | null): Sentido | '' => {
        const tramos =
            ticketsDe(inicial.vehiculo_id ?? null).find(
                (ticket) => ticket.id === vueltaId,
            )?.tramos ?? [];

        return tramos.length === 1 ? tramos[0] : '';
    };

    const vueltaInicial =
        inicial.vuelta_id ?? unicoTicket(inicial.vehiculo_id ?? null);
    const { data, setData, post, transform, processing, errors } =
        useForm<FormData>({
            vehiculo_id: inicial.vehiculo_id ?? null,
            vuelta_id: vueltaInicial,
            sentido: inicial.sentido ?? unicoTramo(vueltaInicial),
            punto_abastecimiento_id: null,
            punto_libre: '',
            fecha: hoy(),
            galones: inicial.galones ? String(inicial.galones) : '',
            tanque_lleno: false,
            precio_galon: '',
            motivo_exceso: '',
            observacion: '',
        });
    const [eligiendo, setEligiendo] = useState(false);
    // En un caso excepcional se carga en un grifo que no está en la lista:
    // se elige «Otro punto» y se escribe cuál.
    const [fueraDeLista, setFueraDeLista] = useState(false);

    const opcionesTractos: OpcionBuscable[] = tractos.map((tracto) => ({
        valor: tracto.id,
        etiqueta: tracto.placa,
        detalle:
            tracto.tickets.map((ticket) => ticket.codigo).join(' · ') ||
            undefined,
    }));
    const tickets = ticketsDe(data.vehiculo_id);
    const ticket = tickets.find((opcion) => opcion.id === data.vuelta_id);
    const tramo =
        data.sentido === ''
            ? undefined
            : vueltas
                  .find((vuelta) => vuelta.id === data.vuelta_id)
                  ?.tramos.find((opcion) => opcion.sentido === data.sentido);
    const queda =
        tramo?.previsto != null
            ? Math.max(0, tramo.previsto - tramo.registrado)
            : null;
    const importeEstimado =
        data.galones !== '' && data.precio_galon !== ''
            ? Number(data.galones) * Number(data.precio_galon)
            : null;
    // El tanque full de la ruta (Kio), si todavía no se cargó: se autoriza
    // sin galones y, con su voucher, descuenta del tope del tramo.
    const tanqueLlenoDisponible =
        tramo !== undefined &&
        tramo.tanque_lleno.puntos.length > 0 &&
        !tramo.tanque_lleno.usado;
    const llenando = tanqueLlenoDisponible && data.tanque_lleno;
    const excede =
        !llenando &&
        queda !== null &&
        data.galones !== '' &&
        Number(data.galones) > queda;

    const enviar = (evento: React.FormEvent) => {
        evento.preventDefault();
        // Si se cambió de tramo después de marcarlo, ya no es tanque full.
        transform((datos) => ({ ...datos, tanque_lleno: llenando }));
        post(store().url, {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="flex flex-col overflow-hidden md:max-h-[85dvh] md:max-w-md">
                {eligiendo ? (
                    <>
                        <DialogHeader className="sr-only">
                            <DialogTitle>Elegir unidad</DialogTitle>
                        </DialogHeader>
                        <PanelBuscador
                            titulo="Unidad"
                            valor={data.vehiculo_id}
                            opciones={opcionesTractos}
                            onElegir={(valor) => {
                                const vueltaId = unicoTicket(Number(valor));
                                const tramos =
                                    ticketsDe(Number(valor)).find(
                                        (opcion) => opcion.id === vueltaId,
                                    )?.tramos ?? [];

                                setData((anterior) => ({
                                    ...anterior,
                                    vehiculo_id: Number(valor),
                                    vuelta_id: vueltaId,
                                    sentido:
                                        tramos.length === 1 ? tramos[0] : '',
                                }));
                                setEligiendo(false);
                            }}
                            onVolver={() => setEligiendo(false)}
                        />
                    </>
                ) : (
                    <>
                        <DialogHeader>
                            <DialogTitle>Autorizar combustible</DialogTitle>
                            <DialogDescription>
                                Lo que se autoriza por el QR de Repsol o en Kio,
                                con su precio. Los galones reales llegan
                                después, con el voucher.
                            </DialogDescription>
                        </DialogHeader>

                        <form
                            id="registrar-abastecimiento"
                            onSubmit={enviar}
                            className="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto px-1"
                        >
                            <Field
                                label="Unidad"
                                error={errors.vehiculo_id}
                                required
                                ayuda={
                                    data.vehiculo_id !== null &&
                                    tickets.length === 0
                                        ? 'No tiene un ticket abierto: primero se programa.'
                                        : undefined
                                }
                            >
                                {(id) => (
                                    <CampoSelector
                                        id={id}
                                        etiqueta="Unidad"
                                        elegida={opcionesTractos.find(
                                            (opcion) =>
                                                opcion.valor ===
                                                data.vehiculo_id,
                                        )}
                                        invalido={Boolean(errors.vehiculo_id)}
                                        onAbrir={() => setEligiendo(true)}
                                    />
                                )}
                            </Field>

                            <div className="grid grid-cols-2 gap-4">
                                <Field
                                    label="Ticket"
                                    error={errors.vuelta_id}
                                    required
                                >
                                    {() => (
                                        <Select
                                            value={
                                                data.vuelta_id
                                                    ? String(data.vuelta_id)
                                                    : undefined
                                            }
                                            disabled={tickets.length === 0}
                                            onValueChange={(valor) => {
                                                const tramos =
                                                    tickets.find(
                                                        (opcion) =>
                                                            opcion.id ===
                                                            Number(valor),
                                                    )?.tramos ?? [];
                                                setData((anterior) => ({
                                                    ...anterior,
                                                    vuelta_id: Number(valor),
                                                    sentido:
                                                        tramos.length === 1
                                                            ? tramos[0]
                                                            : '',
                                                }));
                                            }}
                                        >
                                            <SelectTrigger className="w-full">
                                                <SelectValue placeholder="Elegir…" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {tickets.map((opcion) => (
                                                    <SelectItem
                                                        key={opcion.id}
                                                        value={String(
                                                            opcion.id,
                                                        )}
                                                    >
                                                        {opcion.codigo}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    )}
                                </Field>

                                <Field
                                    label="Tramo"
                                    error={errors.sentido}
                                    required
                                >
                                    {() => (
                                        <Select
                                            value={data.sentido || undefined}
                                            disabled={ticket === undefined}
                                            onValueChange={(valor) =>
                                                setData(
                                                    'sentido',
                                                    valor as Sentido,
                                                )
                                            }
                                        >
                                            <SelectTrigger className="w-full">
                                                <SelectValue placeholder="Elegir…" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {(
                                                    ['ida', 'regreso'] as const
                                                ).map((sentido) => (
                                                    <SelectItem
                                                        key={sentido}
                                                        value={sentido}
                                                        disabled={
                                                            !ticket?.tramos.includes(
                                                                sentido,
                                                            )
                                                        }
                                                    >
                                                        {nombreTramo(sentido)}
                                                        {!ticket?.tramos.includes(
                                                            sentido,
                                                        ) &&
                                                            ' · sin tarjeta confirmada'}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    )}
                                </Field>
                            </div>

                            {ticket?.bloqueo && (
                                <p className="rounded-md border border-amber-300 bg-amber-50 p-2 text-xs text-amber-900 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200">
                                    {ticket.bloqueo}
                                </p>
                            )}

                            <div className="grid grid-cols-2 gap-4">
                                <Field
                                    label="Punto"
                                    error={errors.punto_abastecimiento_id}
                                >
                                    {() => (
                                        <Select
                                            value={
                                                fueraDeLista
                                                    ? OTRO_PUNTO
                                                    : data.punto_abastecimiento_id
                                                      ? String(
                                                            data.punto_abastecimiento_id,
                                                        )
                                                      : undefined
                                            }
                                            onValueChange={(valor) => {
                                                const otro =
                                                    valor === OTRO_PUNTO;

                                                const ultimoPrecio = otro
                                                    ? null
                                                    : (puntos.find(
                                                          (punto) =>
                                                              punto.id ===
                                                              Number(valor),
                                                      )?.ultimo_precio ?? null);

                                                setFueraDeLista(otro);
                                                setData((anterior) => ({
                                                    ...anterior,
                                                    punto_abastecimiento_id:
                                                        otro
                                                            ? null
                                                            : Number(valor),
                                                    punto_libre: otro
                                                        ? anterior.punto_libre
                                                        : '',
                                                    // El último precio de ese
                                                    // grifo; si cambió, se
                                                    // corrige.
                                                    precio_galon:
                                                        ultimoPrecio !== null
                                                            ? String(
                                                                  ultimoPrecio,
                                                              )
                                                            : anterior.precio_galon,
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
                                                    Otro punto (fuera de la
                                                    lista)…
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    )}
                                </Field>

                                <Field
                                    label="Fecha"
                                    error={errors.fecha}
                                    required
                                >
                                    {(id) => (
                                        <Input
                                            id={id}
                                            type="date"
                                            value={data.fecha}
                                            onChange={(evento) =>
                                                setData(
                                                    'fecha',
                                                    evento.target.value,
                                                )
                                            }
                                        />
                                    )}
                                </Field>
                            </div>

                            {fueraDeLista && (
                                <Field
                                    label="¿Dónde cargó?"
                                    error={errors.punto_libre}
                                    required
                                    ayuda="Solo para casos excepcionales. Si se carga ahí seguido, agrégalo a la lista en la Pizarra."
                                >
                                    {(id) => (
                                        <Input
                                            id={id}
                                            autoFocus
                                            placeholder="Ej.: Primax Camaná"
                                            maxLength={80}
                                            value={data.punto_libre}
                                            onChange={(evento) =>
                                                setData(
                                                    'punto_libre',
                                                    evento.target.value,
                                                )
                                            }
                                        />
                                    )}
                                </Field>
                            )}

                            {tanqueLlenoDisponible && (
                                <label className="flex items-start gap-2 rounded-lg border border-sky-300 bg-sky-50 p-3 text-sm dark:border-sky-900 dark:bg-sky-950">
                                    <Checkbox
                                        checked={data.tanque_lleno}
                                        onCheckedChange={(marcado) => {
                                            const punto =
                                                tramo?.tanque_lleno.puntos[0];

                                            setFueraDeLista(false);
                                            setData((anterior) => ({
                                                ...anterior,
                                                tanque_lleno: marcado === true,
                                                galones: '',
                                                punto_abastecimiento_id:
                                                    marcado === true
                                                        ? (punto?.punto_id ??
                                                          anterior.punto_abastecimiento_id)
                                                        : anterior.punto_abastecimiento_id,
                                                punto_libre: '',
                                            }));
                                        }}
                                    />
                                    <span className="flex flex-col">
                                        <span className="font-medium">
                                            Tanque full en{' '}
                                            {tramo?.tanque_lleno.puntos
                                                .map((punto) => punto.punto)
                                                .join(' o ')}
                                        </span>
                                        <span className="text-xs text-muted-foreground">
                                            No suma al tope. Los galones los
                                            dice el voucher: se regulariza con
                                            su foto y el kilometraje.
                                        </span>
                                    </span>
                                </label>
                            )}
                            {errors.tanque_lleno && (
                                <p className="text-sm text-destructive">
                                    {errors.tanque_lleno}
                                </p>
                            )}

                            {!llenando && (
                                <Field
                                    label="Galones autorizados"
                                    error={errors.galones}
                                    required
                                    ayuda={
                                        queda !== null
                                            ? `Quedan ${galones(queda)} del tope de la tarjeta (${galones(tramo?.previsto ?? null)}).`
                                            : undefined
                                    }
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
                                                setData(
                                                    'galones',
                                                    evento.target.value,
                                                )
                                            }
                                        />
                                    )}
                                </Field>
                            )}

                            <Field
                                label="Precio por galón (S/)"
                                error={errors.precio_galon}
                                required
                                ayuda={
                                    importeEstimado !== null
                                        ? `Costo estimado: ${soles(importeEstimado)}. El voucher lo confirma.`
                                        : 'Se propone el último precio pagado en ese grifo.'
                                }
                            >
                                {(id) => (
                                    <Input
                                        id={id}
                                        type="number"
                                        inputMode="decimal"
                                        min="0"
                                        step="0.001"
                                        placeholder="Ej.: 16.40"
                                        value={data.precio_galon}
                                        onChange={(evento) =>
                                            setData(
                                                'precio_galon',
                                                evento.target.value,
                                            )
                                        }
                                    />
                                )}
                            </Field>

                            {(excede || errors.motivo_exceso) && (
                                <Field
                                    label="¿Por qué pasa el tope?"
                                    error={errors.motivo_exceso}
                                    required
                                    ayuda={
                                        puedeExcederTope
                                            ? 'Queda registrado quién lo autorizó y por qué.'
                                            : 'Pasar el tope lo autoriza solo quien tiene permiso: pídeselo.'
                                    }
                                >
                                    {(id) => (
                                        <Textarea
                                            id={id}
                                            rows={2}
                                            maxLength={500}
                                            disabled={!puedeExcederTope}
                                            placeholder="Ej.: desvío por huaico en Camaná"
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

                            <Field
                                label="Observación"
                                error={errors.observacion}
                            >
                                {(id) => (
                                    <Input
                                        id={id}
                                        value={data.observacion}
                                        onChange={(evento) =>
                                            setData(
                                                'observacion',
                                                evento.target.value,
                                            )
                                        }
                                    />
                                )}
                            </Field>
                        </form>

                        <DialogFooter>
                            <Button
                                type="submit"
                                form="registrar-abastecimiento"
                                disabled={
                                    processing ||
                                    data.vuelta_id === null ||
                                    data.sentido === '' ||
                                    data.precio_galon === '' ||
                                    Boolean(ticket?.bloqueo) ||
                                    (excede && !puedeExcederTope) ||
                                    (fueraDeLista &&
                                        data.punto_libre.trim() === '')
                                }
                            >
                                Autorizar
                            </Button>
                        </DialogFooter>
                    </>
                )}
            </DialogContent>
        </Dialog>
    );
}
