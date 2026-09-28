import { Head } from '@inertiajs/react';
import {
    AlertTriangle,
    CheckCircle2,
    FileText,
    Plus,
    Send,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import emision, {
    emitir as emitirGr,
    conductor as consultarConductor,
    guia as consultarGuia,
    vehiculo as consultarVehiculo,
} from '@/actions/App/Http/Controllers/EmisionGreController';
import viajes from '@/actions/App/Http/Controllers/ViajeController';
import { SelectorBuscable } from '@/components/selector-buscable';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
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
import { formatearPlaca } from '@/lib/format';

type Placa = { id: number; placa: string };

type ConductorOpcion = {
    id: number;
    nombre: string;
    documento: string;
    licencia: string | null;
};

type ClienteOpcion = { ruc: string; alias: string };

type Props = {
    tractos: Placa[];
    carretas: Placa[];
    conductores: ConductorOpcion[];
    clientes: ClienteOpcion[];
    sunatConfigurado: boolean;
    puedeEmitir: boolean;
};

type ResultadoEmision =
    | { estado: 'emitida'; numeroGr: string; viajeRegistrado: boolean }
    | { estado: 'en_duda' | 'rechazada' | 'no_enviada'; mensaje: string };

type GuiaRemitente = {
    ruc: string;
    serie: string;
    numero: number;
    completa: boolean;
    remitente: string | null;
    destinatario: string | null;
    destinatarioRuc: string | null;
    partida: string | null;
    llegada: string | null;
    peso: number | null;
    unidadPeso: string | null;
    bultos: number | null;
    motivo: string | null;
    fechaTraslado: string | null;
    transportistaRuc: string | null;
    avisos: string[];
};

type Mtc = { placa: string; tuce: string | null; vigente: boolean };

type VerificacionConductor = {
    dni: { encontrado: boolean; nombre: string | null; coincide: boolean };
    licencia: { encontrada: boolean; mensaje: string | null };
};

type Consulta<T> =
    | { estado: 'cargando' }
    | { estado: 'ok'; datos: T }
    | { estado: 'error'; mensaje: string };

/** `codigo` es el de SUNAT (parámetro 1024 del formulario de SOL). */
const PAGADORES = [
    { value: 'remitente', label: 'El remitente', codigo: '01' },
    { value: 'subcontratador', label: 'Un subcontratador', codigo: '02' },
    { value: 'tercero', label: 'Un tercero', codigo: '03' },
] as const;

/**
 * GET a un endpoint JSON de la app. Los errores traen `mensaje` (SUNAT caído,
 * GR inexistente) o, si es validación, el primero de `errors`.
 */
async function consultar<T>(url: string): Promise<T> {
    const respuesta = await fetch(url, {
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
    });
    const cuerpo = await respuesta.json().catch(() => ({}));

    if (!respuesta.ok) {
        const validacion = cuerpo.errors
            ? (Object.values(cuerpo.errors)[0] as string[] | undefined)?.[0]
            : undefined;

        throw new Error(
            cuerpo.mensaje ??
                validacion ??
                `No se pudo consultar (error ${respuesta.status}).`,
        );
    }

    return cuerpo as T;
}

/** El token CSRF que Laravel deja en la cookie XSRF-TOKEN. */
function tokenXsrf(): string {
    const cookie = document.cookie
        .split('; ')
        .find((par) => par.startsWith('XSRF-TOKEN='));

    return cookie ? decodeURIComponent(cookie.split('=')[1]) : '';
}

function hoy(): string {
    const fecha = new Date();
    fecha.setMinutes(fecha.getMinutes() - fecha.getTimezoneOffset());

    return fecha.toISOString().slice(0, 10);
}

export default function EmitirGr({
    tractos,
    carretas,
    conductores,
    clientes,
    sunatConfigurado,
    puedeEmitir,
}: Props) {
    const [ruc, setRuc] = useState('');
    const [serie, setSerie] = useState('');
    const [numero, setNumero] = useState('');
    const [buscandoGuia, setBuscandoGuia] = useState(false);
    const [errorGuia, setErrorGuia] = useState<string | null>(null);
    const [guias, setGuias] = useState<GuiaRemitente[]>([]);

    const [tractoId, setTractoId] = useState<number | null>(null);
    const [carretaId, setCarretaId] = useState<number | null>(null);
    const [mtc, setMtc] = useState<Record<number, Consulta<Mtc>>>({});

    const [conductorId, setConductorId] = useState<number | null>(null);
    const [verificacion, setVerificacion] =
        useState<Consulta<VerificacionConductor> | null>(null);

    const [fechaTraslado, setFechaTraslado] = useState(hoy());
    const [pagador, setPagador] = useState<string>('remitente');
    const [rucPagador, setRucPagador] = useState('');

    const conductorElegido = conductores.find((c) => c.id === conductorId);
    const tracto = tractos.find((t) => t.id === tractoId);
    const carreta = carretas.find((c) => c.id === carretaId);

    const agregarGuia = async (event: React.FormEvent) => {
        event.preventDefault();
        setErrorGuia(null);

        const serieNormal = serie.trim().toUpperCase();
        const numeroNormal = Number(numero);

        if (
            guias.some(
                (g) =>
                    g.ruc === ruc &&
                    g.serie === serieNormal &&
                    g.numero === numeroNormal,
            )
        ) {
            setErrorGuia('Esa GR-remitente ya está en la lista.');

            return;
        }

        setBuscandoGuia(true);

        try {
            const guia = await consultar<GuiaRemitente>(
                consultarGuia.url({
                    query: {
                        ruc: ruc.trim(),
                        serie: serieNormal,
                        numero: numero.trim(),
                    },
                }),
            );
            setGuias((actuales) => [...actuales, guia]);

            // La primera guía propone la fecha: es el día que el remitente
            // declaró para el traslado.
            if (guias.length === 0 && guia.fechaTraslado) {
                setFechaTraslado(guia.fechaTraslado);
            }

            setNumero('');
        } catch (error) {
            setErrorGuia((error as Error).message);
        } finally {
            setBuscandoGuia(false);
        }
    };

    const elegirVehiculo = async (
        id: number,
        guardar: (id: number) => void,
    ) => {
        guardar(id);

        if (mtc[id]?.estado === 'ok') {
            return;
        }

        setMtc((actual) => ({ ...actual, [id]: { estado: 'cargando' } }));

        try {
            const datos = await consultar<Mtc>(consultarVehiculo.url(id));
            setMtc((actual) => ({ ...actual, [id]: { estado: 'ok', datos } }));
        } catch (error) {
            setMtc((actual) => ({
                ...actual,
                [id]: { estado: 'error', mensaje: (error as Error).message },
            }));
        }
    };

    const elegirConductor = async (id: number) => {
        setConductorId(id);
        setVerificacion({ estado: 'cargando' });

        try {
            const datos = await consultar<VerificacionConductor>(
                consultarConductor.url(id),
            );
            setVerificacion({ estado: 'ok', datos });
        } catch (error) {
            setVerificacion({
                estado: 'error',
                mensaje: (error as Error).message,
            });
        }
    };

    const [confirmando, setConfirmando] = useState(false);
    const [emitiendo, setEmitiendo] = useState(false);
    const [resultado, setResultado] = useState<ResultadoEmision | null>(null);

    const avisosBloqueantes = guias.flatMap((g) => g.avisos);
    // Estos no se emiten ni confirmando: el servidor también los frena.
    const bloqueos = avisosBloqueantes.filter(
        (aviso) =>
            aviso.startsWith('Ya tiene GR-transportista') ||
            aviso.includes('no está vigente'),
    );

    const emitir = async () => {
        setEmitiendo(true);
        setResultado(null);

        try {
            const respuesta = await fetch(emitirGr.url(), {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-XSRF-TOKEN': tokenXsrf(),
                },
                body: JSON.stringify({
                    guias: guias.map((g) => ({
                        ruc: g.ruc,
                        serie: g.serie,
                        numero: g.numero,
                    })),
                    tracto_id: tractoId,
                    carreta_id: carretaId,
                    conductor_id: conductorId,
                    fecha_traslado: fechaTraslado,
                    pagador: PAGADORES.find((p) => p.value === pagador)?.codigo,
                    ruc_pagador: pagador === 'remitente' ? null : rucPagador,
                }),
            });
            const cuerpo = await respuesta.json().catch(() => null);

            setResultado(
                cuerpo?.estado
                    ? (cuerpo as ResultadoEmision)
                    : {
                          // Sin respuesta legible no se sabe qué pasó en SUNAT.
                          estado: 'en_duda',
                          mensaje: `El servidor respondió ${respuesta.status} sin detalle. La GR pudo haberse emitido: revisa «Consulta de GRE» en SOL antes de volver a intentar.`,
                      },
            );
        } catch {
            setResultado({
                estado: 'en_duda',
                mensaje:
                    'Se perdió la conexión mientras se emitía. La GR pudo haberse emitido: revisa «Consulta de GRE» en SOL antes de volver a intentar.',
            });
        } finally {
            setEmitiendo(false);
            setConfirmando(false);
        }
    };

    // Emitida o en duda: el botón no vuelve a habilitarse sin recargar, para
    // que nadie mande la misma GR dos veces.
    const cerrada =
        resultado?.estado === 'emitida' || resultado?.estado === 'en_duda';
    const listaParaEmitir =
        puedeEmitir &&
        sunatConfigurado &&
        bloqueos.length === 0 &&
        !emitiendo &&
        !cerrada;
    const faltantes = [
        guias.length === 0 && 'al menos una GR-remitente',
        !tractoId && 'el tracto',
        !conductorId && 'el conductor',
        pagador !== 'remitente' &&
            !/^\d{11}$/.test(rucPagador) &&
            'el RUC de quien paga el flete',
    ].filter(Boolean) as string[];

    return (
        <div className="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4 md:p-6">
            <Head title="Emitir GR" />

            <p className="text-sm text-muted-foreground">
                Arma la GR-transportista con los datos que SUNAT confirma en
                vivo: la GR-remitente, el TUCE de cada placa y el DNI del
                conductor, y la emite en SUNAT.
            </p>

            {!sunatConfigurado && (
                <Alert variant="destructive">
                    <AlertTriangle className="size-4" />
                    <AlertTitle>Falta el usuario SOL</AlertTitle>
                    <AlertDescription>
                        Configura SUNAT_SOL_RUC, SUNAT_SOL_USUARIO y
                        SUNAT_SOL_CLAVE en el servidor para poder consultar.
                    </AlertDescription>
                </Alert>
            )}

            <Seccion
                titulo="1. GR-remitente"
                descripcion="SUNAT completa remitente, destinatario, partida, llegada y peso."
            >
                <form onSubmit={agregarGuia} className="grid gap-4">
                    <Field label="Cliente (remitente)" required>
                        {(id) => (
                            <div className="grid gap-2 sm:grid-cols-[1fr_10rem]">
                                <Select
                                    value={
                                        clientes.some((c) => c.ruc === ruc)
                                            ? ruc
                                            : ''
                                    }
                                    onValueChange={setRuc}
                                >
                                    <SelectTrigger id={id}>
                                        <SelectValue placeholder="Elegir cliente o escribir el RUC" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {clientes.map((cliente) => (
                                            <SelectItem
                                                key={cliente.ruc}
                                                value={cliente.ruc}
                                            >
                                                {cliente.alias} · {cliente.ruc}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <Input
                                    aria-label="RUC del remitente"
                                    inputMode="numeric"
                                    maxLength={11}
                                    placeholder="RUC"
                                    value={ruc}
                                    onChange={(e) =>
                                        setRuc(
                                            e.target.value.replace(/\D/g, ''),
                                        )
                                    }
                                />
                            </div>
                        )}
                    </Field>

                    <div className="grid grid-cols-[6rem_1fr_auto] items-end gap-2">
                        <Field label="Serie" required>
                            {(id) => (
                                <Input
                                    id={id}
                                    maxLength={4}
                                    placeholder="T007"
                                    value={serie}
                                    onChange={(e) =>
                                        setSerie(e.target.value.toUpperCase())
                                    }
                                />
                            )}
                        </Field>
                        <Field label="Número" required>
                            {(id) => (
                                <Input
                                    id={id}
                                    inputMode="numeric"
                                    placeholder="10088"
                                    value={numero}
                                    onChange={(e) =>
                                        setNumero(
                                            e.target.value.replace(/\D/g, ''),
                                        )
                                    }
                                />
                            )}
                        </Field>
                        <Button
                            type="submit"
                            disabled={
                                buscandoGuia ||
                                !sunatConfigurado ||
                                ruc.length !== 11 ||
                                serie.length !== 4 ||
                                numero === ''
                            }
                        >
                            {buscandoGuia ? (
                                <Spinner />
                            ) : (
                                <Plus className="size-4" />
                            )}
                            Agregar
                        </Button>
                    </div>

                    {errorGuia && (
                        <p className="text-sm text-destructive">{errorGuia}</p>
                    )}
                </form>

                {guias.length > 0 && (
                    <ul className="mt-4 grid gap-3">
                        {guias.map((guia) => (
                            <TarjetaGuia
                                key={`${guia.ruc}-${guia.serie}-${guia.numero}`}
                                guia={guia}
                                onQuitar={() =>
                                    setGuias((actuales) =>
                                        actuales.filter((g) => g !== guia),
                                    )
                                }
                            />
                        ))}
                    </ul>
                )}
            </Seccion>

            <Seccion
                titulo="2. Unidad"
                descripcion="El TUCE lo trae el MTC a partir de la placa."
            >
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label="Tracto" required>
                        {(id) => (
                            <>
                                <SelectorBuscable
                                    id={id}
                                    valor={tractoId}
                                    onCambio={(valor) =>
                                        elegirVehiculo(valor, setTractoId)
                                    }
                                    etiqueta="Elegir tracto"
                                    opciones={tractos.map((t) => ({
                                        valor: t.id,
                                        etiqueta: formatearPlaca(t.placa),
                                        detalle: t.placa,
                                    }))}
                                />
                                {tractoId && (
                                    <EstadoMtc consulta={mtc[tractoId]} />
                                )}
                            </>
                        )}
                    </Field>
                    <Field label="Carreta">
                        {(id) => (
                            <>
                                <SelectorBuscable
                                    id={id}
                                    valor={carretaId}
                                    onCambio={(valor) =>
                                        elegirVehiculo(valor, setCarretaId)
                                    }
                                    etiqueta="Elegir carreta"
                                    opciones={carretas.map((c) => ({
                                        valor: c.id,
                                        etiqueta: formatearPlaca(c.placa),
                                        detalle: c.placa,
                                    }))}
                                />
                                {carretaId && (
                                    <EstadoMtc consulta={mtc[carretaId]} />
                                )}
                            </>
                        )}
                    </Field>
                </div>
            </Seccion>

            <Seccion titulo="3. Conductor">
                <Field label="Conductor" required>
                    {(id) => (
                        <SelectorBuscable
                            id={id}
                            valor={conductorId}
                            onCambio={elegirConductor}
                            etiqueta="Elegir conductor"
                            opciones={conductores.map((c) => ({
                                valor: c.id,
                                etiqueta: c.nombre,
                                detalle: c.documento,
                            }))}
                        />
                    )}
                </Field>
                {conductorElegido && (
                    <div className="mt-3 grid gap-1 text-sm">
                        <p>
                            DNI{' '}
                            <span className="font-medium">
                                {conductorElegido.documento}
                            </span>{' '}
                            · Licencia{' '}
                            <span className="font-medium">
                                {conductorElegido.licencia ?? '—'}
                            </span>
                        </p>
                        {verificacion?.estado === 'cargando' && (
                            <p className="flex items-center gap-2 text-muted-foreground">
                                <Spinner /> Verificando el DNI en RENIEC…
                            </p>
                        )}
                        {verificacion?.estado === 'ok' &&
                            (verificacion.datos.dni.coincide ? (
                                <p className="flex items-center gap-1 text-emerald-600">
                                    <CheckCircle2 className="size-4" /> DNI
                                    verificado: {verificacion.datos.dni.nombre}
                                </p>
                            ) : verificacion.datos.dni.encontrado ? (
                                <p className="text-destructive">
                                    El DNI corresponde a{' '}
                                    {verificacion.datos.dni.nombre}, no a{' '}
                                    {conductorElegido.nombre}. Corrige el DNI en
                                    la ficha del conductor.
                                </p>
                            ) : (
                                <p className="text-destructive">
                                    RENIEC no encontró el DNI{' '}
                                    {conductorElegido.documento}.
                                </p>
                            ))}
                        {verificacion?.estado === 'ok' &&
                            !verificacion.datos.licencia.encontrada && (
                                <p className="text-xs text-muted-foreground">
                                    La licencia se envía tal cual: la consulta
                                    del MTC hoy no reconoce licencias, tampoco
                                    desde SOL.
                                </p>
                            )}
                        {verificacion?.estado === 'error' && (
                            <p className="text-destructive">
                                {verificacion.mensaje}
                            </p>
                        )}
                    </div>
                )}
            </Seccion>

            <Seccion titulo="4. Traslado y flete">
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label="Fecha de inicio del traslado" required>
                        {(id) => (
                            <Input
                                id={id}
                                type="date"
                                value={fechaTraslado}
                                onChange={(e) =>
                                    setFechaTraslado(e.target.value)
                                }
                            />
                        )}
                    </Field>
                    <Field label="¿Quién paga el flete?" required>
                        {(id) => (
                            <Select value={pagador} onValueChange={setPagador}>
                                <SelectTrigger id={id}>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {PAGADORES.map((opcion) => (
                                        <SelectItem
                                            key={opcion.value}
                                            value={opcion.value}
                                        >
                                            {opcion.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        )}
                    </Field>
                    {pagador !== 'remitente' && (
                        <Field
                            label={
                                pagador === 'subcontratador'
                                    ? 'RUC del subcontratador'
                                    : 'RUC de quien paga'
                            }
                            required
                        >
                            {(id) => (
                                <Input
                                    id={id}
                                    inputMode="numeric"
                                    maxLength={11}
                                    value={rucPagador}
                                    onChange={(e) =>
                                        setRucPagador(
                                            e.target.value.replace(/\D/g, ''),
                                        )
                                    }
                                />
                            )}
                        </Field>
                    )}
                </div>
            </Seccion>

            <Seccion titulo="5. Vista previa">
                <dl className="grid gap-2 text-sm sm:grid-cols-[10rem_1fr]">
                    <dt className="text-muted-foreground">GR-remitente</dt>
                    <dd>
                        {guias.length > 0
                            ? guias
                                  .map((g) => `${g.serie}-${g.numero}`)
                                  .join(', ')
                            : '—'}
                    </dd>
                    <dt className="text-muted-foreground">Ruta</dt>
                    <dd>
                        {guias[0]
                            ? `${guias[0].partida ?? '—'} → ${guias[0].llegada ?? '—'}`
                            : '—'}
                    </dd>
                    <dt className="text-muted-foreground">Peso</dt>
                    <dd>
                        {guias.length > 0
                            ? `${guias.reduce((t, g) => t + (g.peso ?? 0), 0).toFixed(3)} ${guias[0].unidadPeso ?? ''}`
                            : '—'}
                    </dd>
                    <dt className="text-muted-foreground">Unidad</dt>
                    <dd>
                        {tracto ? formatearPlaca(tracto.placa) : '—'}
                        {carreta ? ` / ${formatearPlaca(carreta.placa)}` : ''}
                    </dd>
                    <dt className="text-muted-foreground">Conductor</dt>
                    <dd>{conductorElegido?.nombre ?? '—'}</dd>
                    <dt className="text-muted-foreground">Traslado</dt>
                    <dd>
                        {fechaTraslado} · paga{' '}
                        {PAGADORES.find(
                            (p) => p.value === pagador,
                        )?.label.toLowerCase()}
                        {pagador !== 'remitente' && rucPagador
                            ? ` (${rucPagador})`
                            : ''}
                    </dd>
                </dl>

                {faltantes.length > 0 && (
                    <p className="mt-4 text-sm text-muted-foreground">
                        Falta: {faltantes.join(', ')}.
                    </p>
                )}
                {avisosBloqueantes.length > 0 && (
                    <p className="mt-2 text-sm text-destructive">
                        Revisa los avisos de las GR-remitente antes de emitir.
                    </p>
                )}

                <div className="mt-4 flex flex-col gap-2">
                    <Button
                        className="w-full sm:w-auto"
                        disabled={!listaParaEmitir || faltantes.length > 0}
                        onClick={() => setConfirmando(true)}
                    >
                        {emitiendo ? <Spinner /> : <Send className="size-4" />}
                        {emitiendo
                            ? 'Emitiendo en SUNAT…'
                            : 'Emitir GR en SUNAT'}
                    </Button>
                    {!puedeEmitir && (
                        <p className="text-xs text-muted-foreground">
                            Tu usuario no tiene el permiso «Emitir GR en SUNAT».
                        </p>
                    )}
                    {bloqueos.length > 0 && (
                        <p className="text-xs text-destructive">
                            No se puede emitir: {bloqueos.join(' ')}
                        </p>
                    )}
                </div>

                {resultado && <ResultadoDeEmision resultado={resultado} />}
            </Seccion>

            <Dialog open={confirmando} onOpenChange={setConfirmando}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>¿Emitir la GR en SUNAT?</DialogTitle>
                        <DialogDescription>
                            Se emite una GR-transportista real. No se puede
                            editar: si sale mal, hay que darla de baja en SOL.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-1 text-sm">
                        <p>
                            <span className="text-muted-foreground">
                                GR-remitente:
                            </span>{' '}
                            {guias
                                .map((g) => `${g.serie}-${g.numero}`)
                                .join(', ')}
                        </p>
                        <p>
                            <span className="text-muted-foreground">
                                Unidad:
                            </span>{' '}
                            {tracto ? formatearPlaca(tracto.placa) : '—'}
                            {carreta
                                ? ` / ${formatearPlaca(carreta.placa)}`
                                : ''}
                        </p>
                        <p>
                            <span className="text-muted-foreground">
                                Conductor:
                            </span>{' '}
                            {conductorElegido?.nombre}
                        </p>
                        <p>
                            <span className="text-muted-foreground">
                                Traslado:
                            </span>{' '}
                            {fechaTraslado}
                        </p>
                        {avisosBloqueantes.length > 0 && (
                            <ul className="mt-2 grid gap-1 text-destructive">
                                {avisosBloqueantes.map((aviso) => (
                                    <li key={aviso}>⚠ {aviso}</li>
                                ))}
                            </ul>
                        )}
                    </div>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            disabled={emitiendo}
                            onClick={() => setConfirmando(false)}
                        >
                            Cancelar
                        </Button>
                        <Button disabled={emitiendo} onClick={emitir}>
                            {emitiendo ? (
                                <Spinner />
                            ) : (
                                <Send className="size-4" />
                            )}
                            Sí, emitir
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}

function ResultadoDeEmision({ resultado }: { resultado: ResultadoEmision }) {
    if (resultado.estado === 'emitida') {
        return (
            <Alert className="mt-4 border-emerald-600/40">
                <CheckCircle2 className="size-4 text-emerald-600" />
                <AlertTitle>Emitida {resultado.numeroGr}</AlertTitle>
                <AlertDescription>
                    {resultado.viajeRegistrado
                        ? 'El viaje ya está registrado en Viajes con el PDF de SUNAT.'
                        : 'SUNAT la emitió, pero no se pudo bajar el PDF: el viaje entra cuando se suba la GR.'}{' '}
                    <a
                        className="underline"
                        href={viajes.index.url({
                            query: { buscar: resultado.numeroGr },
                        })}
                    >
                        Ver en Viajes
                    </a>
                </AlertDescription>
            </Alert>
        );
    }

    return (
        <Alert variant="destructive" className="mt-4">
            <AlertTriangle className="size-4" />
            <AlertTitle>
                {resultado.estado === 'en_duda'
                    ? 'No se sabe si se emitió'
                    : 'No se emitió'}
            </AlertTitle>
            <AlertDescription>{resultado.mensaje}</AlertDescription>
        </Alert>
    );
}

function Seccion({
    titulo,
    descripcion,
    children,
}: {
    titulo: string;
    descripcion?: string;
    children: React.ReactNode;
}) {
    return (
        <section className="rounded-xl border border-border bg-card p-4 sm:p-5">
            <div className="mb-4">
                <h2 className="text-sm font-semibold text-foreground">
                    {titulo}
                </h2>
                {descripcion && (
                    <p className="text-xs text-muted-foreground">
                        {descripcion}
                    </p>
                )}
            </div>
            {children}
        </section>
    );
}

function TarjetaGuia({
    guia,
    onQuitar,
}: {
    guia: GuiaRemitente;
    onQuitar: () => void;
}) {
    return (
        <li className="rounded-lg border border-border p-3 text-sm">
            <div className="flex items-start justify-between gap-2">
                <div className="flex items-center gap-2 font-medium">
                    <FileText className="size-4 text-muted-foreground" />
                    {guia.serie}-{guia.numero}
                    {!guia.completa && (
                        <span className="text-xs font-normal text-amber-600">
                            (resumida)
                        </span>
                    )}
                </div>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    aria-label="Quitar GR-remitente"
                    onClick={onQuitar}
                >
                    <Trash2 className="size-4" />
                </Button>
            </div>
            <dl className="mt-1 grid gap-1 text-muted-foreground">
                <div>
                    <dt className="sr-only">Remitente y destinatario</dt>
                    <dd>
                        {guia.remitente ?? '—'} → {guia.destinatario ?? '—'}
                    </dd>
                </div>
                <div>
                    <dt className="sr-only">Ruta</dt>
                    <dd>
                        {guia.partida ?? '—'}
                        <br />→ {guia.llegada ?? '—'}
                    </dd>
                </div>
                <div>
                    <dt className="sr-only">Carga</dt>
                    <dd>
                        {guia.peso ?? '—'} {guia.unidadPeso ?? ''}
                        {guia.bultos ? ` · ${guia.bultos} bultos` : ''}
                        {guia.fechaTraslado
                            ? ` · traslado ${guia.fechaTraslado}`
                            : ''}
                    </dd>
                </div>
            </dl>
            {guia.avisos.length > 0 && (
                <ul className="mt-2 grid gap-1">
                    {guia.avisos.map((aviso) => (
                        <li
                            key={aviso}
                            className="flex items-start gap-1 text-destructive"
                        >
                            <AlertTriangle className="mt-0.5 size-4 shrink-0" />
                            {aviso}
                        </li>
                    ))}
                </ul>
            )}
        </li>
    );
}

function EstadoMtc({ consulta }: { consulta?: Consulta<Mtc> }) {
    if (!consulta || consulta.estado === 'cargando') {
        return (
            <p className="flex items-center gap-2 text-xs text-muted-foreground">
                <Spinner /> Consultando el MTC…
            </p>
        );
    }

    if (consulta.estado === 'error') {
        return <p className="text-xs text-destructive">{consulta.mensaje}</p>;
    }

    if (!consulta.datos.tuce) {
        return (
            <p className="text-xs text-destructive">
                El MTC no tiene TUCE para esta placa.
            </p>
        );
    }

    return (
        <p
            className={
                consulta.datos.vigente
                    ? 'flex items-center gap-1 text-xs text-emerald-600'
                    : 'text-xs text-destructive'
            }
        >
            {consulta.datos.vigente && <CheckCircle2 className="size-3.5" />}
            TUCE {consulta.datos.tuce}
            {consulta.datos.vigente ? ' · vigente' : ' · NO vigente'}
        </p>
    );
}

EmitirGr.layout = {
    breadcrumbs: [
        { title: 'Viajes', href: viajes.index().url },
        { title: 'Emitir GR', href: emision.create().url },
    ],
};
