import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { Copy, FileText, Plus, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import cotizaciones, {
    cotizador,
    create,
} from '@/actions/App/Http/Controllers/CotizacionController';
import { CotizacionesTabs } from '@/components/cotizaciones/cotizaciones-tabs';
import { HojaTarifa } from '@/components/cotizaciones/hoja-tarifa';
import type { ColumnaHoja } from '@/components/cotizaciones/hoja-tarifa';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { calcularTarifa } from '@/lib/tarifa';
import type { LineaTarifa } from '@/types/fleet';

type Props = {
    lineas: LineaTarifa[];
    /** En tanto por uno: 0.12 es 12 %. */
    margen_pct_default: number;
    igv_pct: number;
};

/** Lo que se tipea de una ruta. Todo como texto, tal cual está en el campo. */
type Ruta = {
    id: string;
    cliente: string;
    destino: string;
    material: string;
    dias: string;
    km: string;
};

type Hoja = { rutas: Ruta[]; margen: string };

/** Más columnas no entran en pantalla ni se comparan de un vistazo. */
const MAXIMO_RUTAS = 6;

/**
 * La hoja queda guardada en este navegador para no perderla al ir a emitir
 * una cotización y volver. Es comodidad, no registro: lo que vale se emite.
 */
const CLAVE_ALMACEN = 'cotizador-rutas';

/**
 * Solo distingue una columna de otra dentro de la hoja. No se usa
 * `crypto.randomUUID()` porque el navegador lo esconde fuera de https, y el
 * sistema también se abre por http.
 */
function nuevoId(): string {
    return `${Date.now().toString(36)}-${Math.random().toString(36).slice(2)}`;
}

function rutaVacia(): Ruta {
    return {
        id: nuevoId(),
        cliente: '',
        destino: '',
        material: '',
        dias: '',
        km: '',
    };
}

function leerHoja(margenPorDefecto: number): Hoja {
    const vacia = {
        rutas: [rutaVacia()],
        margen: (margenPorDefecto * 100).toString(),
    };

    try {
        const guardada = window.localStorage.getItem(CLAVE_ALMACEN);

        if (!guardada) {
            return vacia;
        }

        const hoja = JSON.parse(guardada) as Hoja;

        return Array.isArray(hoja.rutas) && hoja.rutas.length > 0
            ? hoja
            : vacia;
    } catch {
        return vacia;
    }
}

/**
 * El cotizador rápido: la hoja de rutas del Excel, con el tarifario vigente.
 * Se tipean días y kilómetros y la tarifa sale al instante; varias rutas se
 * comparan lado a lado. Nada se guarda hasta que se emite una.
 */
export default function Cotizador({
    lineas,
    margen_pct_default,
    igv_pct,
}: Props) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Cotizaciones', href: cotizaciones.index().url },
            { title: 'Cotizador', href: cotizador().url },
        ],
    });

    const [hoja, setHoja] = useState<Hoja>(() => leerHoja(margen_pct_default));

    useEffect(() => {
        try {
            window.localStorage.setItem(CLAVE_ALMACEN, JSON.stringify(hoja));
        } catch {
            // Sin almacenamiento (ventana privada) la hoja igual funciona.
        }
    }, [hoja]);

    const margenPct = (Number(hoja.margen) || 0) / 100;
    const margenValido = margenPct >= 0 && margenPct < 1;

    const cambiarRuta = (id: string, campo: keyof Ruta, valor: string) =>
        setHoja((actual) => ({
            ...actual,
            rutas: actual.rutas.map((ruta) =>
                ruta.id === id ? { ...ruta, [campo]: valor } : ruta,
            ),
        }));

    const agregarRuta = (base?: Ruta) =>
        setHoja((actual) => ({
            ...actual,
            rutas: [
                ...actual.rutas,
                base ? { ...base, id: nuevoId() } : rutaVacia(),
            ],
        }));

    const quitarRuta = (id: string) =>
        setHoja((actual) => ({
            ...actual,
            rutas: actual.rutas.filter((ruta) => ruta.id !== id),
        }));

    const limpiar = () =>
        setHoja({
            rutas: [rutaVacia()],
            margen: (margen_pct_default * 100).toString(),
        });

    const columnas: ColumnaHoja[] = hoja.rutas.map((ruta, indice) => {
        const km = Number(ruta.km) || 0;
        const dias = Number(ruta.dias) || 0;
        const completa = km > 0 && dias > 0 && margenValido;

        return {
            clave: ruta.id,
            kmNumero: km,
            cabecera: (
                <div className="flex flex-col gap-1">
                    <Input
                        aria-label={`Cliente de la ruta ${indice + 1}`}
                        value={ruta.cliente}
                        onChange={(e) =>
                            cambiarRuta(
                                ruta.id,
                                'cliente',
                                e.target.value.toUpperCase(),
                            )
                        }
                        placeholder="Cliente"
                        className="h-8 text-center font-semibold"
                    />
                    <Input
                        aria-label={`Destino de la ruta ${indice + 1}`}
                        value={ruta.destino}
                        onChange={(e) =>
                            cambiarRuta(
                                ruta.id,
                                'destino',
                                e.target.value.toUpperCase(),
                            )
                        }
                        placeholder="Destino"
                        className="h-8 text-center"
                    />
                    <Input
                        aria-label={`Material de la ruta ${indice + 1}`}
                        value={ruta.material}
                        onChange={(e) =>
                            cambiarRuta(
                                ruta.id,
                                'material',
                                e.target.value.toUpperCase(),
                            )
                        }
                        placeholder="Material"
                        className="h-8 text-center text-xs"
                    />
                </div>
            ),
            dias: (
                <Input
                    aria-label={`Días de la ruta ${indice + 1}`}
                    type="number"
                    inputMode="decimal"
                    step="0.5"
                    min={0.5}
                    value={ruta.dias}
                    onChange={(e) =>
                        cambiarRuta(ruta.id, 'dias', e.target.value)
                    }
                    placeholder="9"
                    className="h-8 bg-background text-right font-mono tabular-nums"
                />
            ),
            km: (
                <Input
                    aria-label={`Kilómetros de la ruta ${indice + 1}`}
                    type="number"
                    inputMode="numeric"
                    min={1}
                    value={ruta.km}
                    onChange={(e) => cambiarRuta(ruta.id, 'km', e.target.value)}
                    placeholder="1275"
                    className="h-8 bg-background text-right font-mono tabular-nums"
                />
            ),
            resultado: completa
                ? calcularTarifa(lineas, { km, dias, margenPct }, igv_pct)
                : null,
            pie: (
                <div className="flex items-center gap-1">
                    {completa ? (
                        <Button asChild size="sm" className="flex-1">
                            <Link
                                href={create({
                                    query: {
                                        cliente_nombre: ruta.cliente,
                                        destino: ruta.destino,
                                        material: ruta.material,
                                        km: Math.round(km).toString(),
                                        dias: dias.toString(),
                                        margen_pct: margenPct.toString(),
                                    },
                                })}
                            >
                                <FileText className="size-4" />
                                Emitir
                            </Link>
                        </Button>
                    ) : (
                        <Button size="sm" className="flex-1" disabled>
                            <FileText className="size-4" />
                            Emitir
                        </Button>
                    )}
                    <Button
                        type="button"
                        size="icon"
                        variant="ghost"
                        className="size-8"
                        title="Duplicar ruta"
                        aria-label={`Duplicar la ruta ${indice + 1}`}
                        disabled={hoja.rutas.length >= MAXIMO_RUTAS}
                        onClick={() => agregarRuta(ruta)}
                    >
                        <Copy className="size-4" />
                    </Button>
                    <Button
                        type="button"
                        size="icon"
                        variant="ghost"
                        className="size-8"
                        title="Quitar ruta"
                        aria-label={`Quitar la ruta ${indice + 1}`}
                        disabled={hoja.rutas.length === 1}
                        onClick={() => quitarRuta(ruta.id)}
                    >
                        <Trash2 className="size-4" />
                    </Button>
                </div>
            ),
        };
    });

    return (
        <div className="mx-auto flex w-full max-w-[1400px] flex-col gap-4 p-4 md:p-6">
            <Head title="Cotizador de rutas" />

            <CotizacionesTabs actual="cotizador" />

            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h1 className="text-xl font-semibold tracking-tight">
                        Cotizador de rutas
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Días y kilómetros de cada ruta contra el tarifario
                        vigente. Nada se guarda hasta que emitís la cotización.
                    </p>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <Button type="button" variant="ghost" onClick={limpiar}>
                        Limpiar hoja
                    </Button>
                    <Button
                        type="button"
                        onClick={() => agregarRuta()}
                        disabled={hoja.rutas.length >= MAXIMO_RUTAS}
                    >
                        <Plus className="size-4" />
                        Agregar ruta
                    </Button>
                </div>
            </div>

            <HojaTarifa
                lineas={lineas}
                columnas={columnas}
                igvPct={igv_pct}
                margen={
                    <div className="flex items-center justify-end gap-1">
                        <Input
                            aria-label="Margen de operación (%)"
                            type="number"
                            inputMode="decimal"
                            step="0.5"
                            min={0}
                            max={90}
                            value={hoja.margen}
                            onChange={(e) =>
                                setHoja((actual) => ({
                                    ...actual,
                                    margen: e.target.value,
                                }))
                            }
                            className="h-7 w-16 text-right font-mono tabular-nums"
                        />
                        <span className="text-xs">%</span>
                    </div>
                }
            />

            <p className="text-xs text-muted-foreground">
                {margenValido
                    ? 'El margen es sobre la tarifa: con 12 %, el costo operativo es el 88 % del precio.'
                    : 'El margen tiene que estar entre 0 y 90 %.'}
            </p>
        </div>
    );
}
