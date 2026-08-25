import { Head, Link, setLayoutProps, usePage } from '@inertiajs/react';
import { ChevronDown, Pencil, Trash2 } from 'lucide-react';
import vehiculos, {
    edit,
    show,
} from '@/actions/App/Http/Controllers/VehiculoController';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { AgregarDocumentoDialog } from '@/components/vehiculos/agregar-documento-dialog';
import { DeleteVehiculoDialog } from '@/components/vehiculos/delete-vehiculo-dialog';
import { DocumentoRanura } from '@/components/vehiculos/documento-ranura';
import { EstadoBadge } from '@/components/vehiculos/estado-badge';
import { Dato } from '@/components/vehiculos/info-card';
import { formatearFecha, formatearPlaca } from '@/lib/format';
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
        <div className="mx-auto flex h-full w-full max-w-4xl flex-1 flex-col gap-4 p-4 md:p-6">
            <Head title={formatearPlaca(vehiculo.placa)} />

            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex items-baseline gap-3">
                    <h1 className="text-xl font-semibold tracking-tight">
                        {formatearPlaca(vehiculo.placa)}
                    </h1>
                    <span className="text-sm text-muted-foreground">
                        {tipoLabels[vehiculo.tipo] ?? vehiculo.tipo}
                        {vehiculo.marca && ` · ${vehiculo.marca}`}
                    </span>
                    <EstadoBadge estado={vehiculo.estado} />
                </div>

                {puedeGestionar && (
                    <div className="flex items-center gap-1">
                        <Button asChild variant="ghost" size="sm">
                            <Link href={edit(vehiculo.id)}>
                                <Pencil className="size-4" />
                                Editar
                            </Link>
                        </Button>
                        <DeleteVehiculoDialog
                            vehiculo={vehiculo}
                            trigger={
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    className="text-muted-foreground hover:text-destructive"
                                >
                                    <Trash2 className="size-4" />
                                    Eliminar
                                </Button>
                            }
                        />
                    </div>
                )}
            </div>

            <section>
                <div className="mb-2 flex flex-wrap items-center justify-between gap-2">
                    <h2 className="text-sm font-semibold text-foreground">
                        Documentos
                        <span
                            className={`ml-2 font-normal ${
                                pendientes === 0
                                    ? 'text-muted-foreground'
                                    : 'text-red-700 dark:text-red-400'
                            }`}
                        >
                            {pendientes === 0
                                ? 'todo al día'
                                : resumenPendientes(documentacion)}
                        </span>
                    </h2>

                    {puedeGestionar && (
                        <AgregarDocumentoDialog
                            vehiculoId={vehiculo.id}
                            tipos={tiposDocumento}
                        />
                    )}
                </div>

                <div className="grid gap-2">
                    {obligatorias.map((ranura) => (
                        <DocumentoRanura
                            key={ranura.tipo}
                            ranura={ranura}
                            vehiculoId={vehiculo.id}
                            tipos={tiposDocumento}
                            puedeGestionar={puedeGestionar}
                        />
                    ))}
                </div>

                {sueltas.length > 0 && (
                    <>
                        <p className="mt-4 mb-2 text-xs font-medium text-muted-foreground">
                            Otros documentos
                        </p>
                        <div className="grid gap-2">
                            {sueltas.map((ranura) => (
                                <DocumentoRanura
                                    key={ranura.documento?.id ?? ranura.tipo}
                                    ranura={ranura}
                                    vehiculoId={vehiculo.id}
                                    tipos={tiposDocumento}
                                    puedeGestionar={puedeGestionar}
                                />
                            ))}
                        </div>
                    </>
                )}
            </section>

            <FichaTecnica vehiculo={vehiculo} esTracto={esTracto} />
        </div>
    );
}

/**
 * Los datos del fierro van plegados: se consultan de vez en cuando, mientras que
 * la documentación es el motivo de entrar a esta página.
 */
function FichaTecnica({
    vehiculo,
    esTracto,
}: {
    vehiculo: Vehiculo;
    esTracto: boolean;
}) {
    return (
        <Collapsible className="mt-2 border-t pt-3">
            <CollapsibleTrigger className="group flex w-full items-center gap-1.5 text-xs font-medium text-muted-foreground transition-colors hover:text-foreground">
                <ChevronDown className="size-3.5 transition-transform group-data-[state=open]:rotate-180" />
                Ficha técnica y pesos
            </CollapsibleTrigger>

            <CollapsibleContent>
                <dl className="grid grid-cols-2 gap-x-6 gap-y-3 pt-4 sm:grid-cols-4">
                    <Dato label="Modelo" value={vehiculo.modelo ?? '—'} />
                    <Dato
                        label="Año"
                        value={vehiculo.anio ? String(vehiculo.anio) : '—'}
                    />
                    <Dato label="Color" value={vehiculo.color ?? '—'} />
                    {esTracto && (
                        <Dato
                            label="Caja"
                            value={
                                vehiculo.caja
                                    ? (cajaLabels[vehiculo.caja] ??
                                      vehiculo.caja)
                                    : '—'
                            }
                        />
                    )}
                    <Dato
                        label="Ejes"
                        value={vehiculo.ejes ? String(vehiculo.ejes) : '—'}
                    />
                    <Dato label="VIN" value={vehiculo.vin ?? '—'} />
                    {esTracto && (
                        <Dato
                            label="N.° de motor"
                            value={vehiculo.numero_motor ?? '—'}
                        />
                    )}
                    <Dato
                        label="Adquisición"
                        value={
                            vehiculo.fecha_adquisicion
                                ? formatearFecha(vehiculo.fecha_adquisicion)
                                : '—'
                        }
                    />
                    <Dato
                        label="Peso neto"
                        value={formatearKg(vehiculo.peso_neto)}
                    />
                    <Dato
                        label="Peso bruto"
                        value={formatearKg(vehiculo.peso_bruto)}
                    />
                    <Dato
                        label="Carga útil"
                        value={formatearKg(vehiculo.carga_util)}
                    />
                </dl>

                {vehiculo.observaciones && (
                    <div className="mt-4">
                        <p className="text-xs text-muted-foreground">
                            Observaciones
                        </p>
                        <p className="mt-1 text-sm whitespace-pre-line">
                            {vehiculo.observaciones}
                        </p>
                    </div>
                )}
            </CollapsibleContent>
        </Collapsible>
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
