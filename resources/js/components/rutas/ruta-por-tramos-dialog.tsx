import { Link, router } from '@inertiajs/react';
import { ArrowRight, Plus, X } from 'lucide-react';
import { useState } from 'react';
import tramosRutas from '@/actions/App/Http/Controllers/GalonesPorTramoController';
import rutas from '@/actions/App/Http/Controllers/RutaController';
import { useConfirmar } from '@/components/confirmar';
import InputError from '@/components/input-error';
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
import { Textarea } from '@/components/ui/textarea';
import { filaDeLaTabla } from '@/lib/tabla-de-tramos';
import { cn } from '@/lib/utils';
import type {
    GalonesTramo,
    LineaRuta,
    OpcionesRutas,
    RutaCatalogo,
} from '@/types/abastecimiento';

/** El valor de los selectores para «ninguno»: Radix no acepta vacío. */
const NINGUNO = '__ninguno';
/** De dónde sale la ida: la base y la mina. */
const BASES = ['juliaca', 'mina'];

type Tramo = {
    cargado: boolean;
    /** Con qué va cuando va cargado. */
    carga: string;
    lugar_origen?: string | null;
    lugar_destino?: string | null;
};

/**
 * Si la ruta se puede editar con las tres preguntas: va por tramos, sin
 * variantes, y su recorrido es una cadena de lugares (cada tramo sale de
 * donde llegó el anterior, con destino fijo).
 */
export function esRutaSimple(ruta: RutaCatalogo): boolean {
    return (
        ruta.por_tramos &&
        !ruta.es_complemento &&
        // Las variantes de una ruta por tramos son las bandas de la tabla;
        // con líneas propias por variante hace falta el editor completo.
        ruta.lineas.every((linea) => linea.variante === null) &&
        ruta.paradas.length > 0 &&
        ruta.paradas.every(
            (parada, indice) =>
                parada.zona_destino !== null &&
                (indice === 0 ||
                    parada.zona_origen ===
                        ruta.paradas[indice - 1].zona_destino),
        )
    );
}

/** Los galones de un tramo en la tabla; null si falta ese número. */
function enLaTabla(
    tabla: GalonesTramo[],
    origen: string,
    destino: string,
    cargado: boolean,
    carga: string | null,
): number | null {
    if (origen === destino) {
        return 0;
    }

    const fila = filaDeLaTabla(tabla, origen, destino, cargado ? carga : null);

    if (!fila) {
        return null;
    }

    return cargado ? fila.galones_cargado : fila.galones_vacio;
}

const clave = (origen: string, destino: string, cargado: boolean) =>
    `${origen}>${destino}:${cargado ? 'cargado' : 'vacio'}`;

function Pregunta({
    numero,
    titulo,
    ayuda,
    children,
}: {
    numero: number;
    titulo: string;
    ayuda: string;
    children: React.ReactNode;
}) {
    return (
        <section className="flex flex-col gap-3 rounded-xl border p-4">
            <div className="flex items-start gap-3">
                <span className="flex size-7 shrink-0 items-center justify-center rounded-full bg-primary text-sm font-semibold text-primary-foreground">
                    {numero}
                </span>
                <div>
                    <h3 className="text-base font-semibold">{titulo}</h3>
                    <p className="text-xs text-muted-foreground">{ayuda}</p>
                </div>
            </div>
            {children}
        </section>
    );
}

/**
 * Crear o editar una ruta en tres preguntas: por dónde pasa, cuánto gasta y
 * qué más lleva. Los galones no se escriben en la ruta: salen de la tabla
 * «Galones por tramo»; si un tramo todavía no está ahí, se pregunta acá una
 * sola vez y queda en la tabla para las demás rutas.
 */
