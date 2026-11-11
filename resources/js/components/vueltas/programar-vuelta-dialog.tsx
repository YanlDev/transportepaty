import { router, useForm } from '@inertiajs/react';
import {
    ArrowDownRight,
    ArrowUpRight,
    ChevronDown,
    CircleHelp,
    Layers,
    Lock,
    MapPin,
    Mountain,
    Package,
    Sparkles,
    Tag,
    Truck,
} from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import {
    destroy,
    store,
    update,
} from '@/actions/App/Http/Controllers/VueltaController';
import { CampoSelector, PanelBuscador } from '@/components/selector-en-dialogo';
import type { OpcionBuscable } from '@/components/selector-en-dialogo';
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
import { CargasDelRecorrido } from '@/components/vueltas/cargas-del-recorrido';
import { ItinerarioVuelta } from '@/components/vueltas/itinerario-vuelta';
import { cn } from '@/lib/utils';
import type {
    OpcionesVuelta,
    OpcionTramo,
    ParadaVuelta,
    Sentido,
    Vuelta,
} from '@/types/vueltas';

export type ParadaForm = {
    /** Para seguirla en pantalla aunque cambie de lugar en la lista. */
    clave: string;
    /** Solo las que ya existen. */
    id: number | null;
    sentido: Sentido;
    zona_origen: string;
    zona_destino: string;
    lugar_origen: string | null;
    lugar_destino: string | null;
    tipo_carga: string;
    cliente_id: number | null;
    /** El cliente escrito a mano, cuando todavía no está en el padrón. */
    cliente: string | null;
    /** La GR del ticket anterior cuya carga sigue en esta parada. */
    viaje_continuado_id: number | null;
    /** Con GR: es lo que pasó, se muestra pero no se toca. */
    bloqueada: boolean;
    /** Un vacío que el formulario agregó solo para unir dos paradas. */
    auto?: boolean;
};

type FormData = {
    tracto_id: number | null;
    carreta_id: number | null;
    conductor_id: number | null;
    inicio: string;
    paradas: ParadaForm[];
};

type Eligiendo =
    | { campo: 'tracto_id' | 'carreta_id' | 'conductor_id' }
    | { campo: 'cliente'; sentido: Sentido }
    /** El cliente de una sola carga, por su clave. */
    | { campo: 'cliente_carga'; clave: string }
    | null;

/** El regreso todavía sin decidir: se programa cuando se sabe. */
const POR_DEFINIR = 'por_definir';

/**
 * Cómo sube la unidad de Juliaca a la mina antes de cargar: vacía, con la
 * carga que trae de la costa (la GR del ticket anterior, como la escoria) o
 * con carga nueva de Juliaca (con su propia GR). «Ninguna» queda solo para
 * tickets viejos que arrancaban en la mina: Juliaca es la única base.
 */
type Subida = 'vacia' | 'continua' | 'cargada' | 'ninguna';

/** La carga que la unidad trae hacia la mina: la de su última GR. */
type Trae = NonNullable<OpcionesVuelta['tractos'][number]['trae']>;

/** Si trae carga hacia la mina, sigue con ella; si no, sube vacía. */
function subidaInicial(trae: Trae | null): Subida {
    return trae ? 'continua' : 'vacia';
}

/** El recorrido de una ruta que arranca en la mina, con la subida delante. */
function conSubida(descripcion: string, subida: Subida): string {
    if (subida === 'ninguna') {
        return descripcion;
    }

    return `Juliaca → Mina (${subida === 'vacia' ? 'vacía' : 'con carga'}) → ${descripcion.replace(/^Mina\s*→\s*/, '')}`;
}

function esSubida(parada: ParadaForm): boolean {
    return parada.zona_origen === 'juliaca' && parada.zona_destino === 'mina';
}

const ICONOS: Record<string, ReactNode> = {
    concentrado: <Mountain className="size-4" />,
    metalico: <Layers className="size-4" />,
    particular: <Tag className="size-4" />,
    vacio: <Truck className="size-4" />,
    escoria: <Sparkles className="size-4" />,
    materiales: <Package className="size-4" />,
};

/** El ícono de una opción: el de su carga principal (la última con carga). */
function iconoDe(paradas: { tipo_carga: string }[]): ReactNode {
    const conCarga = paradas.filter((parada) => parada.tipo_carga !== 'vacio');
    const carga = (conCarga.at(-1) ?? paradas.at(-1))?.tipo_carga;

    return (carga && ICONOS[carga]) ?? <CircleHelp className="size-4" />;
}

function desdeParada(parada: ParadaVuelta): ParadaForm {
    return {
        clave: `e${parada.id}`,
        id: parada.id,
        sentido: parada.sentido,
        zona_origen: parada.zona_origen ?? '',
        zona_destino: parada.zona_destino ?? '',
        lugar_origen: parada.lugar_origen,
        lugar_destino: parada.lugar_destino,
        tipo_carga: parada.tipo_carga,
        cliente_id: parada.cliente_id,
        cliente: parada.cliente_id === null ? (parada.cliente ?? null) : null,
        viaje_continuado_id: parada.viaje_continuado_id,
        bloqueada: parada.guias.length > 0,
    };
}

let clavesDadas = 0;

function nuevaParada(
    sentido: Sentido,
    datos: Partial<ParadaForm> = {},
): ParadaForm {
    return {
        clave: `n${++clavesDadas}`,
        id: null,
        sentido,
        zona_origen: '',
        zona_destino: '',
        lugar_origen: null,
        lugar_destino: null,
        tipo_carga: 'particular',
        cliente_id: null,
        cliente: null,
        viaje_continuado_id: null,
        bloqueada: false,
        ...datos,
    };
}

/** Una parada armada desde la plantilla de una ruta del catálogo. */
function desdePlantilla(
    sentido: Sentido,
    plantilla: OpcionTramo['paradas'][number],
    destino: { zona: string; lugar: string } | null = null,
): ParadaForm {
    return nuevaParada(sentido, {
        zona_origen: plantilla.zona_origen,
        zona_destino: plantilla.zona_destino ?? destino?.zona ?? '',
        lugar_origen: plantilla.lugar_origen ?? null,
        lugar_destino:
            plantilla.zona_destino === null
                ? (destino?.lugar ?? null)
                : (plantilla.lugar_destino ?? null),
        tipo_carga: plantilla.tipo_carga,
    });
}

/**
 * Lo que falta recorrer de una ruta cuando el tramo ya tiene GR: la parte
 * que esas GR ya cubren no se vuelve a agregar. «Particular a Arequipa» con
 * Huaral → Arequipa hecho deja solo Arequipa → Juliaca.
 */
function desdeDondeQuedo(
    nuevas: ParadaForm[],
    hechas: ParadaForm[],
): ParadaForm[] {
    const dondeQuedo = hechas.at(-1)?.zona_destino;

    if (!dondeQuedo) {
        return nuevas;
    }

    const sigue = nuevas.findIndex(
        (parada) => parada.zona_origen === dondeQuedo,
    );

    if (sigue !== -1) {
        return nuevas.slice(sigue);
    }

    const llega = nuevas.findLastIndex(
        (parada) => parada.zona_destino === dondeQuedo,
    );

    return llega !== -1 ? nuevas.slice(llega + 1) : nuevas;
}

/**
 * Pone una parada donde encadena con el tramo: si llega a donde arranca
 * otra que todavía no tiene GR, va justo antes (el retorno vacío de Kio a
 * Pisco, antes de la escoria que sale de Pisco); si no, al final.
 */
