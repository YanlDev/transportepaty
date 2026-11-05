import { Head, router, setLayoutProps, useForm } from '@inertiajs/react';
import { ChevronRight, Fuel, Plus, Settings, Trash2 } from 'lucide-react';
import { useState } from 'react';
import configuracion from '@/actions/App/Http/Controllers/ConfiguracionAbastecimientoController';
import hub from '@/actions/App/Http/Controllers/ConfiguracionController';
import { ReglaDialog } from '@/components/abastecimiento/regla-dialog';
import { EncabezadoPantalla } from '@/components/encabezado-pantalla';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
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
import { colorTarjeta } from '@/lib/color-tarjeta';
import { cn } from '@/lib/utils';
import type {
    OpcionesConfiguracion,
    PuntoAbastecimiento,
    ReglaAbastecimiento,
} from '@/types/abastecimiento';

type Props = {
    puntos: PuntoAbastecimiento[];
    reglas: ReglaAbastecimiento[];
    opciones: OpcionesConfiguracion;
};

const SIN_ZONA = '__sin_zona';

/** Agregar o editar un punto de abastecimiento. */
function PuntoDialog({
    punto,
    zonas,
    onCerrar,
}: {
    punto: PuntoAbastecimiento | null;
    zonas: OpcionesConfiguracion['zonas'];
    onCerrar: () => void;
}) {
    const { data, setData, post, put, processing, errors } = useForm({
        nombre: punto?.nombre ?? '',
        zona: punto?.zona ?? '',
        detalle: punto?.detalle ?? '',
        activo: punto?.activo ?? true,
    });

    const enviar = (evento: React.FormEvent) => {
        evento.preventDefault();
        const opciones = { preserveScroll: true, onSuccess: onCerrar };

        if (punto) {
            put(configuracion.updatePunto(punto.id).url, opciones);

            return;
        }

        post(configuracion.storePunto().url, opciones);
    };

    return (
        <Dialog open onOpenChange={(abierto) => !abierto && onCerrar()}>
            <DialogContent className="md:max-w-sm">
                <DialogHeader>
                    <DialogTitle>
                        {punto ? 'Editar punto' : 'Nuevo punto'}
                    </DialogTitle>
                </DialogHeader>
                <form
                    id="punto-abastecimiento"
                    onSubmit={enviar}
                    className="flex flex-col gap-4"
                >
                    <Field label="Nombre" error={errors.nombre} required>
                        {(id) => (
                            <Input
                                id={id}
                                value={data.nombre}
                                onChange={(evento) =>
                                    setData('nombre', evento.target.value)
                                }
                            />
                        )}
                    </Field>
                    <Field label="Zona" error={errors.zona}>
                        {() => (
                            <Select
                                value={data.zona || SIN_ZONA}
                                onValueChange={(valor) =>
                                    setData(
                                        'zona',
                                        valor === SIN_ZONA ? '' : valor,
                                    )
                                }
                            >
                                <SelectTrigger className="w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={SIN_ZONA}>
                                        Punto de paso
                                    </SelectItem>
                                    {zonas.map((zona) => (
                                        <SelectItem
                                            key={zona.value}
                                            value={zona.value}
                                        >
                                            {zona.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        )}
                    </Field>
                    <Field label="Detalle" error={errors.detalle}>
                        {(id) => (
                            <Input
                                id={id}
                                placeholder="Grifo Repsol, cochera…"
                                value={data.detalle}
                                onChange={(evento) =>
                                    setData('detalle', evento.target.value)
                                }
                            />
                        )}
                    </Field>
                    {/* Un punto que ya se usó no se borra, se desactiva:
                        acá se vuelve a activar. */}
                    <label className="flex items-center gap-2 text-sm">
                        <Checkbox
                            checked={data.activo}
                            onCheckedChange={(marcado) =>
                                setData('activo', marcado === true)
                            }
                        />
                        Activo (se ofrece al registrar y en las tarjetas)
                    </label>
                </form>
                <DialogFooter>
                    <Button
                        type="submit"
                        form="punto-abastecimiento"
                        disabled={processing}
                    >
                        Guardar
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

type Seccion = {
    clave: string;
    titulo: string;
    ayuda: string;
    reglas: ReglaAbastecimiento[];
};

/** Para quién es la carga: lo que no es particular ni vacío es de Minsur. */
function claseDeCarga(tipo: string | null): 'mina' | 'particular' | 'general' {
    if (tipo === null || tipo === 'vacio') {
        return 'general';
    }

    return tipo === 'particular' ? 'particular' : 'mina';
}

/**
 * Las tarjetas por sentido y por para quién es la carga, en el orden en que
 * se lee el viaje: la ida de la mina, la ida particular, la vuelta de la
 * mina, la vuelta particular. Las que no se aplican solas y las apagadas van
 * al final.
 */
function secciones(reglas: ReglaAbastecimiento[]): Seccion[] {
    const definiciones: Omit<Seccion, 'reglas'>[] = [
        {
            clave: 'ida-mina',
            titulo: 'Ida · carga de mina',
            ayuda: 'De la mina o Juliaca hacia la costa con carga de Minsur.',
        },
        {
            clave: 'ida-particular',
            titulo: 'Ida · carga particular',
            ayuda: 'Hacia la costa con carga de un cliente particular.',
        },
        {
            clave: 'regreso-mina',
            titulo: 'Vuelta · carga de mina',
            ayuda: 'El regreso con escoria, materiales de Ransa o sacos (también la carga particular que sube a la mina, por peso).',
        },
        {
            clave: 'regreso-particular',
            titulo: 'Vuelta · carga particular',
            ayuda: 'El regreso con carga de un cliente particular.',
        },
        {
            clave: 'general',
            titulo: 'Para cualquier tramo',
            ayuda: 'Sin sentido o sin carga definidos.',
        },
        {
            clave: 'referencia',
            titulo: 'Solo de referencia',
            ayuda: 'No se aplican solas a ningún ticket: sirven de consulta.',
        },
        {
            clave: 'apagadas',
            titulo: 'Desactivadas',
            ayuda: 'No se usan. Se pueden volver a activar desde ⚙️.',
        },
    ];

    const claveDe = (regla: ReglaAbastecimiento): string => {
        if (!regla.activa) {
            return 'apagadas';
        }

        if (!regla.se_aplica_sola) {
            return 'referencia';
        }

        const clase = claseDeCarga(regla.tipo_carga);

        if (regla.sentido === null || clase === 'general') {
            return 'general';
        }

        return `${regla.sentido}-${clase}`;
    };

    return definiciones
        .map((definicion) => ({
            ...definicion,
            reglas: reglas.filter(
                (regla) => claveDe(regla) === definicion.clave,
            ),
        }))
        .filter((seccion) => seccion.reglas.length > 0);
}

/**
 * Las tarjetas del mismo trayecto y la misma carga que solo cambian por
 * peso (los materiales de Ransa) van en una sola fila.
 */
function filas(reglas: ReglaAbastecimiento[]): ReglaAbastecimiento[][] {
    const grupos = new Map<string, ReglaAbastecimiento[]>();

    for (const regla of reglas) {
        const porPeso = regla.peso_desde !== null || regla.peso_hasta !== null;
        const clave = porPeso
            ? [
                  regla.sentido,
                  regla.zona_origen,
                  regla.zona_destino,
                  regla.tipo_carga,
                  regla.lugares,
              ].join('|')
            : `regla-${regla.id}`;

        grupos.set(clave, [...(grupos.get(clave) ?? []), regla]);
    }

    return [...grupos.values()].map((grupo) =>
        [...grupo].sort(
            (a, b) => (a.peso_hasta ?? Infinity) - (b.peso_hasta ?? Infinity),
        ),
    );
}

function etiquetaDe(
    lista: { value: string; label: string }[],
    valor: string | null,
): string | null {
    return valor
        ? (lista.find((opcion) => opcion.value === valor)?.label ?? valor)
        : null;
}

/** El combustible de la tarjeta: el total y cómo se reparte entre puntos. */
function combustibleDe(regla: ReglaAbastecimiento): {
    total: string;
    puntos: string;
} {
    const lineas = regla.lineas.filter((linea) => linea.tipo === 'combustible');

    if (lineas.length === 0) {
        return { total: '—', puntos: '' };
    }

    const porDefinir = lineas.some((linea) => linea.galones === null);
    const galones = lineas.reduce(
        (suma, linea) => suma + (linea.galones ?? 0),
        0,
    );

    return {
        total: porDefinir ? '¿? gal (por definir)' : `${galones} gal`,
        puntos:
            lineas.length > 1 || lineas[0].punto
                ? lineas
                      .map(
                          (linea) =>
                              `${linea.punto ?? linea.concepto} ${linea.galones ?? '¿?'}`,
                      )
                      .join(' · ')
                : '',
    };
}

function viaticosDe(regla: ReglaAbastecimiento): number {
    return regla.lineas
        .filter((linea) => linea.tipo !== 'combustible')
        .reduce((suma, linea) => suma + (linea.monto ?? 0), 0);
}

function rangoDePeso(regla: ReglaAbastecimiento): string {
    if (regla.peso_hasta !== null) {
        return `hasta ${regla.peso_hasta} t`;
    }

    return regla.peso_desde !== null ? `más de ${regla.peso_desde} t` : '—';
}

function BotonEditar({
    etiqueta,
    onClick,
}: {
    etiqueta: string;
    onClick: () => void;
}) {
    return (
        <Button
            variant="ghost"
            size="icon"
            className="size-8 shrink-0"
            aria-label={etiqueta}
            title={etiqueta}
            onClick={onClick}
        >
            <Settings className="size-4" />
        </Button>
    );
}

/** El color de la línea según el sentido: el mismo de todo el sistema. */
const COLOR_SENTIDO: Record<string, { linea: string; texto: string }> = {
    ida: { linea: 'bg-amber-500', texto: 'text-amber-500' },
    regreso: { linea: 'bg-violet-500', texto: 'text-violet-500' },
};

const SIN_SENTIDO = {
    linea: 'bg-muted-foreground/40',
    texto: 'text-muted-foreground/60',
};

/** Lo que va a la mina, en el azul de Minsur (el mismo del Gantt). */
const A_MINA = {
    linea: 'bg-[#1d3c8f] dark:bg-blue-400',
    texto: 'text-[#1d3c8f] dark:text-blue-400',
};

/**
 * La ruta dibujada: el origen y el destino unidos por una línea del color
 * del sentido (la ida en ámbar, la vuelta en violeta; lo que va a la mina,
 * en azul Minsur), con los puntos donde se carga marcados encima y sus
 * galones debajo.
 */
function RutaDibujada({
    regla,
    opciones,
    conPuntos,
}: {
    regla: ReglaAbastecimiento;
    opciones: OpcionesConfiguracion;
    conPuntos: boolean;
}) {
    const color =
        regla.zona_destino === 'mina'
            ? A_MINA
            : regla.sentido
              ? (COLOR_SENTIDO[regla.sentido] ?? SIN_SENTIDO)
              : SIN_SENTIDO;
    const desde = etiquetaDe(opciones.zonas, regla.zona_origen) ?? 'Cualquiera';
    const hasta =
        etiquetaDe(opciones.zonas, regla.zona_destino) ?? 'cualquiera';
    const puntos = conPuntos
        ? regla.lineas.filter(
              (linea) => linea.tipo === 'combustible' && linea.punto,
          )
        : [];

    return (
        <div
            className={cn(
                'flex items-center gap-2',
                puntos.length > 0 && 'pb-4',
            )}
        >
            <span className="shrink-0 text-sm font-medium">{desde}</span>
            <div className="relative h-5 min-w-28 flex-1">
                <span
                    className={cn(
                        'absolute inset-x-0 top-1/2 h-1 -translate-y-1/2 rounded-full',
                        color.linea,
                    )}
                />
                <ChevronRight
                    className={cn(
                        'absolute top-1/2 -right-2 size-5 -translate-y-1/2',
                        color.texto,
                    )}
                    strokeWidth={3}
                />
                {puntos.map((linea, indice) => (
                    <span
                        key={indice}
                        className="absolute top-1/2 flex -translate-x-1/2 -translate-y-1/2 flex-col items-center"
                        style={{
                            left: `${((indice + 1) / (puntos.length + 1)) * 100}%`,
                        }}
                    >
                        <span
                            className={cn(
                                'size-3 rounded-full ring-2 ring-card',
                                color.linea,
                            )}
                        />
                        <span className="absolute top-full mt-1 text-[10px] leading-none whitespace-nowrap text-muted-foreground">
                            {linea.punto}{' '}
                            <span className="font-medium text-foreground tabular-nums">
                                {linea.galones ?? '¿?'}
                            </span>
                        </span>
                    </span>
                ))}
            </div>
            <span className="shrink-0 pl-2 text-sm font-medium">
                {hasta}
                {regla.lugares && (
                    <span className="text-xs font-normal text-muted-foreground">
                        {' '}
                        ({regla.lugares})
                    </span>
                )}
            </span>
        </div>
    );
}

const COLUMNAS =
    'md:grid-cols-[minmax(0,2.6fr)_minmax(0,0.8fr)_minmax(0,0.7fr)_minmax(0,0.7fr)_auto]';

/** Una ruta de la pizarra en una fila: qué le toca y el ⚙️ para editarla. */
function FilaRuta({
    grupo,
    opciones,
    onEditar,
}: {
    grupo: ReglaAbastecimiento[];
    opciones: OpcionesConfiguracion;
    onEditar: (regla: ReglaAbastecimiento) => void;
}) {
    const primera = grupo[0];
    const porPeso = grupo.length > 1;
    const viaticos = viaticosDe(primera);

    return (
        <li
            className={cn(
                'grid gap-2 px-4 py-3 md:items-center md:gap-4',
                COLUMNAS,
            )}
        >
            <div className="flex min-w-0 flex-col gap-2">
                {/* El nombre manda: es como se la conoce en la oficina. La
                    marca es el color de su tarjeta en la pizarra de papel. */}
                <p className="flex min-w-0 items-center gap-2">
                    <span
                        className={cn(
                            'h-4 w-1.5 shrink-0 rounded-full border',
                            colorTarjeta(primera.color).cabecera,
                        )}
                    />
                    <span className="truncate text-sm font-semibold">
                        {porPeso
                            ? primera.nombre.replace(/\s*·.*$/, '')
                            : primera.nombre}
                    </span>
                </p>
                <RutaDibujada
                    regla={primera}
                    opciones={opciones}
                    conPuntos={!porPeso}
                />
            </div>

            <p className="text-sm text-muted-foreground">
                {etiquetaDe(opciones.cargas, primera.tipo_carga) ??
                    'Cualquier carga'}
            </p>

            {porPeso ? (
                <div className="flex flex-wrap gap-1.5 md:col-span-2">
                    {grupo.map((regla) => (
                        <button
                            key={regla.id}
                            type="button"
                            onClick={() => onEditar(regla)}
                            className="rounded-full border px-2.5 py-0.5 text-xs hover:border-foreground/30 hover:bg-accent/50"
                            title={`Editar «${regla.nombre}»`}
                        >
                            <span className="text-muted-foreground">
                                {rangoDePeso(regla)}:
                            </span>{' '}
                            <span className="font-medium tabular-nums">
                                {combustibleDe(regla).total}
                            </span>
                        </button>
                    ))}
                </div>
            ) : (
                <>
                    <p className="text-sm font-medium tabular-nums">
                        {combustibleDe(primera).total}
                    </p>
                    <p className="text-sm tabular-nums">
                        {viaticos > 0 ? `S/ ${viaticos.toFixed(2)}` : '—'}
                    </p>
                </>
            )}

            <div className="flex justify-end">
                {!porPeso && (
                    <BotonEditar
                        etiqueta={`Editar «${primera.nombre}»`}
                        onClick={() => onEditar(primera)}
                    />
                )}
            </div>
        </li>
    );
}

/**
 * La pizarra de abastecimiento como lista: cada ruta en una fila, agrupadas
 * por sentido y por para quién es la carga, con un ⚙️ para revisarla o
 * editarla al toque. Aparte, los puntos donde se carga, con lo que se cargó
 * en el mes y el último precio.
 */
export default function AbastecimientoConfiguracion({
    puntos,
    reglas,
    opciones,
}: Props) {
    const [pestana, setPestana] = useState<'rutas' | 'puntos'>('rutas');
    const [punto, setPunto] = useState<PuntoAbastecimiento | null | false>(
        false,
    );
    const [regla, setRegla] = useState<ReglaAbastecimiento | null | false>(
        false,
    );

    setLayoutProps({
        breadcrumbs: [
            { title: 'Configuración', href: hub.index().url },
            {
                title: 'Pizarra de abastecimiento',
                href: configuracion.index().url,
            },
        ],
    });

    const quitarPunto = (elegido: PuntoAbastecimiento) => {
        if (!window.confirm(`¿Quitar ${elegido.nombre}?`)) {
            return;
        }

        router.delete(configuracion.destroyPunto(elegido.id).url, {
            preserveScroll: true,
        });
    };

    const alternarPunto = (elegido: PuntoAbastecimiento) =>
        router.put(
            configuracion.updatePunto(elegido.id).url,
            {
                nombre: elegido.nombre,
                zona: elegido.zona ?? '',
                detalle: elegido.detalle ?? '',
                activo: !elegido.activo,
            },
            { preserveScroll: true },
        );

    return (
        <div className="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-5 p-4 md:p-6">
            <Head title="Pizarra de abastecimiento" />

            <EncabezadoPantalla
                titulo="Pizarra de abastecimiento"
                descripcion="Cuánto combustible y viáticos le toca a cada ruta, y los puntos donde se carga."
                icono={Fuel}
            />

            <div className="flex flex-wrap items-center justify-between gap-3 border-b">
                <nav
                    aria-label="Secciones de la pizarra"
                    className="flex gap-1"
                >
                    {(
                        [
                            ['rutas', 'Rutas y dotaciones', reglas.length],
                            ['puntos', 'Puntos de carga', puntos.length],
                        ] as const
                    ).map(([valor, titulo, cantidad]) => (
                        <button
                            key={valor}
                            type="button"
                            onClick={() => setPestana(valor)}
                            aria-current={
                                pestana === valor ? 'page' : undefined
                            }
                            className={cn(
                                '-mb-px border-b-2 px-3 py-2 text-sm whitespace-nowrap transition',
                                pestana === valor
                                    ? 'border-primary font-medium text-foreground'
                                    : 'border-transparent text-muted-foreground hover:text-foreground',
                            )}
                        >
                            {titulo}{' '}
                            <span className="text-xs text-muted-foreground tabular-nums">
                                {cantidad}
                            </span>
                        </button>
                    ))}
                </nav>
                <Button
                    size="sm"
                    className="mb-2"
                    onClick={() =>
                        pestana === 'rutas' ? setRegla(null) : setPunto(null)
                    }
                >
                    <Plus className="size-4" />
                    {pestana === 'rutas' ? 'Ruta' : 'Punto'}
                </Button>
            </div>

            {pestana === 'rutas' &&
                secciones(reglas).map((seccion) => (
                    <section
                        key={seccion.clave}
                        className={cn(
                            'rounded-xl border bg-card',
                            seccion.clave === 'apagadas' && 'opacity-60',
                        )}
                    >
                        <div className="border-b px-4 py-3">
                            <h2 className="text-sm font-semibold">
                                {seccion.titulo}
                            </h2>
                            <p className="text-xs text-muted-foreground">
                                {seccion.ayuda}
                            </p>
                        </div>
                        <div
                            className={cn(
                                'hidden gap-4 border-b px-4 py-2 text-xs text-muted-foreground md:grid',
                                COLUMNAS,
                            )}
                        >
                            <span>Ruta</span>
                            <span>Carga</span>
                            <span>Combustible</span>
                            <span>Viáticos</span>
                            <span className="w-8" />
                        </div>
                        <ul className="divide-y">
                            {filas(seccion.reglas).map((grupo) => (
                                <FilaRuta
                                    key={grupo[0].id}
                                    grupo={grupo}
                                    opciones={opciones}
                                    onEditar={setRegla}
                                />
                            ))}
                        </ul>
                    </section>
                ))}

            {pestana === 'puntos' && (
                <section className="overflow-x-auto rounded-xl border bg-card">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b text-left text-xs text-muted-foreground">
                                <th className="px-4 py-2 font-normal">Punto</th>
                                <th className="px-4 py-2 font-normal">Zona</th>
                                <th className="px-4 py-2 text-right font-normal">
                                    Último S/ gal
                                </th>
                                <th className="px-4 py-2 text-right font-normal">
                                    Cargado este mes
                                </th>
                                <th className="px-4 py-2 font-normal">
                                    Estado
                                </th>
                                <th className="w-0 px-4 py-2" />
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {puntos.map((elegido) => (
                                <tr
                                    key={elegido.id}
                                    className={cn(
                                        !elegido.activo &&
                                            'text-muted-foreground',
                                    )}
                                >
                                    <td className="px-4 py-2.5">
                                        <p className="font-medium">
                                            {elegido.nombre}
                                        </p>
                                        {elegido.detalle && (
                                            <p className="text-xs text-muted-foreground">
                                                {elegido.detalle}
                                            </p>
                                        )}
                                    </td>
                                    <td className="px-4 py-2.5">
                                        {elegido.zona_label ?? 'De paso'}
                                    </td>
                                    <td className="px-4 py-2.5 text-right tabular-nums">
                                        {elegido.ultimo_precio != null
                                            ? elegido.ultimo_precio.toFixed(3)
                                            : '—'}
                                    </td>
                                    <td className="px-4 py-2.5 text-right tabular-nums">
                                        {elegido.galones_mes
                                            ? `${elegido.galones_mes} gal`
                                            : '—'}
                                    </td>
                                    <td className="px-4 py-2.5">
                                        <button
                                            type="button"
                                            onClick={() =>
                                                alternarPunto(elegido)
                                            }
                                            title={
                                                elegido.activo
                                                    ? 'Desactivar: deja de ofrecerse al registrar'
                                                    : 'Activar: se vuelve a ofrecer al registrar'
                                            }
                                            className={cn(
                                                'inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs transition',
                                                elegido.activo
                                                    ? 'border-emerald-300 bg-emerald-50 text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200'
                                                    : 'hover:bg-accent/50',
                                            )}
                                        >
                                            <span
                                                className={cn(
                                                    'size-2 rounded-full',
                                                    elegido.activo
                                                        ? 'bg-emerald-500'
                                                        : 'bg-muted-foreground/40',
                                                )}
                                            />
                                            {elegido.activo
                                                ? 'Activo'
                                                : 'Inactivo'}
                                        </button>
                                    </td>
                                    <td className="px-4 py-2.5">
                                        <div className="flex justify-end gap-1">
                                            <BotonEditar
                                                etiqueta={`Editar ${elegido.nombre}`}
                                                onClick={() =>
                                                    setPunto(elegido)
                                                }
                                            />
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                className="size-8"
                                                aria-label={`Quitar ${elegido.nombre}`}
                                                onClick={() =>
                                                    quitarPunto(elegido)
                                                }
                                            >
                                                <Trash2 className="size-4" />
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </section>
            )}

            {punto !== false && (
                <PuntoDialog
                    key={punto?.id ?? 'nuevo'}
                    punto={punto}
                    zonas={opciones.zonas}
                    onCerrar={() => setPunto(false)}
                />
            )}

            {regla !== false && (
                <ReglaDialog
                    key={regla?.id ?? 'nueva'}
                    open
                    onOpenChange={(abierto) => !abierto && setRegla(false)}
                    regla={regla}
                    puntos={puntos.filter((elegido) => elegido.activo)}
                    opciones={opciones}
                />
            )}
        </div>
    );
}