export function RutaPorTramosDialog({
    ruta,
    opciones,
    onCerrar,
    onFormularioCompleto,
}: {
    ruta: RutaCatalogo | null;
    opciones: OpcionesRutas;
    onCerrar: () => void;
    /** Abre el editor completo (variantes por peso, complementos, líneas). */
    onFormularioCompleto: () => void;
}) {
    const confirmar = useConfirmar();
    const cargas = opciones.cargas.filter((carga) => carga.value !== 'vacio');
    const lineaFull =
        ruta?.lineas.find(
            (linea) => linea.tipo === 'combustible' && linea.tanque_lleno,
        ) ?? null;
    const lineaViaticos =
        ruta?.lineas.find((linea) => linea.tipo === 'viatico') ?? null;
    // Lo demás que la ruta ya tenga (un peaje, otro viático) se conserva.
    const otrasLineas: LineaRuta[] =
        ruta?.lineas.filter(
            (linea) =>
                linea !== lineaFull &&
                linea !== lineaViaticos &&
                (linea.tipo !== 'combustible' || linea.tanque_lleno),
        ) ?? [];

    const [lugares, setLugares] = useState<string[]>(
        ruta
            ? [
                  ruta.paradas[0].zona_origen,
                  ...ruta.paradas.map((parada) => parada.zona_destino ?? ''),
              ]
            : ['', ''],
    );
    const [tramos, setTramos] = useState<Tramo[]>(
        ruta
            ? ruta.paradas.map((parada) => ({
                  cargado: parada.tipo_carga !== 'vacio',
                  carga:
                      parada.tipo_carga === 'vacio'
                          ? 'particular'
                          : parada.tipo_carga,
                  lugar_origen: parada.lugar_origen,
                  lugar_destino: parada.lugar_destino,
              }))
            : [{ cargado: true, carga: 'particular' }],
    );
    const [nuevos, setNuevos] = useState<Record<string, string>>({});
    const [viaticos, setViaticos] = useState(
        lineaViaticos?.monto?.toString() ?? '',
    );
    const [puntoFull, setPuntoFull] = useState(
        lineaFull?.punto_abastecimiento_id?.toString() ?? '',
    );
    const [nombre, setNombre] = useState(ruta?.nombre ?? '');
    const [nombreEscrito, setNombreEscrito] = useState(ruta !== null);
    const [sentido, setSentido] = useState<string | null>(
        ruta ? (ruta.sentido ?? '') : null,
    );
    const [dias, setDias] = useState(ruta?.dias_viaje?.toString() ?? '');
    const [km, setKm] = useState(ruta?.km_estandar?.toString() ?? '');
    const [nota, setNota] = useState(ruta?.nota ?? '');
    const [activa, setActiva] = useState(ruta?.activa ?? true);
    const [errores, setErrores] = useState<Record<string, string>>({});
    const [guardando, setGuardando] = useState(false);

    const zona = (valor: string) =>
        opciones.zonas.find((opcion) => opcion.value === valor)?.label ?? '¿?';
    const completo = lugares.every((lugar) => lugar !== '');

    // Cada tramo con lo que dice la tabla, o lo que se acaba de escribir.
    const filas = tramos.map((tramo, indice) => {
        const origen = lugares[indice];
        const destino = lugares[indice + 1];
        const tabla =
            origen && destino
                ? enLaTabla(
                      opciones.tramos,
                      origen,
                      destino,
                      tramo.cargado,
                      tramo.carga,
                  )
                : null;
        const llave = clave(origen, destino, tramo.cargado);
        const escrito = nuevos[llave] ?? '';
        // Cargado en un tramo que gasta según las toneladas.
        const bandas =
            (tramo.cargado &&
                filaDeLaTabla(opciones.tramos, origen, destino, tramo.carga)
                    ?.bandas) ||
            [];

        return {
            origen,
            destino,
            tramo,
            llave,
            tabla,
            escrito,
            bandas,
            falta:
                Boolean(origen && destino) &&
                tabla === null &&
                bandas.length === 0,
            galones: tabla ?? (escrito === '' ? null : Number(escrito)),
        };
    });
    const fijas = filas.filter((fila) => fila.bandas.length === 0);
    const porPeso = filas.filter((fila) => fila.bandas.length > 0);
    const suma = (extremo: (galones: number[]) => number) =>
        fijas.reduce((total, fila) => total + (fila.galones ?? 0), 0) +
        porPeso.reduce(
            (total, fila) =>
                total + extremo(fila.bandas.map((banda) => banda.galones)),
            0,
        );
    // Con un tramo por peso el tope va de la banda más liviana a la más pesada.
    const tope = fijas.some((fila) => fila.galones === null)
        ? null
        : porPeso.length === 0
          ? `${suma(() => 0)} gal`
          : `${suma((galones) => Math.min(...galones))} a ${suma((galones) => Math.max(...galones))} gal, según el peso`;

    // El nombre se propone solo mientras nadie lo escriba.
    const primerCargado = filas.find((fila) => fila.tramo.cargado);
    const propuesto = !completo
        ? ''
        : primerCargado
          ? `${cargas.find((carga) => carga.value === primerCargado.tramo.carga)?.label ?? 'Carga'} a ${zona(primerCargado.destino)}`
          : `Vacía a ${zona(lugares[lugares.length - 1])}`;
    const nombreFinal = nombreEscrito ? nombre : propuesto;
    // La ida sale de la base o de la mina; lo demás es vuelta.
    const sentidoFinal =
        sentido ?? (BASES.includes(lugares[0]) ? 'ida' : 'regreso');

    const cambiarLugar = (indice: number, valor: string) =>
        setLugares(lugares.map((lugar, i) => (i === indice ? valor : lugar)));
    const cambiarTramo = (indice: number, cambios: Partial<Tramo>) =>
        setTramos(
            tramos.map((tramo, i) =>
                i === indice ? { ...tramo, ...cambios } : tramo,
            ),
        );
    const agregarLugar = () => {
        setLugares([...lugares, '']);
        // Lo habitual: después del destino, la unidad vuelve vacía a la base.
        setTramos([...tramos, { cargado: false, carga: 'particular' }]);
    };
    const quitarLugar = (indice: number) => {
        setLugares(lugares.filter((_, i) => i !== indice));
        setTramos(tramos.filter((_, i) => i !== Math.max(indice - 1, 0)));
    };

    const enviar = (evento: React.FormEvent) => {
        evento.preventDefault();

        if (!completo) {
            setErrores({ paradas: 'Elige todos los lugares del recorrido.' });

            return;
        }

        if (lugares.some((lugar, i) => i > 0 && lugar === lugares[i - 1])) {
            setErrores({
                paradas: 'Dos lugares seguidos no pueden ser el mismo.',
            });

            return;
        }

        const lineas: Partial<LineaRuta>[] = [
            ...(puntoFull !== ''
                ? [
                      {
                          id: lineaFull?.id ?? null,
                          variante: null,
                          tipo: 'combustible' as const,
                          concepto: lineaFull?.concepto ?? 'Combustible',
                          punto_abastecimiento_id: Number(puntoFull),
                          tanque_lleno: true,
                      },
                  ]
                : []),
            ...(viaticos !== '' && Number(viaticos) > 0
                ? [
                      {
                          id: lineaViaticos?.id ?? null,
                          variante: null,
                          tipo: 'viatico' as const,
                          concepto: lineaViaticos?.concepto ?? 'Viáticos',
                          monto: Number(viaticos),
                      },
                  ]
                : []),
            ...otrasLineas,
        ];

        const datos = {
            nombre: nombreFinal,
            descripcion: ruta?.descripcion ?? '',
            sentido: sentidoFinal,
            es_complemento: false,
            por_tramos: true,
            dias_viaje: dias,
            km_estandar: km,
            color: ruta?.color ?? '',
            nota,
            activa,
            paradas: filas.map((fila) => ({
                zona_origen: fila.origen,
                zona_destino: fila.destino,
                tipo_carga: fila.tramo.cargado ? fila.tramo.carga : 'vacio',
                lugar_origen: fila.tramo.lugar_origen ?? null,
                lugar_destino: fila.tramo.lugar_destino ?? null,
            })),
            variantes: [],
            lineas,
            tramos_nuevos: filas
                .filter((fila) => fila.falta && fila.escrito !== '')
                .map((fila) => ({
                    zona_origen: fila.origen,
                    zona_destino: fila.destino,
                    cargado: fila.tramo.cargado,
                    galones: Number(fila.escrito),
                })),
        };
        const opcionesEnvio = {
            preserveScroll: true,
            onStart: () => setGuardando(true),
            onFinish: () => setGuardando(false),
            onError: (lista: Record<string, string>) => setErrores(lista),
            onSuccess: onCerrar,
        };

        if (ruta) {
            router.put(rutas.update(ruta.id).url, datos, opcionesEnvio);

            return;
        }

        router.post(rutas.store().url, datos, opcionesEnvio);
    };

    const quitar = async () => {
        if (
            !ruta ||
            !(await confirmar(
                ruta.usada
                    ? {
                          titulo: `¿Desactivar «${ruta.nombre}»?`,
                          descripcion:
                              'Ya está en tickets: no se borra, deja de ofrecerse al programar.',
                          accion: 'Desactivar',
                      }
                    : {
                          titulo: `¿Quitar la ruta «${ruta.nombre}»?`,
                          accion: 'Quitar',
                      },
            ))
        ) {
            return;
        }

        router.delete(rutas.destroy(ruta.id).url, {
            preserveScroll: true,
            onSuccess: onCerrar,
        });
    };

    return (
        <Dialog open onOpenChange={(abierto) => !abierto && onCerrar()}>
            <DialogContent className="flex max-h-[92dvh] flex-col overflow-hidden md:max-w-3xl">
                <DialogHeader>
                    <DialogTitle>
                        {ruta ? `Editar «${ruta.nombre}»` : 'Nueva ruta'}
                    </DialogTitle>
                    <DialogDescription>
                        Tres preguntas. Los galones salen de la tabla de tramos
                        y el total se suma solo. Los tickets que ya tienen esta
                        ruta no cambian.
                    </DialogDescription>
                </DialogHeader>

                <form
                    id="ruta-por-tramos"
                    onSubmit={enviar}
                    className="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto px-1"
                >
                    <Pregunta
                        numero={1}
                        titulo="¿Por dónde pasa?"
                        ayuda="Los lugares en orden, hasta volver a la base si corresponde. En cada tramo, si va cargado o vacío."
                    >
                        <div className="flex flex-wrap items-center gap-2">
                            {lugares.map((lugar, indice) => (
                                <div
                                    key={indice}
                                    className="flex items-center gap-2"
                                >
                                    {indice > 0 && (
                                        <ArrowRight className="size-4 text-muted-foreground" />
                                    )}
                                    <div className="flex items-center">
                                        <Select
                                            value={lugar || undefined}
                                            onValueChange={(valor) =>
                                                cambiarLugar(indice, valor)
                                            }
                                        >
                                            <SelectTrigger
                                                className="w-36"
                                                aria-label={
                                                    indice === 0
                                                        ? 'Desde'
                                                        : `Lugar ${indice + 1}`
                                                }
                                            >
                                                <SelectValue
                                                    placeholder={
                                                        indice === 0
                                                            ? 'Desde'
                                                            : 'Hasta'
                                                    }
                                                />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {opciones.zonas.map(
                                                    (opcion) => (
                                                        <SelectItem
                                                            key={opcion.value}
                                                            value={opcion.value}
                                                        >
                                                            {opcion.label}
                                                        </SelectItem>
                                                    ),
                                                )}
                                            </SelectContent>
                                        </Select>
                                        {lugares.length > 2 && indice > 0 && (
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                className="size-7"
                                                aria-label="Quitar lugar"
                                                onClick={() =>
                                                    quitarLugar(indice)
                                                }
                                            >
                                                <X className="size-3.5" />
                                            </Button>
                                        )}
                                    </div>
                                </div>
                            ))}
                            {lugares.length < 9 && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={agregarLugar}
                                >
                                    <Plus className="size-4" />
                                    Lugar
                                </Button>
                            )}
                        </div>
                        <InputError message={errores.paradas} />

                        <ul className="flex flex-col gap-2">
                            {filas.map((fila, indice) => (
                                <li
                                    key={indice}
                                    className="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-muted/50 px-3 py-2"
                                >
                                    <span className="text-sm font-medium">
                                        {zona(fila.origen)} →{' '}
                                        {zona(fila.destino)}
                                    </span>
                                    <div className="flex items-center gap-2">
                                        {fila.tramo.cargado && (
                                            <Select
                                                value={fila.tramo.carga}
                                                onValueChange={(valor) =>
                                                    cambiarTramo(indice, {
                                                        carga: valor,
                                                    })
                                                }
                                            >
                                                <SelectTrigger
                                                    className="h-8 w-36"
                                                    aria-label="Con qué va cargado"
                                                >
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {cargas.map((carga) => (
                                                        <SelectItem
                                                            key={carga.value}
                                                            value={carga.value}
                                                        >
                                                            {carga.label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        )}
                                        <div className="flex rounded-md border bg-background p-0.5">
                                            {[true, false].map((cargado) => (
                                                <button
                                                    key={String(cargado)}
                                                    type="button"
                                                    aria-pressed={
                                                        fila.tramo.cargado ===
                                                        cargado
                                                    }
                                                    onClick={() =>
                                                        cambiarTramo(indice, {
                                                            cargado,
                                                        })
                                                    }
                                                    className={cn(
                                                        'rounded px-2.5 py-1 text-xs font-medium',
                                                        fila.tramo.cargado ===
                                                            cargado
                                                            ? 'bg-primary text-primary-foreground'
                                                            : 'text-muted-foreground hover:text-foreground',
                                                    )}
                                                >
                                                    {cargado
                                                        ? 'Cargado'
                                                        : 'Vacío'}
                                                </button>
                                            ))}
                                        </div>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    </Pregunta>

                    <Pregunta
                        numero={2}
                        titulo="¿Cuánto gasta?"
                        ayuda="Lo que dice la tabla de galones por tramo. Si un tramo es nuevo, escribe sus galones: quedan en la tabla para las demás rutas."
                    >
                        <ul className="flex flex-col gap-2">
                            {filas.map((fila, indice) => (
                                <li
                                    key={indice}
                                    className={cn(
                                        'flex items-center justify-between gap-3 rounded-lg border px-3 py-2',
                                        fila.falta &&
                                            'border-amber-500/50 bg-amber-500/5',
                                    )}
                                >
                                    <div className="min-w-0">
                                        <p className="text-sm font-medium">
                                            {zona(fila.origen)} →{' '}
                                            {zona(fila.destino)} ·{' '}
                                            {fila.tramo.cargado
                                                ? 'cargado'
                                                : 'vacío'}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {!fila.origen || !fila.destino
                                                ? 'Elige los lugares arriba.'
                                                : fila.bandas.length > 0
                                                  ? `Según las toneladas de la GR: ${fila.bandas.map((banda) => `${banda.peso_hasta === null ? `más de ${banda.peso_desde} t` : `hasta ${banda.peso_hasta} t`} ${banda.galones}`).join(' · ')}. La banda se elige en el ticket.`
                                                  : fila.falta
                                                    ? 'Tramo nuevo: ¿cuántos galones gasta? Si no lo sabes todavía, déjalo vacío.'
                                                    : fila.origen ===
                                                        fila.destino
                                                      ? 'Dentro de la misma zona no suma.'
                                                      : 'De la tabla.'}
                                        </p>
                                    </div>
                                    {fila.falta ? (
                                        <div className="flex items-center gap-1.5">
                                            <Input
                                                inputMode="decimal"
                                                className="w-24 text-right"
                                                aria-label={`Galones de ${zona(fila.origen)} a ${zona(fila.destino)}`}
                                                placeholder="¿?"
                                                value={fila.escrito}
                                                onChange={(e) =>
                                                    setNuevos({
                                                        ...nuevos,
                                                        [fila.llave]:
                                                            e.target.value.replace(
                                                                /[^0-9.]/g,
                                                                '',
                                                            ),
                                                    })
                                                }
                                            />
                                            <span className="text-sm text-muted-foreground">
                                                gal
                                            </span>
                                        </div>
                                    ) : (
                                        <span className="text-sm font-semibold tabular-nums">
                                            {fila.bandas.length > 0
                                                ? `${Math.min(...fila.bandas.map((banda) => banda.galones))}–${Math.max(...fila.bandas.map((banda) => banda.galones))} gal`
                                                : fila.galones === null
                                                  ? '—'
                                                  : `${fila.galones} gal`}
                                        </span>
                                    )}
                                </li>
                            ))}
                        </ul>
                        <InputError
                            message={
                                Object.entries(errores).find(([campo]) =>
                                    campo.startsWith('tramos_nuevos'),
                                )?.[1]
                            }
                        />
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <Link
                                href={tramosRutas.index().url}
                                className="text-xs font-medium text-primary hover:underline"
                            >
                                Corregir un número: tabla de galones por tramo
                            </Link>
                            {tope === null ? (
                                <span className="text-sm font-medium text-amber-700 dark:text-amber-400">
                                    Sin tope hasta completar los galones
                                </span>
                            ) : (
                                <span className="text-base font-semibold tabular-nums">
                                    Tope de la ruta: {tope}
                                </span>
                            )}
                        </div>
                    </Pregunta>

                    <Pregunta
                        numero={3}
                        titulo="¿Qué más lleva?"
                        ayuda="Los viáticos y si tanquea full. El full no tiene galones: lo que diga su voucher descuenta del tope."
                    >
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field
                                label="Viáticos (S/)"
                                error={
                                    Object.entries(errores).find(([campo]) =>
                                        campo.endsWith('.monto'),
                                    )?.[1]
                                }
                            >
                                {(id) => (
                                    <Input
                                        id={id}
                                        inputMode="decimal"
                                        placeholder="Sin viáticos"
                                        value={viaticos}
                                        onChange={(e) =>
                                            setViaticos(
                                                e.target.value.replace(
                                                    /[^0-9.]/g,
                                                    '',
                                                ),
                                            )
                                        }
                                    />
                                )}
                            </Field>
                            <Field label="Tanque full">
                                {(id) => (
                                    <Select
                                        value={puntoFull || NINGUNO}
                                        onValueChange={(valor) =>
                                            setPuntoFull(
                                                valor === NINGUNO ? '' : valor,
                                            )
                                        }
                                    >
                                        <SelectTrigger id={id}>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value={NINGUNO}>
                                                No tanquea full
                                            </SelectItem>
                                            {opciones.puntos.map((punto) => (
                                                <SelectItem
                                                    key={punto.value}
                                                    value={punto.value}
                                                >
                                                    Full en {punto.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                )}
                            </Field>
                            <div className="sm:col-span-2">
                                <Field
                                    label="Nombre"
                                    error={errores.nombre}
                                    ayuda="Se propone solo con el recorrido; cámbialo si quieres."
                                    required
                                >
                                    {(id) => (
                                        <Input
                                            id={id}
                                            value={nombreFinal}
                                            onChange={(e) => {
                                                setNombre(e.target.value);
                                                setNombreEscrito(true);
                                            }}
                                        />
                                    )}
                                </Field>
                            </div>
                        </div>
                        {otrasLineas.length > 0 && (
                            <p className="text-xs text-muted-foreground">
                                Además lleva:{' '}
                                {otrasLineas
                                    .map((linea) => linea.concepto)
                                    .join(', ')}
                                . Eso se cambia en el formulario completo.
                            </p>
                        )}
                    </Pregunta>

                    <details className="rounded-xl border px-4 py-3 text-sm">
                        <summary className="cursor-pointer font-medium">
                            Más opciones
                        </summary>
                        <div className="mt-3 grid gap-4 sm:grid-cols-3">
                            <Field label="Se ofrece en" error={errores.sentido}>
                                {(id) => (
                                    <Select
                                        value={sentidoFinal || NINGUNO}
                                        onValueChange={(valor) =>
                                            setSentido(
                                                valor === NINGUNO ? '' : valor,
                                            )
                                        }
                                    >
                                        <SelectTrigger id={id}>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="ida">
                                                La ida
                                            </SelectItem>
                                            <SelectItem value="regreso">
                                                La vuelta
                                            </SelectItem>
                                            <SelectItem value={NINGUNO}>
                                                Cualquiera
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                )}
                            </Field>
                            <Field
                                label="Días de viaje"
                                error={errores.dias_viaje}
                            >
                                {(id) => (
                                    <Input
                                        id={id}
                                        inputMode="numeric"
                                        value={dias}
                                        onChange={(e) =>
                                            setDias(
                                                e.target.value.replace(
                                                    /\D/g,
                                                    '',
                                                ),
                                            )
                                        }
                                    />
                                )}
                            </Field>
                            <Field label="Km" error={errores.km_estandar}>
                                {(id) => (
                                    <Input
                                        id={id}
                                        inputMode="numeric"
                                        value={km}
                                        onChange={(e) =>
                                            setKm(
                                                e.target.value.replace(
                                                    /\D/g,
                                                    '',
                                                ),
                                            )
                                        }
                                    />
                                )}
                            </Field>
                            <div className="sm:col-span-3">
                                <Field label="Nota" error={errores.nota}>
                                    {(id) => (
                                        <Textarea
                                            id={id}
                                            rows={2}
                                            value={nota}
                                            onChange={(e) =>
                                                setNota(e.target.value)
                                            }
                                        />
                                    )}
                                </Field>
                            </div>
                            <label className="flex items-center gap-2 sm:col-span-3">
                                <Checkbox
                                    checked={activa}
                                    onCheckedChange={(marcado) =>
                                        setActiva(marcado === true)
                                    }
                                />
                                Se ofrece al programar
                            </label>
                        </div>
                        <button
                            type="button"
                            onClick={onFormularioCompleto}
                            className="mt-3 text-xs font-medium text-primary hover:underline"
                        >
                            ¿Es un complemento o depende del peso? Abrir el
                            formulario completo
                        </button>
                    </details>
                </form>

                <DialogFooter className="gap-2 sm:justify-between">
                    {ruta ? (
                        <Button type="button" variant="ghost" onClick={quitar}>
                            {ruta.usada ? 'Desactivar' : 'Quitar ruta'}
                        </Button>
                    ) : (
                        <span />
                    )}
                    <Button
                        type="submit"
                        form="ruta-por-tramos"
                        disabled={guardando}
                    >
                        {guardando && <Spinner />}
                        Guardar ruta
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