function encadenar(tramo: ParadaForm[], nueva: ParadaForm): ParadaForm[] {
    const sigue =
        nueva.zona_destino === ''
            ? -1
            : tramo.findIndex(
                  (parada) =>
                      !parada.bloqueada &&
                      !parada.auto &&
                      parada.zona_origen === nueva.zona_destino,
              );

    return sigue === -1
        ? [...tramo, nueva]
        : [...tramo.slice(0, sigue), nueva, ...tramo.slice(sigue)];
}

/** Si la parada es la que puso esa plantilla (sin cambios a mano). */
function esDeLaPlantilla(
    parada: ParadaForm,
    plantilla: OpcionTramo['paradas'][number],
): boolean {
    return (
        !parada.bloqueada &&
        parada.zona_origen === plantilla.zona_origen &&
        parada.zona_destino === plantilla.zona_destino &&
        parada.tipo_carga === plantilla.tipo_carga
    );
}

function hoy(): string {
    const ahora = new Date();
    const mes = String(ahora.getMonth() + 1).padStart(2, '0');
    const dia = String(ahora.getDate()).padStart(2, '0');

    return `${ahora.getFullYear()}-${mes}-${dia}`;
}

/**
 * Une lo elegido en un recorrido continuo: si la ida arranca en la mina,
 * antepone la subida desde Juliaca (vacía, con la carga que trae o con
 * carga nueva, según se eligió); si la ida termina donde no arranca el
 * regreso, une los dos con un vacío. Dentro de cada tramo une las paradas
 * que no se tocan y cierra la vuelta en Juliaca. Lo que agregó antes se
 * rehace cada vez: los vacíos se deducen, no se escriben.
 */
function normalizar(
    paradas: ParadaForm[],
    subida: Subida,
    trae: Trae | null,
): ParadaForm[] {
    // La subida que puso el formulario se rehace con lo elegido, conservando
    // su id; una que se cargó a mano se respeta.
    const subidaAnterior = paradas.find(
        (parada) => parada.auto && parada.sentido === 'ida' && esSubida(parada),
    );
    const propias = paradas.filter((parada) => !parada.auto);
    const ida = propias.filter((parada) => parada.sentido === 'ida');
    const regreso = propias.filter((parada) => parada.sentido === 'regreso');
    const conGuias = propias.some((parada) => parada.bloqueada);

    if (!conGuias && subida !== 'ninguna' && ida[0]?.zona_origen === 'mina') {
        // Sigue con la carga de la GR del ticket anterior: la misma carga,
        // sin cliente ni GR propia.
        const sigue = subida === 'continua' && trae !== null;

        ida.unshift(
            nuevaParada('ida', {
                id: subidaAnterior?.id ?? null,
                zona_origen: 'juliaca',
                zona_destino: 'mina',
                // Con carga nueva se respeta la que ya traía; si no, materiales.
                tipo_carga: sigue
                    ? trae.tipo_carga
                    : subida === 'cargada'
                      ? subidaAnterior &&
                        subidaAnterior.tipo_carga !== 'vacio' &&
                        subidaAnterior.viaje_continuado_id === null
                          ? subidaAnterior.tipo_carga
                          : 'materiales'
                      : 'vacio',
                cliente_id:
                    subida === 'cargada'
                        ? (subidaAnterior?.cliente_id ?? null)
                        : null,
                viaje_continuado_id: sigue ? trae.viaje_id : null,
                auto: true,
            }),
        );
    }

    const idaUnida = unirTramo(sinVaciosRepetidos(ida));
    const regresoUnido = unirTramo(sinVaciosRepetidos(regreso));
    const ultimaIda = idaUnida.at(-1);
    const primeraRegreso = regresoUnido[0];

    if (
        ultimaIda?.zona_destino &&
        primeraRegreso?.zona_origen &&
        ultimaIda.zona_destino !== primeraRegreso.zona_origen
    ) {
        idaUnida.push(
            nuevaParada('ida', {
                zona_origen: ultimaIda.zona_destino,
                zona_destino: primeraRegreso.zona_origen,
                tipo_carga: 'vacio',
                auto: true,
            }),
        );
    }

    // La vuelta termina en la base: si lo último no llega a Juliaca (ni
    // sube a la mina), de ahí sigue vacía. Nadie lo escribe.
    const ultimaRegreso = regresoUnido.at(-1);

    if (ultimaRegreso?.zona_destino && !regresoUnido.some(llegaALaBase)) {
        regresoUnido.push(
            nuevaParada('regreso', {
                zona_origen: ultimaRegreso.zona_destino,
                lugar_origen: ultimaRegreso.lugar_destino,
                zona_destino: 'juliaca',
                tipo_carga: 'vacio',
                auto: true,
            }),
        );
    }

    return [...idaUnida, ...regresoUnido];
}

/** La vuelta termina ahí: en Juliaca o subiendo a la mina. */
function llegaALaBase(parada: ParadaForm): boolean {
    return (
        parada.sentido === 'regreso' &&
        ['juliaca', 'mina'].includes(parada.zona_destino)
    );
}

/** Un vacío que todavía se puede cambiar: sin GR y sin carga que continúe. */
function esVacioLibre(parada: ParadaForm): boolean {
    return (
        parada.tipo_carga === 'vacio' &&
        !parada.bloqueada &&
        parada.viaje_continuado_id === null
    );
}

/**
 * Un vacío deja de serlo cuando después aparece una carga que llega al
 * mismo sitio: la unidad ya no va vacía hasta ahí. Si la carga sale del
 * mismo lugar, el vacío sobra; si sale de otro, el vacío llega solo hasta
 * donde carga (Cusco ⇢ Juliaca pasa a Cusco ⇢ Moquegua si carga en Ilo).
 */
function sinVaciosRepetidos(tramo: ParadaForm[]): ParadaForm[] {
    const resultado: ParadaForm[] = [];

    tramo.forEach((parada, indice) => {
        const carga = esVacioLibre(parada)
            ? tramo
                  .slice(indice + 1)
                  .find(
                      (otra) =>
                          otra.tipo_carga !== 'vacio' &&
                          otra.zona_origen !== '' &&
                          otra.zona_destino === parada.zona_destino,
                  )
            : undefined;

        if (!carga) {
            resultado.push(parada);

            return;
        }

        if (carga.zona_origen !== parada.zona_origen) {
            resultado.push({
                ...parada,
                zona_destino: carga.zona_origen,
                lugar_destino: carga.lugar_origen,
            });
        }
    });

    return resultado;
}

/**
 * Une las paradas de un tramo: si una no arranca donde terminó la
 * anterior, entre las dos va un vacío. Entre dos con GR no se agrega nada:
 * eso ya pasó como dicen las GR.
 */
function unirTramo(tramo: ParadaForm[]): ParadaForm[] {
    return tramo.flatMap((parada, indice) => {
        const anterior = tramo[indice - 1];
        const hayHueco =
            anterior !== undefined &&
            anterior.zona_destino !== '' &&
            parada.zona_origen !== '' &&
            anterior.zona_destino !== parada.zona_origen &&
            !(anterior.bloqueada && parada.bloqueada) &&
            // Lo que quede después de llegar a la base está fuera de
            // orden: no se le inventa un vacío de regreso a la costa.
            !llegaALaBase(anterior);

        return hayHueco
            ? [
                  nuevaParada(parada.sentido, {
                      zona_origen: anterior.zona_destino,
                      lugar_origen: anterior.lugar_destino,
                      zona_destino: parada.zona_origen,
                      lugar_destino: parada.lugar_origen,
                      tipo_carga: 'vacio',
                      auto: true,
                  }),
                  parada,
              ]
            : [parada];
    });
}

