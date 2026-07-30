import { Head, Link, router } from '@inertiajs/react';
import { AlertTriangle, Container, Truck, Users } from 'lucide-react';
import { show as showConductor } from '@/actions/App/Http/Controllers/ConductorController';
import { show } from '@/actions/App/Http/Controllers/VehiculoController';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { dashboard } from '@/routes';

/** Horizontes del filtro de vencimientos; deben calzar con el backend. */
const horizontes = [
    { value: '15', label: 'Próximos 15 días' },
    { value: '30', label: 'Próximos 30 días' },
];

type DocumentoPorVencer = {
    clave: string;
    /** Placa del vehículo o nombre del conductor, según a quién pertenezca. */
    titular: string;
    vehiculo_id: number | null;
    conductor_id: number | null;
    tipo_label: string;
    fecha_vencimiento: string | null;
    vencido: boolean;
};

type Props = {
    resumen: {
        tractos: number;
        carretas: number;
        operativos: number;
        conductores: number;
    };
    filtros: { dias: number };
    documentosPorVencer: DocumentoPorVencer[];
};

export default function Dashboard({
    resumen,
    filtros,
    documentosPorVencer,
}: Props) {
    const cambiarHorizonte = (dias: string) => {
        router.get(
            dashboard().url,
            { dias },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    return (
        <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
            <Head title="Dashboard" />

            <div>
                <h1 className="text-2xl font-semibold tracking-tight">
                    Resumen de flota
                </h1>
                <p className="text-sm text-muted-foreground">
                    Estado general de las unidades y su documentación.
                </p>
            </div>

            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <Tarjeta
                    label="Tractos"
                    valor={resumen.tractos}
                    icon={<Truck className="size-5" />}
                />
                <Tarjeta
                    label="Carretas"
                    valor={resumen.carretas}
                    icon={<Container className="size-5" />}
                />
                <Tarjeta
                    label="Operativos"
                    valor={resumen.operativos}
                    icon={<Truck className="size-5" />}
                />
                <Tarjeta
                    label="Conductores activos"
                    valor={resumen.conductores}
                    icon={<Users className="size-5" />}
                />
            </div>

            <section className="rounded-xl border border-border bg-card">
                <div className="flex flex-wrap items-center gap-2 border-b p-5">
                    <AlertTriangle className="size-4 text-amber-500" />
                    <h2 className="text-sm font-semibold">
                        Documentos vencidos o por vencer
                    </h2>

                    <div className="ml-auto">
                        <Select
                            value={String(filtros.dias)}
                            onValueChange={cambiarHorizonte}
                        >
                            <SelectTrigger
                                size="sm"
                                aria-label="Horizonte de vencimientos"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {horizontes.map((horizonte) => (
                                    <SelectItem
                                        key={horizonte.value}
                                        value={horizonte.value}
                                    >
                                        {horizonte.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                {documentosPorVencer.length === 0 ? (
                    <p className="p-5 text-sm text-muted-foreground">
                        No hay documentos vencidos ni que venzan en los próximos{' '}
                        {filtros.dias} días.
                    </p>
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow className="hover:bg-transparent">
                                <TableHead>Unidad / Conductor</TableHead>
                                <TableHead>Documento</TableHead>
                                <TableHead>Vence</TableHead>
                                <TableHead>Estado</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {documentosPorVencer.map((documento) => (
                                <TableRow key={documento.clave}>
                                    <TableCell className="font-medium">
                                        <Link
                                            href={
                                                documento.vehiculo_id !== null
                                                    ? show(
                                                          documento.vehiculo_id,
                                                      )
                                                    : showConductor(
                                                          documento.conductor_id ??
                                                              0,
                                                      )
                                            }
                                            className="hover:underline"
                                        >
                                            {documento.titular}
                                        </Link>
                                    </TableCell>
                                    <TableCell>
                                        {documento.tipo_label}
                                    </TableCell>
                                    <TableCell className="tabular-nums">
                                        {documento.fecha_vencimiento ?? '—'}
                                    </TableCell>
                                    <TableCell>
                                        <span
                                            className={
                                                documento.vencido
                                                    ? 'inline-flex items-center rounded-none bg-red-50 px-2 py-0.5 text-[11px] font-medium text-red-700 ring-1 ring-red-600/20'
                                                    : 'inline-flex items-center rounded-none bg-amber-50 px-2 py-0.5 text-[11px] font-medium text-amber-700 ring-1 ring-amber-600/20'
                                            }
                                        >
                                            {documento.vencido
                                                ? 'Vencido'
                                                : 'Por vencer'}
                                        </span>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
            </section>
        </div>
    );
}

function Tarjeta({
    label,
    valor,
    icon,
}: {
    label: string;
    valor: number;
    icon: React.ReactNode;
}) {
    return (
        <div className="rounded-xl border border-border bg-card p-5">
            <div className="flex items-center justify-between">
                <p className="text-sm text-muted-foreground">{label}</p>
                <span className="text-muted-foreground">{icon}</span>
            </div>
            <p className="mt-2 text-3xl font-semibold tabular-nums">{valor}</p>
        </div>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: dashboard().url }],
};
