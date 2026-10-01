import { Head } from '@inertiajs/react';
import { AlertTriangle, CheckCircle2, Send } from 'lucide-react';
import { useState } from 'react';
import emision, {
    emitir as emitirGr,
    conductor as consultarConductor,
    vehiculo as consultarVehiculo,
} from '@/actions/App/Http/Controllers/EmisionGreController';
import viajes from '@/actions/App/Http/Controllers/ViajeController';
import { SelectorBuscable } from '@/components/selector-buscable';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
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
import { Spinner } from '@/components/ui/spinner';
import { consultar, hoy, tokenXsrf } from '@/components/viajes/emision/http';
import {
    NotaUltimoViaje,
    ResultadoDeEmision,
    Seccion,
    TuceDeVehiculo,
} from '@/components/viajes/emision/piezas';
import { SeccionGuias } from '@/components/viajes/emision/seccion-guias';
import { PAGADORES } from '@/components/viajes/emision/tipos';
import type {
    CampoUnidad,
    Consulta,
    GuiaRemitente,
    Mtc,
    PropsEmitirGr,
    ResultadoEmision,
    VerificacionConductor,
} from '@/components/viajes/emision/tipos';
import { formatearPlaca } from '@/lib/format';

/**
 * «JR. SANDIA 206 (JULIACA, SAN ROMAN, PUNO)» → «JULIACA»: el distrito, que
 * la vista previa pone entre paréntesis al final de la dirección.
 */
function ciudadDe(direccion: string | null | undefined): string {
    return direccion?.split('(').pop()?.split(',')[0]?.trim() ?? '';
}

