import { Head, Link, setLayoutProps, usePage } from '@inertiajs/react';
import {
    CalendarDays,
    Cog,
    FileText,
    Gauge,
    Palette,
    Pencil,
    Scale,
    Settings2,
    Trash2,
    Truck,
    Wrench,
} from 'lucide-react';
import vehiculos, {
    edit,
    show,
} from '@/actions/App/Http/Controllers/VehiculoController';
import { Copiable } from '@/components/copiable';
import { Button } from '@/components/ui/button';
import { AgregarDocumentoDialog } from '@/components/vehiculos/agregar-documento-dialog';
import { DeleteVehiculoDialog } from '@/components/vehiculos/delete-vehiculo-dialog';
import { DocumentoFilaVehiculo } from '@/components/vehiculos/documento-fila-vehiculo';
import { DocumentoRanura } from '@/components/vehiculos/documento-ranura';
import { EstadoBadge } from '@/components/vehiculos/estado-badge';
import { formatearFecha, formatearPlaca } from '@/lib/format';
import { cn } from '@/lib/utils';
import type {
    EnumOption,
    EstadoDocumental,
    RanuraDocumental,
    Vehiculo,
} from '@/types/fleet';
import { cajaLabels, tipoLabels } from '@/types/fleet';

type Props = {
    vehiculo: Vehiculo;
    documentacion: EstadoDocumental;
    ranuras: RanuraDocumental[];
    tiposDocumento: EnumOption[];
};

/**
 * La ficha del vehículo: identidad y datos generales a la izquierda, y a la
 * derecha lo que se viene a mirar —el expediente documental— con la ficha
 * técnica debajo. Los datos no se repiten entre columnas: lo general vive a
 * la izquierda y lo técnico (motor, VIN, pesos) a la derecha.
 */