/** Una opción de tramo como tarjeta elegible. */
function TarjetaOpcion({
    icono,
    titulo,
    descripcion,
    elegida,
    sentido,
    dotacion = null,
    bloqueo = null,
    onElegir,
    children = null,
}: {
    icono: ReactNode;
    titulo: string;
    descripcion: string;
    elegida: boolean;
    sentido: Sentido;
    /** Lo que se autoriza con esta ruta, en una línea. */
    dotacion?: string | null;
    /** Por qué no se puede elegir: no arranca donde está la unidad. */
    bloqueo?: string | null;
    onElegir: () => void;
    /** Lo que se pregunta de esta ruta una vez elegida, debajo de la tarjeta. */
    children?: ReactNode;
}) {
    if (elegida && children) {
        return (
            <div
                className={cn(
                    'flex flex-col gap-2 rounded-xl border p-1 ring-1',
                    sentido === 'ida'
                        ? 'border-amber-500 bg-amber-500/5 ring-amber-500'
                        : 'border-violet-500 bg-violet-500/5 ring-violet-500',
                )}
            >
                <TarjetaOpcion
                    icono={icono}
                    titulo={titulo}
                    descripcion={descripcion}
                    elegida={elegida}
                    sentido={sentido}
                    dotacion={dotacion}
                    bloqueo={bloqueo}
                    onElegir={onElegir}
                />
                <div className="px-2 pb-2">{children}</div>
            </div>
        );
    }

    return (
        <button
            type="button"
            onClick={onElegir}
            aria-pressed={elegida}
            disabled={bloqueo !== null}
            className={cn(
                'flex items-start gap-3 rounded-xl border p-3 text-left transition',
                bloqueo !== null
                    ? 'cursor-not-allowed border-dashed opacity-50'
                    : elegida
                      ? sentido === 'ida'
                          ? 'border-amber-500 bg-amber-500/10 ring-1 ring-amber-500'
                          : 'border-violet-500 bg-violet-500/10 ring-1 ring-violet-500'
                      : 'hover:border-foreground/30 hover:bg-accent/50',
            )}
        >
            <span
                className={cn(
                    'flex size-8 shrink-0 items-center justify-center rounded-lg',
                    elegida
                        ? sentido === 'ida'
                            ? 'bg-amber-500 text-white'
                            : 'bg-violet-500 text-white'
                        : 'bg-muted text-muted-foreground',
                )}
            >
                {icono}
            </span>
            <span className="min-w-0">
                <span className="block text-sm font-medium">{titulo}</span>
                <span className="block text-xs text-muted-foreground">
                    {descripcion}
                </span>
                {dotacion && (
                    <span className="mt-0.5 block text-[11px] text-emerald-700 dark:text-emerald-400">
                        {dotacion}
                    </span>
                )}
                {bloqueo !== null && (
                    <span className="mt-0.5 flex items-center gap-1 text-xs text-muted-foreground">
                        <Lock className="size-3" />
                        {bloqueo}
                    </span>
                )}
            </span>
        </button>
    );
}

/**
 * Programar la vuelta de una unidad, un tramo a la vez: la ida se programa
 * al salir y el regreso cuando se sabe con qué vuelve (escoria, materiales o
 * carga particular). Las paradas que ya tienen GR quedan fijas.
 */