export default function EmitirGr({
    tractos,
    carretas,
    conductores,
    clientes,
    sunatConfigurado,
    puedeEmitir,
    rucPaty,
    ultimos,
    remitentes,
}: PropsEmitirGr) {
    const [guias, setGuias] = useState<GuiaRemitente[]>([]);

    const [tractoId, setTractoId] = useState<number | null>(null);
    const [carretaId, setCarretaId] = useState<number | null>(null);
    const [mtc, setMtc] = useState<Record<number, Consulta<Mtc>>>({});
    // TUCE escrito a mano (o el RUC de Paty) por id de vehículo; si no hay,
    // va el que propone el servidor.
    const [tuces, setTuces] = useState<Record<number, string>>({});

    const [conductorId, setConductorId] = useState<number | null>(null);
    const [verificacion, setVerificacion] =
        useState<Consulta<VerificacionConductor> | null>(null);

    const [fechaTraslado, setFechaTraslado] = useState(hoy());
    const [pagador, setPagador] = useState<string>('remitente');
    const [rucPagador, setRucPagador] = useState('');
    // Cuando otra transportista (p. ej. Crisar) subcontrata a Paty: la GR
    // sale con «Transporte subcontratado: Sí» y sus datos (EG03-12627).
    const [subcontratado, setSubcontratado] = useState(false);
    const [rucSubcontratador, setRucSubcontratador] = useState('');
    // De dónde salió la subcontratación marcada sola, para decirlo; null si
    // la marcó la persona o no hay.
    const [subcontratoSugerido, setSubcontratoSugerido] = useState<
        'sunat' | 'historial' | null
    >(null);

    /**
     * Si la GR-remitente consigna a otra transportista (Crisar), Paty va
     * subcontratada por ella: lo dice SUNAT. Si SUNAT no lo dice, se usa el
     * historial: quién contrata a Paty en los viajes de ese remitente. Solo
     * se propone cuando todavía no se marcó nada a mano.
     */
    /** Nombre conocido de un RUC: padrón de clientes o GR anteriores. */
    const nombreDeRuc = (ruc: string): string | undefined =>
        clientes.find((cliente) => cliente.ruc === ruc)?.alias ??
        remitentes.find((remitente) => remitente.ruc === ruc)?.nombre ??
        remitentes.find((remitente) => remitente.contratante?.ruc === ruc)
            ?.contratante?.nombre;

    const sugerirSubcontratacion = (guia: GuiaRemitente) => {
        if (subcontratado) {
            return;
        }

        const porSunat =
            guia.transportistaRuc && guia.transportistaRuc !== rucPaty
                ? guia.transportistaRuc
                : null;
        const porHistorial =
            remitentes.find((remitente) => remitente.ruc === guia.ruc)
                ?.contratante?.ruc ?? null;
        const sugerido = porSunat ?? porHistorial;

        if (!sugerido) {
            return;
        }

        setSubcontratado(true);
        setRucSubcontratador(sugerido);
        setSubcontratoSugerido(porSunat ? 'sunat' : 'historial');

        if (pagador === 'remitente') {
            setPagador('subcontratador');
        }
    };

    const conductorElegido = conductores.find((c) => c.id === conductorId);
    const tracto = tractos.find((t) => t.id === tractoId);
    const carreta = carretas.find((c) => c.id === carretaId);

    // Lo que eligió la persona a mano. El autollenado solo toca los campos
    // que no están acá: elegir el tracto no pisa un conductor ya escogido.
    const [elegidosAMano, setElegidosAMano] = useState<Set<CampoUnidad>>(
        new Set(),
    );
    // Lo que se completó solo, para decirlo en pantalla.
    const [autollenados, setAutollenados] = useState<Set<CampoUnidad>>(
        new Set(),
    );

    const consultarTuce = async (id: number) => {
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

    const verificarConductor = async (id: number) => {
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

    /** Marca el campo como elegido a mano y deja de mostrarlo como sugerido. */
    const marcarAMano = (campo: CampoUnidad) => {
        setElegidosAMano((actual) => new Set(actual).add(campo));
        setAutollenados((actual) => {
            const siguiente = new Set(actual);
            siguiente.delete(campo);

            return siguiente;
        });
    };

    /**
     * Completa con la última combinación usada lo que la persona no eligió
     * a mano. No encadena: lo autollenado no dispara otro autollenado.
     */
    const autollenar = (propuesta: {
        tracto?: number | null;
        carreta?: number | null;
        conductor?: number | null;
    }) => {
        const nuevos: CampoUnidad[] = [];

        if (
            propuesta.tracto &&
            !elegidosAMano.has('tracto') &&
            tractos.some((t) => t.id === propuesta.tracto)
        ) {
            setTractoId(propuesta.tracto);
            void consultarTuce(propuesta.tracto);
            nuevos.push('tracto');
        }

        if (
            propuesta.carreta &&
            !elegidosAMano.has('carreta') &&
            carretas.some((c) => c.id === propuesta.carreta)
        ) {
            setCarretaId(propuesta.carreta);
            void consultarTuce(propuesta.carreta);
            nuevos.push('carreta');
        }

        if (
            propuesta.conductor &&
            !elegidosAMano.has('conductor') &&
            conductores.some((c) => c.id === propuesta.conductor)
        ) {
            setConductorId(propuesta.conductor);
            void verificarConductor(propuesta.conductor);
            nuevos.push('conductor');
        }

        if (nuevos.length > 0) {
            setAutollenados((actual) => new Set([...actual, ...nuevos]));
        }
    };

    const elegirTracto = (id: number) => {
        marcarAMano('tracto');
        setTractoId(id);
        void consultarTuce(id);
        autollenar(ultimos.porTracto[id] ?? {});
    };

    const elegirCarreta = (id: number) => {
        marcarAMano('carreta');
        setCarretaId(id);
        void consultarTuce(id);
    };

    const elegirConductor = (id: number) => {
        marcarAMano('conductor');
        setConductorId(id);
        void verificarConductor(id);
        autollenar(ultimos.porConductor[id] ?? {});
    };

    const [confirmando, setConfirmando] = useState(false);
    const [emitiendo, setEmitiendo] = useState(false);
    const [resultado, setResultado] = useState<ResultadoEmision | null>(null);

    // En una subcontratación la GR-remitente consigna como transportista a la
    // subcontratante, no a Paty: ese aviso deja de ser un problema.
    const avisosBloqueantes = guias.flatMap((g) =>
        g.avisos.filter(
            (aviso) =>
                !(
                    subcontratado &&
                    g.transportistaRuc === rucSubcontratador &&
                    aviso.startsWith('La GR-remitente consigna a otro')
                ),
        ),
    );
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
                    ruc_pagador: pagador === 'tercero' ? rucPagador : null,
                    ruc_subcontratador: subcontratado
                        ? rucSubcontratador
                        : null,
                    tuce_tracto: tractoId ? (tuces[tractoId] ?? null) : null,
                    tuce_carreta: carretaId ? (tuces[carretaId] ?? null) : null,
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
        subcontratado &&
            !/^\d{11}$/.test(rucSubcontratador) &&
            'el RUC de quien subcontrata',
        pagador === 'subcontratador' &&
            !subcontratado &&
            'marcar el transporte como subcontratado',
        pagador === 'tercero' &&
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

            <SeccionGuias
                remitentes={remitentes}
                sunatConfigurado={sunatConfigurado}
                guias={guias}
                onAgregada={(guia) => {
                    setGuias((actuales) => [...actuales, guia]);

                    // La primera guía propone la fecha: es el día que el
                    // remitente declaró para el traslado.
                    if (guias.length === 0 && guia.fechaTraslado) {
                        setFechaTraslado(guia.fechaTraslado);
                    }

                    sugerirSubcontratacion(guia);
                }}
                onQuitar={(guia) =>
                    setGuias((actuales) => actuales.filter((g) => g !== guia))
                }
            />

            <Seccion
                titulo="2. Unidad"
                descripcion="El TUCE sale de la ficha del vehículo; si no tiene, del MTC."
            >
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label="Tracto" required>
                        {(id) => (
                            <>
                                <SelectorBuscable
                                    id={id}
                                    valor={tractoId}
                                    onCambio={elegirTracto}
                                    etiqueta="Elegir tracto"
                                    opciones={tractos.map((t) => ({
                                        valor: t.id,
                                        etiqueta: formatearPlaca(t.placa),
                                        detalle: t.placa,
                                    }))}
                                />
                                {autollenados.has('tracto') && (
                                    <NotaUltimoViaje />
                                )}
                                {tractoId && (
                                    <TuceDeVehiculo
                                        consulta={mtc[tractoId]}
                                        elegido={tuces[tractoId]}
                                        rucPaty={rucPaty}
                                        onCambio={(valor) =>
                                            setTuces((actual) => ({
                                                ...actual,
                                                [tractoId]: valor,
                                            }))
                                        }
                                    />
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
                                    onCambio={elegirCarreta}
                                    etiqueta="Elegir carreta"
                                    opciones={carretas.map((c) => ({
                                        valor: c.id,
                                        etiqueta: formatearPlaca(c.placa),
                                        detalle: c.placa,
                                    }))}
                                />
                                {autollenados.has('carreta') && (
                                    <NotaUltimoViaje />
                                )}
                                {carretaId && (
                                    <TuceDeVehiculo
                                        consulta={mtc[carretaId]}
                                        elegido={tuces[carretaId]}
                                        rucPaty={rucPaty}
                                        onCambio={(valor) =>
                                            setTuces((actual) => ({
                                                ...actual,
                                                [carretaId]: valor,
                                            }))
                                        }
                                    />
                                )}
                            </>
                        )}
                    </Field>
                </div>
            </Seccion>

            <Seccion titulo="3. Conductor">
                <Field label="Conductor" required>
                    {(id) => (
                        <>
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
                            {autollenados.has('conductor') && (
                                <NotaUltimoViaje />
                            )}
                        </>
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
                    <label className="flex min-h-11 items-center gap-3 sm:col-span-2">
                        <Checkbox
                            checked={subcontratado}
                            onCheckedChange={(valor) => {
                                setSubcontratado(valor === true);
                                setSubcontratoSugerido(null);
                            }}
                        />
                        <span className="text-sm">
                            Transporte subcontratado
                            <span className="block text-xs text-muted-foreground">
                                Otra transportista contrató a Paty para este
                                traslado.
                            </span>
                        </span>
                    </label>
                    {subcontratado && (
                        <Field
                            label="RUC de quien subcontrata"
                            required
                            ayuda={[
                                nombreDeRuc(rucSubcontratador),
                                subcontratoSugerido === 'sunat' &&
                                    'Marcado solo: la GR-remitente consigna a esta transportista.',
                                subcontratoSugerido === 'historial' &&
                                    'Marcado solo: los viajes de este remitente llegan por ella.',
                            ]
                                .filter(Boolean)
                                .join(' · ')}
                        >
                            {(id) => (
                                <Input
                                    id={id}
                                    inputMode="numeric"
                                    maxLength={11}
                                    list="clientes-ruc"
                                    placeholder="20603930844"
                                    value={rucSubcontratador}
                                    onChange={(e) =>
                                        setRucSubcontratador(
                                            e.target.value.replace(/\D/g, ''),
                                        )
                                    }
                                />
                            )}
                        </Field>
                    )}
                    <datalist id="clientes-ruc">
                        {clientes.map((cliente) => (
                            <option key={cliente.ruc} value={cliente.ruc}>
                                {cliente.alias}
                            </option>
                        ))}
                    </datalist>
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
                                <SelectTrigger id={id} className="w-full">
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
                    {pagador === 'tercero' && (
                        <Field label="RUC de quien paga" required>
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
                <dl className="grid grid-cols-[6.5rem_minmax(0,1fr)] gap-x-3 gap-y-2 text-sm sm:grid-cols-[10rem_minmax(0,1fr)]">
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
                        {pagador === 'tercero' && rucPagador
                            ? ` (${rucPagador})`
                            : ''}
                    </dd>
                    {subcontratado && (
                        <>
                            <dt className="text-muted-foreground">
                                Subcontratado por
                            </dt>
                            <dd>
                                {clientes.find(
                                    (c) => c.ruc === rucSubcontratador,
                                )?.alias ?? 'RUC'}{' '}
                                {rucSubcontratador || '—'}
                            </dd>
                        </>
                    )}
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

                {/* En el celular el botón vive en la barra fija de abajo. */}
                <div className="mt-4 flex flex-col gap-2 max-md:hidden">
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

                {resultado && (
                    <ResultadoDeEmision
                        resultado={resultado}
                        detalle={[
                            `${fechaTraslado.split('-').reverse().join('/')} · ${ciudadDe(guias[0]?.partida)} → ${ciudadDe(guias[0]?.llegada)}`,
                            `${tracto ? formatearPlaca(tracto.placa) : ''}${carreta ? ` / ${formatearPlaca(carreta.placa)}` : ''} · ${conductorElegido?.nombre ?? ''}`,
                        ]}
                    />
                )}
            </Seccion>

            {/* La acción principal, siempre a mano en el celular: pegada
                encima de la BottomNav, con lo que falta al lado. */}
            <div aria-hidden className="h-20 md:hidden" />
            <div className="fixed inset-x-0 bottom-[calc(3.5rem+env(safe-area-inset-bottom))] z-30 flex items-center gap-3 border-t bg-background/95 px-4 py-2.5 backdrop-blur supports-[backdrop-filter]:bg-background/80 md:hidden">
                <p className="min-w-0 flex-1 truncate text-xs text-muted-foreground">
                    {bloqueos.length > 0
                        ? 'No se puede emitir: revisa los avisos.'
                        : faltantes.length > 0
                          ? `Falta: ${faltantes.join(', ')}.`
                          : resultado?.estado === 'emitida'
                            ? `Emitida ${resultado.numeroGr}.`
                            : 'Todo listo para emitir.'}
                </p>
                <Button
                    className="shrink-0"
                    disabled={!listaParaEmitir || faltantes.length > 0}
                    onClick={() => setConfirmando(true)}
                >
                    {emitiendo ? <Spinner /> : <Send className="size-4" />}
                    {emitiendo ? 'Emitiendo…' : 'Emitir GR'}
                </Button>
            </div>

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

EmitirGr.layout = {
    breadcrumbs: [
        { title: 'Viajes', href: viajes.index().url },
        { title: 'Emitir GR', href: emision.create().url },
    ],
};