export default function VehiculoShow({
    vehiculo,
    documentacion,
    ranuras,
    tiposDocumento,
}: Props) {
    const { auth } = usePage().props;
    const puedeGestionar = auth.roles.includes('admin');
    const esTracto = vehiculo.tipo === 'tracto';

    const obligatorias = ranuras.filter((ranura) => ranura.obligatorio);
    const sueltas = ranuras.filter((ranura) => !ranura.obligatorio);
    const ordenadas = [...obligatorias, ...sueltas];

    const pendientes =
        documentacion.faltantes.length +
        documentacion.vencidos.length +
        documentacion.por_vencer.length;

    setLayoutProps({
        breadcrumbs: [
            {
                title: esTracto ? 'Tractos' : 'Carretas',
                href: (esTracto ? vehiculos.tractos() : vehiculos.carretas())
                    .url,
            },
            {
                title: formatearPlaca(vehiculo.placa),
                href: show(vehiculo.id).url,
            },
        ],
    });

    return (
        <div className="mx-auto flex h-full w-full max-w-[1400px] flex-1 flex-col gap-4 p-4 md:p-6">
            <Head title={formatearPlaca(vehiculo.placa)} />

            <div className="grid gap-4 lg:grid-cols-[280px_minmax(0,1fr)]">
                <Identidad
                    vehiculo={vehiculo}
                    esTracto={esTracto}
                    puedeGestionar={puedeGestionar}
                />

                <div className="flex min-w-0 flex-col gap-4">
                    <section className="rounded-xl border border-border bg-card">
                        <div className="flex flex-wrap items-center justify-between gap-2 border-b p-4">
                            <h2 className="flex items-center gap-2 text-sm font-semibold">
                                <FileText className="size-4 text-muted-foreground" />
                                Documentos
                                <span
                                    className={cn(
                                        'font-normal',
                                        pendientes === 0
                                            ? 'text-muted-foreground'
                                            : 'text-red-700 dark:text-red-400',
                                    )}
                                >
                                    {pendientes === 0
                                        ? '· todo al día'
                                        : `· ${resumenPendientes(documentacion)}`}
                                </span>
                            </h2>

                            {puedeGestionar && (
                                <AgregarDocumentoDialog
                                    vehiculoId={vehiculo.id}
                                    tipos={tiposDocumento}
                                />
                            )}
                        </div>

                        {/* En el celular la tabla no entra: se cae a las mismas
                            tarjetas del expediente del conductor. */}
                        <div className="flex flex-col gap-2 p-3 sm:hidden">
                            {ordenadas.map((ranura) => (
                                <DocumentoRanura
                                    key={ranura.documento?.id ?? ranura.tipo}
                                    ranura={ranura}
                                    vehiculoId={vehiculo.id}
                                    tipos={tiposDocumento}
                                    puedeGestionar={puedeGestionar}
                                />
                            ))}
                        </div>

                        <div className="hidden overflow-hidden sm:block">
                            <table className="w-full table-fixed text-sm">
                                <thead>
                                    <tr className="border-b text-left text-xs text-muted-foreground">
                                        <th className="p-3 font-medium">
                                            Documento
                                        </th>
                                        <th className="w-32 p-3 font-medium">
                                            Vencimiento
                                        </th>
                                        <th className="w-32 p-3 font-medium">
                                            Estado
                                        </th>
                                        <th className="w-28 p-3 text-right font-medium">
                                            Acciones
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {ordenadas.map((ranura) => (
                                        <DocumentoFilaVehiculo
                                            key={
                                                ranura.documento?.id ??
                                                ranura.tipo
                                            }
                                            ranura={ranura}
                                            vehiculoId={vehiculo.id}
                                            tipos={tiposDocumento}
                                            puedeGestionar={puedeGestionar}
                                        />
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <FichaTecnica vehiculo={vehiculo} esTracto={esTracto} />
                </div>
            </div>
        </div>
    );
}

/** La columna que identifica al fierro: placa, tipo, estado y datos generales. */
function Identidad({
    vehiculo,
    esTracto,
    puedeGestionar,
}: {
    vehiculo: Vehiculo;
    esTracto: boolean;
    puedeGestionar: boolean;
}) {
    return (
        <section className="flex flex-col gap-5 rounded-xl border border-border bg-card p-5 lg:sticky lg:top-4 lg:self-start">
            <div className="flex flex-col gap-3">
                <div className="flex items-start justify-between gap-2">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        {formatearPlaca(vehiculo.placa)}
                    </h1>
                    <EstadoBadge estado={vehiculo.estado} />
                </div>

                <div className="flex flex-wrap gap-2">
                    <span className="rounded-md bg-muted px-2 py-0.5 text-xs font-medium">
                        {tipoLabels[vehiculo.tipo] ?? vehiculo.tipo}
                    </span>
                    {vehiculo.anio && (
                        <span className="rounded-md bg-muted px-2 py-0.5 text-xs font-medium tabular-nums">
                            {vehiculo.anio}
                        </span>
                    )}
                </div>

                {puedeGestionar && (
                    <div className="flex gap-2">
                        <Button
                            asChild
                            variant="outline"
                            size="sm"
                            className="flex-1"
                        >
                            <Link href={edit(vehiculo.id)}>
                                <Pencil className="size-4" />
                                Editar
                            </Link>
                        </Button>
                        <DeleteVehiculoDialog
                            vehiculo={vehiculo}
                            trigger={
                                <Button
                                    variant="outline"
                                    size="sm"
                                    className="text-muted-foreground hover:text-destructive"
                                    aria-label="Eliminar vehículo"
                                >
                                    <Trash2 className="size-4" />
                                </Button>
                            }
                        />
                    </div>
                )}
            </div>

            <ul className="flex flex-col gap-2.5 border-t pt-4 text-sm">
                <DatoLateral icono={Truck} label="Marca">
                    {vehiculo.marca ?? '—'}
                </DatoLateral>
                <DatoLateral icono={Settings2} label="Modelo">
                    {vehiculo.modelo ?? '—'}
                </DatoLateral>
                <DatoLateral icono={Palette} label="Color">
                    {vehiculo.color ?? '—'}
                </DatoLateral>
                {esTracto && (
                    <DatoLateral icono={Wrench} label="Caja">
                        {vehiculo.caja
                            ? (cajaLabels[vehiculo.caja] ?? vehiculo.caja)
                            : '—'}
                    </DatoLateral>
                )}
                <DatoLateral icono={CalendarDays} label="Adquisición">
                    {vehiculo.fecha_adquisicion
                        ? formatearFecha(vehiculo.fecha_adquisicion)
                        : '—'}
                </DatoLateral>
            </ul>

            {vehiculo.observaciones && (
                <div className="border-t pt-4">
                    <p className="text-xs text-muted-foreground">
                        Observaciones
                    </p>
                    <p className="mt-1 text-sm whitespace-pre-line">
                        {vehiculo.observaciones}
                    </p>
                </div>
            )}
        </section>
    );
}

function DatoLateral({
    icono: Icono,
    label,
    children,
}: {
    icono: typeof Truck;
    label: string;
    children: React.ReactNode;
}) {
    return (
        <li className="flex items-center justify-between gap-3">
            <span className="flex items-center gap-2.5 text-muted-foreground">
                <Icono className="size-4 shrink-0" />
                {label}
            </span>
            <span className="min-w-0 truncate font-medium">{children}</span>
        </li>
    );
}

/**
 * Lo técnico: lo que se consulta al comparar unidades o al armar papeles —
 * ejes, motor, VIN y los tres pesos de la tarjeta de propiedad.
 */
function FichaTecnica({
    vehiculo,
    esTracto,
}: {
    vehiculo: Vehiculo;
    esTracto: boolean;
}) {
    return (
        <section className="rounded-xl border border-border bg-card">
            <div className="border-b p-4">
                <h2 className="flex items-center gap-2 text-sm font-semibold">
                    <Gauge className="size-4 text-muted-foreground" />
                    Ficha técnica y pesos
                </h2>
            </div>

            <div className="grid gap-3 p-4 sm:grid-cols-2 xl:grid-cols-3">
                <Tile icono={Cog} label="Ejes">
                    {vehiculo.ejes ? String(vehiculo.ejes) : '—'}
                </Tile>
                {esTracto && (
                    <Tile icono={Wrench} label="N.º de motor">
                        {vehiculo.numero_motor ?? '—'}
                    </Tile>
                )}
                <Tile icono={FileText} label="VIN">
                    {vehiculo.vin ? (
                        <Copiable valor={vehiculo.vin} etiqueta="VIN">
                            <span className="font-mono text-sm">
                                {vehiculo.vin}
                            </span>
                        </Copiable>
                    ) : (
                        '—'
                    )}
                </Tile>
                <Tile icono={Scale} label="Peso neto">
                    {formatearKg(vehiculo.peso_neto)}
                </Tile>
                <Tile icono={Scale} label="Peso bruto">
                    {formatearKg(vehiculo.peso_bruto)}
                </Tile>
                <Tile icono={Scale} label="Carga útil">
                    {formatearKg(vehiculo.carga_util)}
                </Tile>
            </div>
        </section>
    );
}

function Tile({
    icono: Icono,
    label,
    children,
}: {
    icono: typeof Cog;
    label: string;
    children: React.ReactNode;
}) {
    return (
        <div className="flex items-center gap-3 rounded-lg border border-border p-3">
            <span
                className="grid size-9 shrink-0 place-items-center rounded-md bg-muted text-muted-foreground"
                aria-hidden
            >
                <Icono className="size-4.5" />
            </span>
            <div className="min-w-0">
                <p className="text-xs text-muted-foreground">{label}</p>
                <div className="truncate text-sm font-medium">{children}</div>
            </div>
        </div>
    );
}

/** Ej: «falta 1 · 2 vencidos». */
function resumenPendientes(documentacion: EstadoDocumental): string {
    const partes: string[] = [];

    if (documentacion.faltantes.length > 0) {
        partes.push(
            `falta${documentacion.faltantes.length > 1 ? 'n' : ''} ${documentacion.faltantes.length}`,
        );
    }

    if (documentacion.vencidos.length > 0) {
        partes.push(
            `${documentacion.vencidos.length} vencido${documentacion.vencidos.length > 1 ? 's' : ''}`,
        );
    }

    if (documentacion.por_vencer.length > 0) {
        partes.push(`${documentacion.por_vencer.length} por vencer`);
    }

    return partes.join(' · ');
}

function formatearKg(valor: number | null): string {
    return valor === null ? '—' : `${valor.toLocaleString('es-PE')} kg`;
}