export function ProgramarVueltaDialog({
    open,
    onOpenChange,
    vuelta,
    tractoInicial,
    fechaInicial,
    opciones,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** `null` para programar una nueva. */
    vuelta: Vuelta | null;
    /** Para programar desde la ficha de una unidad. */
    tractoInicial?: number | null;
    /** El día que se estaba mirando: ahí sale la vuelta nueva. */
    fechaInicial?: string;
    opciones: OpcionesVuelta;
}) {
    const unidadInicial = opciones.tractos.find(
        (unidad) => unidad.id === tractoInicial,
    );

    // La clave del diálogo en la página lo remonta al cambiar de vuelta, así
    // que estos valores iniciales ya son los correctos.
    const { data, setData, post, put, transform, processing, errors } =
        useForm<FormData>({
            tracto_id: vuelta?.tracto_id ?? tractoInicial ?? null,
            carreta_id: vuelta?.carreta_id ?? unidadInicial?.carreta_id ?? null,
            conductor_id:
                vuelta?.conductor_id ?? unidadInicial?.conductor_id ?? null,
            inicio: vuelta?.inicio ?? fechaInicial ?? hoy(),
            paradas: vuelta
                ? [...vuelta.ida, ...vuelta.regreso].map((parada, indice) => {
                      const cargada = desdeParada(parada);
                      const sinGuias = [...vuelta.ida, ...vuelta.regreso].every(
                          (otra) => otra.guias.length === 0,
                      );

                      // Sin GR, la subida a la mina guardada la maneja el
                      // selector «¿Cómo llega a la mina?».
                      return indice === 0 && sinGuias && esSubida(cargada)
                          ? { ...cargada, auto: true }
                          : cargada;
                  })
                : [],
        });

    const [eligiendo, setEligiendo] = useState<Eligiendo>(null);
    const rutasDelTicket = vuelta?.rutas ?? [];
    const principalDe = (sentido: Sentido) =>
        rutasDelTicket.find(
            (ruta) => ruta.sentido === sentido && !ruta.es_complemento,
        )?.ruta_id;
    const [elegida, setElegida] = useState<Record<Sentido, string | null>>({
        ida: principalDe('ida') ? String(principalDe('ida')) : null,
        regreso: principalDe('regreso')
            ? String(principalDe('regreso'))
            : vuelta && vuelta.regreso.length > 0
              ? null
              : POR_DEFINIR,
    });
    // Los complementos de cada tramo: se suman a la ruta principal.
    const [complementos, setComplementos] = useState<Record<Sentido, string[]>>(
        {
            ida: rutasDelTicket
                .filter((ruta) => ruta.sentido === 'ida' && ruta.es_complemento)
                .map((ruta) => String(ruta.ruta_id)),
            regreso: rutasDelTicket
                .filter(
                    (ruta) => ruta.sentido === 'regreso' && ruta.es_complemento,
                )
                .map((ruta) => String(ruta.ruta_id)),
        },
    );
    // La variante elegida (por peso) de cada ruta, por «tramo:ruta». Sin
    // elegir, se define después con el peso de la GR.
    const [variantes, setVariantes] = useState<Record<string, number | null>>(
        Object.fromEntries(
            rutasDelTicket.map((ruta) => [
                `${ruta.sentido}:${ruta.ruta_id}`,
                ruta.variante_ruta_id,
            ]),
        ),
    );
    const [destino, setDestino] = useState<string | null>(null);
    const primeraGuardada = vuelta?.ida[0];
    // La carga que ya sigue en este ticket: aunque la unidad tenga después
    // otra GR, la subida guardada no se pierde al editar.
    const traeGuardada: Trae | null =
        primeraGuardada?.viaje_continuado_id && vuelta
            ? {
                  viaje_id: primeraGuardada.viaje_continuado_id,
                  numero_gr: primeraGuardada.continua?.numero_gr ?? null,
                  tipo_carga: primeraGuardada.tipo_carga,
                  tipo_carga_label: primeraGuardada.tipo_carga_label,
                  ticket: primeraGuardada.continua?.ticket ?? null,
              }
            : null;
    /** La carga con la que sube a la mina la unidad elegida. */
    const traeDe = (tractoId: number | null): Trae | null =>
        (vuelta && tractoId === vuelta.tracto_id ? traeGuardada : null) ??
        opciones.tractos.find((opcion) => opcion.id === tractoId)?.trae ??
        null;
    const [subida, setSubida] = useState<Subida>(() => {
        if (!vuelta) {
            return subidaInicial(unidadInicial?.trae ?? null);
        }

        if (
            primeraGuardada?.zona_origen !== 'juliaca' ||
            primeraGuardada.zona_destino !== 'mina'
        ) {
            return 'ninguna';
        }

        if (primeraGuardada.viaje_continuado_id) {
            return 'continua';
        }

        return primeraGuardada.tipo_carga === 'vacio' ? 'vacia' : 'cargada';
    });
    // Las cargas una por una: abiertas al corregir un ticket en camino, que
    // es cuando aparece lo que no estaba en la ruta.
    const [verCargas, setVerCargas] = useState(vuelta !== null);
    const [cargaEditada, setCargaEditada] = useState<string | null>(null);
    const [confirmandoQuitar, setConfirmandoQuitar] = useState(false);

    const errores = errors as Record<string, string | undefined>;
    // Lo de una parada suelta también se dice arriba: con el editor a mano
    // cerrado, el borde rojo de su campo no se ve.
    const errorParadas =
        errores.rutas ??
        errores.paradas ??
        Object.entries(errores).find(
            ([campo]) =>
                campo.startsWith('paradas.') || campo.startsWith('rutas.'),
        )?.[1];
    const conGuias = data.paradas.some((parada) => parada.bloqueada);
    const etiquetaZona = (zona: string | null) =>
        opciones.zonas.find((opcion) => opcion.value === zona)?.label ??
        zona ??
        '¿?';
    const etiquetaCarga = (carga: string) =>
        opciones.cargas.find((opcion) => opcion.value === carga)?.label ??
        carga;

    const opcionesTractos: OpcionBuscable[] = opciones.tractos.map(
        (unidad) => ({ valor: unidad.id, etiqueta: unidad.placa }),
    );
    const opcionesCarretas: OpcionBuscable[] = opciones.carretas.map(
        (carreta) => ({ valor: carreta.id, etiqueta: carreta.placa }),
    );
    const opcionesConductores: OpcionBuscable[] = opciones.conductores.map(
        (conductor) => ({ valor: conductor.id, etiqueta: conductor.nombre }),
    );
    const opcionesClientes: OpcionBuscable[] = opciones.clientes.map(
        (cliente) => ({
            valor: cliente.id,
            etiqueta: cliente.alias,
            detalle: cliente.ruc ?? undefined,
        }),
    );

    /** Cambia las paradas y vuelve a unir el recorrido. */
    const fijarParadas = (paradas: ParadaForm[], comoSube = subida) =>
        setData(
            'paradas',
            normalizar(paradas, comoSube, traeDe(data.tracto_id)),
        );

    /**
     * Los complementos de la ida, con los que se suman solos según con qué
     * sube a la mina (con escoria, +10 gal): entran si la ruta de ida sube a
     * la mina con esa carga y salen si no. Sin ruta principal en la ida no
     * hay complementos.
     */
    const complementosDeLaIda = (
        actuales: string[],
        rutaIda: string | null,
        comoSube: Subida,
        trae: Trae | null,
    ): string[] => {
        const subeALaMina =
            opciones.tramos.ida.find((opcion) => opcion.value === rutaIda)
                ?.paradas[0]?.zona_origen === 'mina';
        const carga =
            comoSube === 'continua' && subeALaMina
                ? (trae?.tipo_carga ?? null)
                : null;
        const automaticos = opciones.tramos.ida.filter(
            (opcion) => opcion.es_complemento && opcion.sube_con !== null,
        );
        const quitar = automaticos
            .filter((opcion) => opcion.sube_con !== carga)
            .map((opcion) => opcion.value);
        const poner = automaticos
            .filter((opcion) => opcion.sube_con === carga)
            .map((opcion) => opcion.value);

        return [
            ...actuales.filter((valor) => !quitar.includes(valor)),
            ...poner.filter((valor) => !actuales.includes(valor)),
        ];
    };

    const ajustarComplementosDeSubida = (
        comoSube: Subida,
        trae: Trae | null,
    ) => {
        if (!elegida.ida || elegida.ida === POR_DEFINIR) {
            return;
        }

        setComplementos((anterior) => ({
            ...anterior,
            ida: complementosDeLaIda(anterior.ida, elegida.ida, comoSube, trae),
        }));
    };

    const elegirSubida = (comoSube: Subida) => {
        setSubida(comoSube);
        fijarParadas(data.paradas, comoSube);
        ajustarComplementosDeSubida(comoSube, traeDe(data.tracto_id));
    };

    /** Elegir la unidad deja puestos la carreta y el conductor de su última GR. */
    const elegirUnidad = (tractoId: number) => {
        const unidad = opciones.tractos.find(
            (opcion) => opcion.id === tractoId,
        );
        const trae = traeDe(tractoId);
        const comoSube = subidaInicial(trae);

        setSubida(comoSube);
        ajustarComplementosDeSubida(comoSube, trae);
        setData((anterior) => ({
            ...anterior,
            tracto_id: tractoId,
            carreta_id: unidad?.carreta_id ?? anterior.carreta_id,
            conductor_id: unidad?.conductor_id ?? anterior.conductor_id,
            paradas: normalizar(anterior.paradas, comoSube, trae),
        }));
    };

    /**
     * Elegir primero al conductor deja puestas la unidad y la carreta de su
     * última GR, como en la programación de siempre. Si la unidad ya estaba
     * elegida no se pisa: se avisa debajo del campo.
     */
    const elegirConductor = (conductorId: number) => {
        const conductor = opciones.conductores.find(
            (opcion) => opcion.id === conductorId,
        );

        if (data.tracto_id !== null || conGuias || !conductor?.tracto_id) {
            setData('conductor_id', conductorId);

            return;
        }

        const trae = traeDe(conductor.tracto_id);
        const comoSube = subidaInicial(trae);

        setSubida(comoSube);
        ajustarComplementosDeSubida(comoSube, trae);
        setData((anterior) => ({
            ...anterior,
            conductor_id: conductorId,
            tracto_id: conductor.tracto_id,
            carreta_id: conductor.carreta_id ?? anterior.carreta_id,
            paradas: normalizar(anterior.paradas, comoSube, trae),
        }));
    };

    /**
     * Arma un tramo con su ruta principal y sus complementos: reemplaza lo
     * que ese tramo todavía no tiene con GR. La carga particular a otro
     * destino necesita el destino; si no es Juliaca, la unidad descarga ahí
     * y sigue vacía a Juliaca.
     */
    const armarTramo = (
        sentido: Sentido,
        valor: string | null,
        extras: string[],
        lugar: string | null = destino,
    ) => {
        const fijas = data.paradas.filter(
            (parada) => parada.sentido !== sentido || parada.bloqueada,
        );

        if (valor === null || valor === POR_DEFINIR) {
            fijarParadas(fijas.filter((parada) => !parada.auto));

            return;
        }

        const opcion = opciones.tramos[sentido].find(
            (candidata) => candidata.value === valor,
        );

        if (!opcion) {
            return;
        }

        const elegidoDestino = opciones.tramos.destinos.find(
            (candidato) => candidato.lugar === lugar,
        );

        if (opcion.pide_destino && !elegidoDestino) {
            fijarParadas(fijas.filter((parada) => !parada.auto));

            return;
        }

        const deLaRuta = (plantilla: OpcionTramo['paradas'][number]) =>
            desdePlantilla(sentido, plantilla, elegidoDestino ?? null);
        const hechas = fijas.filter((parada) => parada.sentido === sentido);

        let nuevas = desdeDondeQuedo(opcion.paradas.map(deLaRuta), hechas);

        if (
            opcion.pide_destino &&
            elegidoDestino &&
            elegidoDestino.zona !== 'juliaca'
        ) {
            nuevas.push(
                nuevaParada(sentido, {
                    zona_origen: elegidoDestino.zona,
                    lugar_origen: elegidoDestino.lugar,
                    zona_destino: 'juliaca',
                    tipo_carga: 'vacio',
                }),
            );
        }

        for (const extra of extras) {
            const complemento = opciones.tramos[sentido].find(
                (candidata) => candidata.value === extra,
            );

            // Lo del complemento que ya tiene su GR no se repite; lo demás
            // va donde encadena con la ruta.
            nuevas = (complemento?.paradas ?? [])
                .filter(
                    (plantilla) =>
                        !hechas.some(
                            (hecha) =>
                                hecha.zona_origen === plantilla.zona_origen &&
                                hecha.zona_destino === plantilla.zona_destino,
                        ),
                )
                .map(deLaRuta)
                .reduce(encadenar, nuevas);
        }

        const ida =
            sentido === 'ida'
                ? [
                      ...fijas.filter((parada) => parada.sentido === 'ida'),
                      ...nuevas,
                  ]
                : fijas.filter((parada) => parada.sentido === 'ida');
        const regreso =
            sentido === 'regreso'
                ? [
                      ...fijas.filter((parada) => parada.sentido === 'regreso'),
                      ...nuevas,
                  ]
                : fijas.filter((parada) => parada.sentido === 'regreso');

        fijarParadas([...ida, ...regreso]);
    };

    const elegirOpcion = (
        sentido: Sentido,
        valor: string,
        lugar: string | null = destino,
    ) => {
        // La ruta que el tramo ya tiene, con GR encima: volver a tocarla no
        // rearma nada (se llevaría las cargas agregadas a mano).
        if (
            valor === elegida[sentido] &&
            lugar === destino &&
            data.paradas.some(
                (parada) => parada.sentido === sentido && parada.bloqueada,
            )
        ) {
            return;
        }

        setElegida((anterior) => ({ ...anterior, [sentido]: valor }));

        // Sin ruta principal no hay complementos. En la ida, el que se suma
        // solo por la carga con la que sube (escoria) entra si la ruta sube
        // a la mina, y sale si no.
        const extras =
            valor === POR_DEFINIR
                ? []
                : sentido === 'ida'
                  ? complementosDeLaIda(
                        complementos.ida,
                        valor,
                        subida,
                        traeDe(data.tracto_id),
                    )
                  : complementos[sentido];

        setComplementos((anterior) => ({ ...anterior, [sentido]: extras }));
        armarTramo(sentido, valor, extras, lugar);
    };

    /**
     * Sumar o quitar un complemento toca solo sus paradas: la ruta principal
     * no se vuelve a armar, así no se repite lo que ya tiene GR ni se pierde
     * lo ajustado a mano.
     */
    const alternarComplemento = (sentido: Sentido, valor: string) => {
        const quitando = complementos[sentido].includes(valor);
        const extras = quitando
            ? complementos[sentido].filter((otro) => otro !== valor)
            : [...complementos[sentido], valor];
        const plantillas =
            opciones.tramos[sentido].find(
                (candidata) => candidata.value === valor,
            )?.paradas ?? [];

        setComplementos((anterior) => ({ ...anterior, [sentido]: extras }));

        if (plantillas.length === 0) {
            return;
        }

        const delTramo = data.paradas.filter(
            (parada) => parada.sentido === sentido && !parada.auto,
        );
        // Las que unió el formulario van igual: `normalizar` las rehace
        // conservando el id de la subida.
        const otras = data.paradas.filter(
            (parada) => parada.sentido !== sentido || parada.auto,
        );
        let tramo: ParadaForm[];

        if (quitando) {
            tramo = [...delTramo];

            for (const plantilla of plantillas) {
                const indice = tramo.findLastIndex((parada) =>
                    esDeLaPlantilla(parada, plantilla),
                );

                if (indice !== -1) {
                    tramo.splice(indice, 1);
                }
            }
        } else {
            tramo = plantillas
                .filter(
                    (plantilla) =>
                        !delTramo.some(
                            (parada) =>
                                parada.bloqueada &&
                                parada.zona_origen === plantilla.zona_origen &&
                                parada.zona_destino === plantilla.zona_destino,
                        ),
                )
                .map((plantilla) => desdePlantilla(sentido, plantilla))
                .reduce(encadenar, delTramo);
        }

        fijarParadas(
            sentido === 'ida' ? [...tramo, ...otras] : [...otras, ...tramo],
        );
    };

    const elegirVariante = (
        sentido: Sentido,
        rutaId: string,
        varianteId: number | null,
    ) =>
        setVariantes((anterior) => ({
            ...anterior,
            [`${sentido}:${rutaId}`]: varianteId,
        }));

    /** Las rutas elegidas, como las guarda el ticket: principal primero. */
    const rutasElegidas = () =>
        (['ida', 'regreso'] as const).flatMap((sentido) => {
            const principal = elegida[sentido];

            if (principal === null || principal === POR_DEFINIR) {
                return [];
            }

            return [principal, ...complementos[sentido]].map((rutaId) => ({
                sentido,
                ruta_id: Number(rutaId),
                variante_ruta_id: variantes[`${sentido}:${rutaId}`] ?? null,
            }));
        });

    const elegirDestino = (lugar: string) => {
        setDestino(lugar);

        if (elegida.regreso) {
            elegirOpcion('regreso', elegida.regreso, lugar);
        }
    };

    /** El cliente va en las paradas con carga particular del tramo. */
    const clienteDe = (sentido: Sentido) =>
        data.paradas.find(
            (parada) =>
                parada.sentido === sentido &&
                !parada.bloqueada &&
                parada.tipo_carga === 'particular',
        )?.cliente_id ?? null;

    const fijarCliente = (sentido: Sentido, clienteId: number) =>
        setData(
            'paradas',
            data.paradas.map((parada) =>
                parada.sentido === sentido &&
                !parada.bloqueada &&
                parada.tipo_carga === 'particular'
                    ? { ...parada, cliente_id: clienteId, cliente: null }
                    : parada,
            ),
        );

    /** El cliente escrito a mano del tramo, si no se eligió uno del padrón. */
    const clienteEscritoDe = (sentido: Sentido) =>
        data.paradas.find(
            (parada) =>
                parada.sentido === sentido &&
                !parada.bloqueada &&
                parada.tipo_carga === 'particular',
        )?.cliente ?? '';

    // Escribirlo quita el del padrón: es uno o el otro.
    const escribirCliente = (sentido: Sentido, nombre: string) =>
        setData(
            'paradas',
            data.paradas.map((parada) =>
                parada.sentido === sentido &&
                !parada.bloqueada &&
                parada.tipo_carga === 'particular'
                    ? {
                          ...parada,
                          cliente_id: null,
                          cliente: nombre === '' ? null : nombre,
                      }
                    : parada,
            ),
        );

    const cambiarCarga = (clave: string, cambios: Partial<ParadaForm>) =>
        fijarParadas(
            data.paradas.map((parada) =>
                parada.clave === clave
                    ? { ...parada, ...cambios, auto: false }
                    : parada,
            ),
        );

    const quitarCarga = (clave: string) =>
        fijarParadas(data.paradas.filter((parada) => parada.clave !== clave));

    /**
     * Una carga que no sale de la ruta elegida (consiguió arroz en Uraca):
     * arranca donde quedó la unidad en ese tramo y, en la vuelta, va a
     * Juliaca. El vacío que ocupaba ese trayecto se va solo.
     */
    const agregarCarga = (sentido: Sentido) => {
        const delTramo = data.paradas.filter(
            (parada) => parada.sentido === sentido && !parada.auto,
        );
        const dondeQuedo =
            delTramo.findLast(
                (parada) => parada.bloqueada || parada.tipo_carga !== 'vacio',
            )?.zona_destino ??
            (sentido === 'ida'
                ? 'juliaca'
                : (data.paradas.findLast((parada) => parada.sentido === 'ida')
                      ?.zona_destino ?? ''));
        const nueva = nuevaParada(sentido, {
            zona_origen: dondeQuedo,
            zona_destino:
                sentido === 'regreso' && dondeQuedo !== 'juliaca'
                    ? 'juliaca'
                    : '',
        });
        const ida = data.paradas.filter((parada) => parada.sentido === 'ida');
        const regreso = data.paradas.filter(
            (parada) => parada.sentido === 'regreso',
        );

        fijarParadas(
            sentido === 'ida'
                ? [...ida, nueva, ...regreso]
                : [...ida, ...regreso, nueva],
        );
        setCargaEditada(nueva.clave);
    };

    const enviar = (evento: React.FormEvent) => {
        evento.preventDefault();

        const opcionesEnvio = {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        };

        transform((datos) => ({ ...datos, rutas: rutasElegidas() }));

        if (vuelta) {
            put(update(vuelta.id).url, opcionesEnvio);

            return;
        }

        post(store().url, opcionesEnvio);
    };

    const quitar = () => {
        if (!vuelta) {
            return;
        }

        router.delete(destroy(vuelta.id).url, {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        });
    };

    const buscadores = {
        tracto_id: {
            titulo: 'Unidad',
            opciones: opcionesTractos,
            valor: data.tracto_id,
            onElegir: elegirUnidad,
        },
        carreta_id: {
            titulo: 'Carreta',
            opciones: opcionesCarretas,
            valor: data.carreta_id,
            onElegir: (valor: number) => setData('carreta_id', valor),
        },
        conductor_id: {
            titulo: 'Conductor',
            opciones: opcionesConductores,
            valor: data.conductor_id,
            onElegir: elegirConductor,
        },
    } as const;

    const buscador =
        eligiendo === null
            ? null
            : eligiendo.campo === 'cliente'
              ? {
                    titulo: 'Cliente',
                    opciones: opcionesClientes,
                    valor: clienteDe(eligiendo.sentido),
                    onElegir: (valor: number) =>
                        fijarCliente(eligiendo.sentido, valor),
                }
              : eligiendo.campo === 'cliente_carga'
                ? {
                      titulo: 'Cliente',
                      opciones: opcionesClientes,
                      valor:
                          data.paradas.find(
                              (parada) => parada.clave === eligiendo.clave,
                          )?.cliente_id ?? null,
                      onElegir: (valor: number) =>
                          cambiarCarga(eligiendo.clave, {
                              cliente_id: valor,
                              cliente: null,
                          }),
                  }
                : buscadores[eligiendo.campo];

    // Sin lugar propio, una parada arranca donde terminó la anterior si es la
    // misma zona: de Paracas (la GR) sigue a Lima, no de «Pisco».
    const itinerario = data.paradas.map((parada, indice) => {
        const anterior = data.paradas[indice - 1];

        return {
            sentido: parada.sentido,
            desde:
                parada.lugar_origen ??
                (anterior?.lugar_destino &&
                anterior.zona_destino === parada.zona_origen
                    ? anterior.lugar_destino
                    : etiquetaZona(parada.zona_origen)),
            hasta: parada.lugar_destino ?? etiquetaZona(parada.zona_destino),
            carga:
                parada.tipo_carga === 'vacio'
                    ? 'vacía'
                    : etiquetaCarga(parada.tipo_carga),
            vacio: parada.tipo_carga === 'vacio',
            hecha: parada.bloqueada,
        };
    });

    const tramo = (sentido: Sentido) => {
        const subiendo = sentido === 'regreso';
        const lista: (
            | OpcionTramo
            | {
                  value: string;
                  label: string;
                  descripcion: string;
                  pide_destino: boolean;
              }
        )[] = subiendo
            ? [
                  {
                      value: POR_DEFINIR,
                      label: 'Por definir',
                      descripcion: 'Se programa cuando se sepa',
                      pide_destino: false,
                  },
                  ...opciones.tramos.regreso.filter(
                      (opcion) => !opcion.es_complemento,
                  ),
              ]
            : opciones.tramos.ida.filter((opcion) => !opcion.es_complemento);
        const extras = opciones.tramos[sentido].filter(
            (opcion) => opcion.es_complemento,
        );
        const principal = opciones.tramos[sentido].find(
            (opcion) => opcion.value === elegida[sentido],
        );
        // Las rutas elegidas que tienen dotación por peso: se elige la
        // variante si se sabe; si no, después con el peso de la GR.
        const conVariantes = [
            principal,
            ...complementos[sentido].map((valor) =>
                extras.find((opcion) => opcion.value === valor),
            ),
        ].filter(
            (opcion): opcion is OpcionTramo =>
                opcion !== undefined && opcion.variantes.length > 0,
        );
        const fijas = data.paradas.filter(
            (parada) => parada.sentido === sentido && parada.bloqueada,
        );
        // Con GR, el tramo solo sigue desde donde quedó la unidad. Sin GR,
        // la ida arranca en la base: lo de la costa (el metálico de Pisco al
        // Callao) se agrega cuando la ida ya llegó ahí.
        const dondeQuedo = fijas.at(-1)?.zona_destino ?? null;
        const bloqueoDe = (paradas: { zona_origen: string }[]) => {
            const arranca = paradas[0]?.zona_origen;

            if (!arranca) {
                return null;
            }

            if (dondeQuedo) {
                return arranca === dondeQuedo
                    ? null
                    : `La unidad ya llegó a ${etiquetaZona(dondeQuedo)}`;
            }

            return !subiendo && !['mina', 'juliaca'].includes(arranca)
                ? `Se agrega cuando la ida llegue a ${etiquetaZona(arranca)}`
                : null;
        };
        // Con GR en el tramo, las rutas que ya no aplican no se listan: solo
        // la del tramo y las que siguen desde donde quedó la unidad.
        const visibles =
            fijas.length > 0
                ? lista.filter(
                      (opcion) =>
                          elegida[sentido] === opcion.value ||
                          bloqueoDe(
                              'paradas' in opcion ? opcion.paradas : [],
                          ) === null,
                  )
                : lista;
        const ocultas = lista.length - visibles.length;
        // Una ida que arranca en la mina, sin GR todavía: se pregunta cómo
        // llega la unidad hasta ahí (casi siempre sube desde Juliaca).
        const arrancaEnMina = (opcion: { value: string }) =>
            !subiendo &&
            fijas.length === 0 &&
            'paradas' in opcion &&
            (opcion as OpcionTramo).paradas[0]?.zona_origen === 'mina';
        const traeAhora = traeDe(data.tracto_id);
        const subidas: { value: Subida; label: string }[] = [
            { value: 'vacia', label: 'Sube vacía desde Juliaca' },
            ...(traeAhora
                ? [
                      {
                          value: 'continua' as const,
                          label: `Sigue con la ${traeAhora.tipo_carga_label.toLowerCase()} que trae${traeAhora.numero_gr ? ` (GR ${traeAhora.numero_gr})` : ''}`,
                      },
                  ]
                : []),
            {
                value: 'cargada',
                label: 'Sube con carga nueva desde Juliaca (Minsur u otro)',
            },
            // Solo para tickets viejos que arrancaban en la mina.
            ...(subida === 'ninguna'
                ? [{ value: 'ninguna' as const, label: 'Ya está en la mina' }]
                : []),
        ];
        const selectorSubida = (
            <div className="flex flex-col gap-2">
                <span className="flex items-center gap-1 text-xs font-medium text-muted-foreground">
                    <MapPin className="size-3.5" />
                    ¿Cómo sube de Juliaca a la mina?
                </span>
                <div className="flex flex-wrap gap-1.5">
                    {subidas.map((candidata) => (
                        <button
                            key={candidata.value}
                            type="button"
                            onClick={() => elegirSubida(candidata.value)}
                            className={cn(
                                'rounded-full border px-3 py-1 text-xs transition',
                                subida === candidata.value
                                    ? 'border-amber-500 bg-amber-500 text-white'
                                    : 'bg-background hover:bg-accent',
                            )}
                        >
                            {candidata.label}
                        </button>
                    ))}
                </div>
                {subida === 'cargada' && (
                    <span className="text-[11px] text-muted-foreground">
                        La GR de esa carga (Juliaca → San Rafael) se asigna a
                        esta subida desde «GR por asignar».
                    </span>
                )}
                {subida === 'continua' && traeAhora && (
                    <span className="text-[11px] text-muted-foreground">
                        Sigue la GR {traeAhora.numero_gr ?? 'anterior'}
                        {traeAhora.ticket && ` del ${traeAhora.ticket}`}: su
                        combustible le alcanzó hasta Juliaca y la subida va en
                        esta ida, sin GR nueva.
                        {traeAhora.tipo_carga === 'escoria' &&
                            ' Con escoria se suma «Sube con escoria» (+10 gal).'}
                    </span>
                )}
            </div>
        );
        // Los complementos van dentro de la ruta elegida, donde se hace
        // clic: al final de una lista larga no se veían.
        const selectorComplementos = extras.length > 0 && (
            <div className="flex flex-col gap-2">
                <span className="text-xs font-medium text-muted-foreground">
                    Complementos (se suman a la ruta)
                </span>
                <div className="flex flex-wrap gap-1.5">
                    {extras.map((extra) => {
                        const marcado = complementos[sentido].includes(
                            extra.value,
                        );

                        return (
                            <button
                                key={extra.value}
                                type="button"
                                title={
                                    [extra.descripcion, extra.dotacion]
                                        .filter(Boolean)
                                        .join(' · ') || undefined
                                }
                                aria-pressed={marcado}
                                onClick={() =>
                                    alternarComplemento(sentido, extra.value)
                                }
                                className={cn(
                                    'rounded-full border px-3 py-1 text-xs transition',
                                    marcado
                                        ? subiendo
                                            ? 'border-violet-500 bg-violet-500 text-white'
                                            : 'border-amber-500 bg-amber-500 text-white'
                                        : 'hover:bg-accent',
                                )}
                            >
                                {marcado ? '✓ ' : '+ '}
                                {extra.label}
                            </button>
                        );
                    })}
                </div>
            </div>
        );
        // Con las cargas abiertas abajo, el cliente se pone en cada una.
        const conParticular =
            !verCargas &&
            data.paradas.some(
                (parada) =>
                    parada.sentido === sentido &&
                    !parada.bloqueada &&
                    parada.tipo_carga === 'particular',
            );

        return (
            <section className="flex flex-col gap-3 rounded-xl border p-4">
                <h3
                    className={cn(
                        'flex items-center gap-1.5 text-sm font-semibold',
                        subiendo
                            ? 'text-violet-700 dark:text-violet-400'
                            : 'text-amber-700 dark:text-amber-400',
                    )}
                >
                    {subiendo ? (
                        <ArrowUpRight className="size-4" />
                    ) : (
                        <ArrowDownRight className="size-4" />
                    )}
                    {subiendo ? 'Vuelta' : 'Ida'}
                </h3>

                {fijas.length > 0 && (
                    <p className="flex items-center gap-1.5 rounded-lg bg-muted px-3 py-2 text-xs text-muted-foreground">
                        <Lock className="size-3.5" />
                        Ya tiene GR hasta {etiquetaZona(dondeQuedo)}: lo que
                        elijas sigue desde ahí.
                    </p>
                )}

                <div className="grid gap-2">
                    {visibles.map((opcion) => (
                        <TarjetaOpcion
                            key={opcion.value}
                            icono={
                                opcion.value === POR_DEFINIR ? (
                                    <CircleHelp className="size-4" />
                                ) : (
                                    iconoDe(
                                        'paradas' in opcion
                                            ? opcion.paradas
                                            : [],
                                    )
                                )
                            }
                            titulo={opcion.label}
                            descripcion={
                                arrancaEnMina(opcion)
                                    ? conSubida(opcion.descripcion, subida)
                                    : opcion.descripcion
                            }
                            dotacion={
                                'dotacion' in opcion ? opcion.dotacion : null
                            }
                            sentido={sentido}
                            elegida={elegida[sentido] === opcion.value}
                            // La ruta del tramo se queda como está: el
                            // candado es para las que ya no aplican.
                            bloqueo={
                                elegida[sentido] === opcion.value
                                    ? null
                                    : bloqueoDe(
                                          'paradas' in opcion
                                              ? opcion.paradas
                                              : [],
                                      )
                            }
                            onElegir={() => elegirOpcion(sentido, opcion.value)}
                        >
                            {(arrancaEnMina(opcion) ||
                                (opcion.value !== POR_DEFINIR &&
                                    selectorComplementos)) && (
                                <div className="flex flex-col gap-3">
                                    {arrancaEnMina(opcion) && selectorSubida}
                                    {opcion.value !== POR_DEFINIR &&
                                        selectorComplementos}
                                </div>
                            )}
                        </TarjetaOpcion>
                    ))}
                </div>

                {ocultas > 0 && (
                    <p className="text-[11px] text-muted-foreground">
                        {ocultas === 1
                            ? 'Hay 1 ruta más que ya no aplica'
                            : `Hay ${ocultas} rutas más que ya no aplican`}
                        : la unidad ya llegó a {etiquetaZona(dondeQuedo)}. Para
                        otra carga usa «Agregar carga», abajo.
                    </p>
                )}

                {conVariantes.map((ruta) => {
                    const clave = `${sentido}:${ruta.value}`;
                    const actual = variantes[clave] ?? null;

                    return (
                        <div key={clave} className="flex flex-col gap-2">
                            <span className="text-xs font-medium text-muted-foreground">
                                {ruta.label}: ¿cuánto pesa la carga?
                            </span>
                            <div className="flex flex-wrap gap-1.5">
                                {[
                                    {
                                        id: null,
                                        nombre: 'Según la GR',
                                        tope: null,
                                    },
                                    ...ruta.variantes,
                                ].map((variante) => (
                                    <button
                                        key={variante.id ?? 'gr'}
                                        type="button"
                                        title={
                                            variante.id === null
                                                ? 'Se elige después, con el peso de la GR'
                                                : variante.tope !== null
                                                  ? `${variante.tope} gal`
                                                  : undefined
                                        }
                                        onClick={() =>
                                            elegirVariante(
                                                sentido,
                                                ruta.value,
                                                variante.id,
                                            )
                                        }
                                        className={cn(
                                            'rounded-full border px-3 py-1 text-xs transition',
                                            actual === variante.id
                                                ? 'border-emerald-600 bg-emerald-600 text-white'
                                                : 'hover:bg-accent',
                                        )}
                                    >
                                        {variante.nombre}
                                    </button>
                                ))}
                            </div>
                        </div>
                    );
                })}

                {subiendo &&
                    opciones.tramos.regreso.find(
                        (opcion) => opcion.value === elegida.regreso,
                    )?.pide_destino && (
                        <div className="flex flex-col gap-2">
                            <span className="flex items-center gap-1 text-xs font-medium text-muted-foreground">
                                <MapPin className="size-3.5" />
                                ¿A dónde va? Si no es Juliaca, de ahí sigue a
                                Juliaca.
                            </span>
                            <div className="flex flex-wrap gap-1.5">
                                {opciones.tramos.destinos.map((candidato) => (
                                    <button
                                        key={candidato.lugar}
                                        type="button"
                                        onClick={() =>
                                            elegirDestino(candidato.lugar)
                                        }
                                        className={cn(
                                            'rounded-full border px-3 py-1 text-xs transition',
                                            destino === candidato.lugar
                                                ? 'border-violet-500 bg-violet-500 text-white'
                                                : 'hover:bg-accent',
                                        )}
                                    >
                                        {candidato.lugar}
                                    </button>
                                ))}
                            </div>
                        </div>
                    )}

                {conParticular && (
                    <Field label="Cliente">
                        {() => (
                            <CampoSelector
                                etiqueta="Cliente (opcional)"
                                elegida={opcionesClientes.find(
                                    (opcion) =>
                                        opcion.valor === clienteDe(sentido),
                                )}
                                onAbrir={() =>
                                    setEligiendo({ campo: 'cliente', sentido })
                                }
                            />
                        )}
                    </Field>
                )}
                {/* Al padrón entra a quien se le factura: un cliente nuevo
                    se escribe acá y el ticket no espera a que lo den de
                    alta. */}
                {conParticular && clienteDe(sentido) === null && (
                    <Field
                        label="Cliente que no está en la lista"
                        ayuda="Escríbelo tal como lo conoces; se puede cambiar después."
                    >
                        {(id) => (
                            <Input
                                id={id}
                                value={clienteEscritoDe(sentido)}
                                onChange={(evento) =>
                                    escribirCliente(
                                        sentido,
                                        evento.target.value,
                                    )
                                }
                                maxLength={150}
                                placeholder="Nombre del cliente"
                            />
                        )}
                    </Field>
                )}
            </section>
        );
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="flex max-h-[92dvh] flex-col gap-0 overflow-hidden p-0 md:max-w-4xl">
                {buscador !== null ? (
                    <div className="p-4 sm:p-6">
                        <DialogHeader className="sr-only">
                            <DialogTitle>
                                Elegir {buscador.titulo.toLowerCase()}
                            </DialogTitle>
                        </DialogHeader>
                        <PanelBuscador
                            titulo={buscador.titulo}
                            valor={buscador.valor}
                            opciones={buscador.opciones}
                            onElegir={(valor) => {
                                buscador.onElegir(valor);
                                setEligiendo(null);
                            }}
                            onVolver={() => setEligiendo(null)}
                        />
                    </div>
                ) : (
                    <>
                        <DialogHeader className="border-b px-6 pt-6 pb-4">
                            <DialogTitle className="text-lg">
                                {vuelta
                                    ? `Ticket ${vuelta.codigo ?? ''}`
                                    : 'Nuevo ticket'}
                            </DialogTitle>
                            <DialogDescription>
                                Programa la ida al salir; la vuelta, cuando se
                                sepa con qué regresa. Las GR se asignan a su
                                parada desde «GR por asignar».
                            </DialogDescription>
                        </DialogHeader>

                        <form
                            id="programar-vuelta"
                            onSubmit={enviar}
                            className="flex min-h-0 flex-1 flex-col gap-5 overflow-y-auto px-6 py-5"
                        >
                            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                <Field
                                    label="Unidad"
                                    error={errores.tracto_id}
                                    required
                                >
                                    {(id) => (
                                        <CampoSelector
                                            id={id}
                                            etiqueta="Unidad"
                                            elegida={opcionesTractos.find(
                                                (opcion) =>
                                                    opcion.valor ===
                                                    data.tracto_id,
                                            )}
                                            invalido={Boolean(
                                                errores.tracto_id,
                                            )}
                                            onAbrir={() =>
                                                !conGuias &&
                                                setEligiendo({
                                                    campo: 'tracto_id',
                                                })
                                            }
                                        />
                                    )}
                                </Field>
                                <Field
                                    label="Sale el"
                                    error={errores.inicio}
                                    required
                                >
                                    {(id) => (
                                        <Input
                                            id={id}
                                            type="date"
                                            value={data.inicio}
                                            onChange={(evento) =>
                                                setData(
                                                    'inicio',
                                                    evento.target.value,
                                                )
                                            }
                                        />
                                    )}
                                </Field>
                                <Field
                                    label="Conductor"
                                    error={errores.conductor_id}
                                >
                                    {(id) => (
                                        <CampoSelector
                                            id={id}
                                            etiqueta="Conductor"
                                            elegida={opcionesConductores.find(
                                                (opcion) =>
                                                    opcion.valor ===
                                                    data.conductor_id,
                                            )}
                                            onAbrir={() =>
                                                setEligiendo({
                                                    campo: 'conductor_id',
                                                })
                                            }
                                        />
                                    )}
                                </Field>
                                <Field
                                    label="Carreta"
                                    error={errores.carreta_id}
                                >
                                    {(id) => (
                                        <CampoSelector
                                            id={id}
                                            etiqueta="Carreta"
                                            elegida={opcionesCarretas.find(
                                                (opcion) =>
                                                    opcion.valor ===
                                                    data.carreta_id,
                                            )}
                                            onAbrir={() =>
                                                setEligiendo({
                                                    campo: 'carreta_id',
                                                })
                                            }
                                        />
                                    )}
                                </Field>
                            </div>

                            <div className="rounded-xl bg-muted/40 p-4">
                                <span className="mb-3 block text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                    Recorrido
                                </span>
                                <ItinerarioVuelta paradas={itinerario} />
                            </div>

                            {errorParadas && (
                                <p className="text-sm text-destructive">
                                    {errorParadas}
                                </p>
                            )}

                            <div className="grid gap-4 md:grid-cols-2">
                                {tramo('ida')}
                                {tramo('regreso')}
                            </div>

                            <div className="flex flex-col gap-3">
                                <button
                                    type="button"
                                    onClick={() => setVerCargas(!verCargas)}
                                    aria-expanded={verCargas}
                                    className="flex items-center gap-1 self-start text-sm font-medium hover:text-foreground"
                                >
                                    <ChevronDown
                                        className={cn(
                                            'size-4 transition',
                                            verCargas && 'rotate-180',
                                        )}
                                    />
                                    Cargas del recorrido
                                    <span className="font-normal text-muted-foreground">
                                        · para lo que no sale de una ruta de la
                                        tabla
                                    </span>
                                </button>

                                {verCargas && (
                                    <CargasDelRecorrido
                                        paradas={data.paradas}
                                        textos={itinerario}
                                        zonas={opciones.zonas}
                                        cargas={opciones.cargas}
                                        clientes={opcionesClientes}
                                        errores={errores}
                                        editando={cargaEditada}
                                        onEditar={setCargaEditada}
                                        onCambiar={cambiarCarga}
                                        onQuitar={quitarCarga}
                                        onAgregar={agregarCarga}
                                        onElegirCliente={(clave) =>
                                            setEligiendo({
                                                campo: 'cliente_carga',
                                                clave,
                                            })
                                        }
                                    />
                                )}
                            </div>
                        </form>

                        <DialogFooter className="gap-2 border-t px-6 py-4 sm:justify-between">
                            {vuelta?.quitable ? (
                                <Button
                                    type="button"
                                    variant={
                                        confirmandoQuitar
                                            ? 'destructive'
                                            : 'ghost'
                                    }
                                    disabled={processing}
                                    onClick={() =>
                                        confirmandoQuitar
                                            ? quitar()
                                            : setConfirmandoQuitar(true)
                                    }
                                >
                                    {confirmandoQuitar
                                        ? '¿Seguro? Quitar ticket'
                                        : 'Quitar ticket'}
                                </Button>
                            ) : (
                                <span />
                            )}
                            <Button
                                type="submit"
                                form="programar-vuelta"
                                disabled={
                                    processing || data.paradas.length === 0
                                }
                            >
                                {vuelta ? 'Guardar cambios' : 'Programar'}
                            </Button>
                        </DialogFooter>
                    </>
                )}
            </DialogContent>
        </Dialog>
    );
}
