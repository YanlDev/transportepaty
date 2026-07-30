import { Head, Link } from '@inertiajs/react';
import { Container, Link2, Truck, User } from 'lucide-react';
import asignaciones, {
    create,
    disponibles,
} from '@/actions/App/Http/Controllers/AsignacionController';
import { show as showConductor } from '@/actions/App/Http/Controllers/ConductorController';
import { show as showVehiculo } from '@/actions/App/Http/Controllers/VehiculoController';
import { Copiable } from '@/components/copiable';
import { ResumenProblemas } from '@/components/semaforo-documental';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { EstadoBadge } from '@/components/vehiculos/estado-badge';
import { formatearPlaca } from '@/lib/format';
import type { CarretaLibre, ConductorLibre, TractoLibre } from '@/types/fleet';

type Props = {
    tractos: TractoLibre[];
    carretas: CarretaLibre[];
    conductores: ConductorLibre[];
};

export default function AsignacionesDisponibles({
    tractos,
    carretas,
    conductores,
}: Props) {
    const total = tractos.length + carretas.length + conductores.length;

    return (
        <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
            <Head title="Sin asignar" />

            <div>
                <h1 className="text-2xl font-semibold tracking-tight">
                    Sin asignar
                </h1>
                <p className="text-sm text-muted-foreground">
                    {total === 0
                        ? 'Toda la flota está asignada.'
                        : 'Fierros y conductores parados, listos para armar unidad.'}
                </p>
            </div>

            <Seccion
                icono={<Truck className="size-4" />}
                titulo="Tractos sin conductor"
                cantidad={tractos.length}
                vacio="Todos los tractos tienen conductor."
            >
                <Table>
                    <TableHeader>
                        <TableRow className="hover:bg-transparent">
                            <TableHead>Placa</TableHead>
                            <TableHead>Marca</TableHead>
                            <TableHead>TUC</TableHead>
                            <TableHead>Caja</TableHead>
                            <TableHead>Documentación</TableHead>
                            <TableHead>Estado</TableHead>
                            <TableHead className="text-right">
                                Acciones
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {tractos.map((tracto) => (
                            <TableRow key={tracto.id} className="group/fila">
                                <TableCell className="font-mono font-medium">
                                    <Copiable
                                        valor={formatearPlaca(tracto.placa)}
                                        etiqueta="placa"
                                    >
                                        <Link
                                            href={showVehiculo(tracto.id)}
                                            className="hover:underline"
                                        >
                                            {formatearPlaca(tracto.placa)}
                                        </Link>
                                    </Copiable>
                                </TableCell>
                                <TableCell>{tracto.marca ?? '—'}</TableCell>
                                <TableCell className="text-muted-foreground tabular-nums">
                                    <Copiable
                                        valor={tracto.tuc_numero}
                                        etiqueta="TUC"
                                    />
                                </TableCell>
                                <TableCell className="text-muted-foreground">
                                    {tracto.caja_label ?? '—'}
                                </TableCell>
                                <TableCell>
                                    <ResumenProblemas
                                        estado={tracto.documentacion}
                                    />
                                </TableCell>
                                <TableCell>
                                    <EstadoBadge estado={tracto.estado} />
                                </TableCell>
                                <TableCell className="text-right">
                                    <BotonAsignar
                                        href={
                                            create({
                                                query: { tracto: tracto.id },
                                            }).url
                                        }
                                    />
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </Seccion>

            <Seccion
                icono={<Container className="size-4" />}
                titulo="Carretas sin enganchar"
                cantidad={carretas.length}
                vacio="Todas las carretas están enganchadas."
            >
                <Table>
                    <TableHeader>
                        <TableRow className="hover:bg-transparent">
                            <TableHead>Placa</TableHead>
                            <TableHead>Marca</TableHead>
                            <TableHead>Documentación</TableHead>
                            <TableHead>Estado</TableHead>
                            <TableHead className="text-right">
                                Acciones
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {carretas.map((carreta) => (
                            <TableRow key={carreta.id} className="group/fila">
                                <TableCell className="font-mono font-medium">
                                    <Copiable
                                        valor={formatearPlaca(carreta.placa)}
                                        etiqueta="placa"
                                    >
                                        <Link
                                            href={showVehiculo(carreta.id)}
                                            className="hover:underline"
                                        >
                                            {formatearPlaca(carreta.placa)}
                                        </Link>
                                    </Copiable>
                                </TableCell>
                                <TableCell>{carreta.marca ?? '—'}</TableCell>
                                <TableCell>
                                    <ResumenProblemas
                                        estado={carreta.documentacion}
                                    />
                                </TableCell>
                                <TableCell>
                                    <EstadoBadge estado={carreta.estado} />
                                </TableCell>
                                <TableCell className="text-right">
                                    <BotonAsignar
                                        href={
                                            create({
                                                query: { carreta: carreta.id },
                                            }).url
                                        }
                                    />
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </Seccion>

            <Seccion
                icono={<User className="size-4" />}
                titulo="Conductores sin unidad"
                cantidad={conductores.length}
                vacio="Todos los conductores activos tienen unidad."
            >
                <Table>
                    <TableHeader>
                        <TableRow className="hover:bg-transparent">
                            <TableHead>Conductor</TableHead>
                            <TableHead>Celular</TableHead>
                            <TableHead>Licencia</TableHead>
                            <TableHead>Categoría</TableHead>
                            <TableHead>Documentación</TableHead>
                            <TableHead className="text-right">
                                Acciones
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {conductores.map((conductor) => (
                            <TableRow key={conductor.id} className="group/fila">
                                <TableCell className="font-medium">
                                    <Link
                                        href={showConductor(conductor.id)}
                                        className="hover:underline"
                                    >
                                        {conductor.nombre_completo}
                                    </Link>
                                </TableCell>
                                <TableCell className="tabular-nums">
                                    <Copiable
                                        valor={conductor.telefono}
                                        etiqueta="celular"
                                    />
                                </TableCell>
                                <TableCell className="font-mono text-xs">
                                    <Copiable
                                        valor={conductor.licencia}
                                        etiqueta="licencia"
                                    />
                                </TableCell>
                                <TableCell className="text-muted-foreground">
                                    {conductor.categoria_licencia ?? '—'}
                                </TableCell>
                                <TableCell>
                                    <ResumenProblemas
                                        estado={conductor.documentacion}
                                    />
                                </TableCell>
                                <TableCell className="text-right">
                                    <BotonAsignar
                                        href={
                                            create({
                                                query: {
                                                    conductor: conductor.id,
                                                },
                                            }).url
                                        }
                                    />
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </Seccion>
        </div>
    );
}

/**
 * Un grupo con su propio encabezado y conteo. Cuando está vacío se dice con
 * palabras en vez de mostrar una tabla sin filas.
 */
function Seccion({
    icono,
    titulo,
    cantidad,
    vacio,
    children,
}: {
    icono: React.ReactNode;
    titulo: string;
    cantidad: number;
    vacio: string;
    children: React.ReactNode;
}) {
    return (
        <section className="overflow-hidden rounded-xl border border-border">
            <div className="flex items-center gap-2 border-b bg-muted/30 px-4 py-3">
                <span className="text-muted-foreground">{icono}</span>
                <h2 className="text-sm font-semibold">{titulo}</h2>
                <span className="text-sm text-muted-foreground tabular-nums">
                    ({cantidad})
                </span>
            </div>

            {cantidad === 0 ? (
                <p className="px-4 py-6 text-sm text-muted-foreground">
                    {vacio}
                </p>
            ) : (
                <div className="overflow-x-auto">{children}</div>
            )}
        </section>
    );
}

function BotonAsignar({ href }: { href: string }) {
    return (
        <Button asChild size="sm" variant="outline">
            <Link href={href}>
                <Link2 className="size-3.5" />
                Asignar
            </Link>
        </Button>
    );
}

AsignacionesDisponibles.layout = {
    breadcrumbs: [
        { title: 'Asignaciones', href: asignaciones.index().url },
        { title: 'Sin asignar', href: disponibles().url },
    ],
};
