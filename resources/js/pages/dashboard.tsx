import { Head, Link } from '@inertiajs/react';
import { AlertTriangle, Container, Truck, Users } from 'lucide-react';
import { show } from '@/actions/App/Http/Controllers/VehiculoController';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { dashboard } from '@/routes';

type DocumentoPorVencer = {
    id: number;
    vehiculo_id: number;
    placa: string;
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
    documentosPorVencer: DocumentoPorVencer[];
};

export default function Dashboard({ resumen, documentosPorVencer }: Props) {
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
                <div className="flex items-center gap-2 border-b p-5">
                    <AlertTriangle className="size-4 text-amber-500" />
                    <h2 className="text-sm font-semibold">
                        Documentos vencidos o por vencer (30 días)
                    </h2>
                </div>

                {documentosPorVencer.length === 0 ? (
                    <p className="p-5 text-sm text-muted-foreground">
                        No hay documentos próximos a vencer.
                    </p>
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow className="hover:bg-transparent">
                                <TableHead>Placa</TableHead>
                                <TableHead>Documento</TableHead>
                                <TableHead>Vence</TableHead>
                                <TableHead>Estado</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {documentosPorVencer.map((documento) => (
                                <TableRow key={documento.id}>
                                    <TableCell className="font-medium">
                                        <Link
                                            href={show(documento.vehiculo_id)}
                                            className="hover:underline"
                                        >
                                            {documento.placa}
                                        </Link>
                                    </TableCell>
                                    <TableCell>{documento.tipo_label}</TableCell>
                                    <TableCell className="tabular-nums">
                                        {documento.fecha_vencimiento ?? '—'}
                                    </TableCell>
                                    <TableCell>
                                        <span
                                            className={
                                                documento.vencido
                                                    ? 'inline-flex items-center rounded-full bg-red-50 px-2 py-0.5 text-[11px] font-medium text-red-700 ring-1 ring-red-600/20'
                                                    : 'inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-medium text-amber-700 ring-1 ring-amber-600/20'
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
