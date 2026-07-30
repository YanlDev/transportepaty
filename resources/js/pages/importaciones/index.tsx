import { Head, Link } from '@inertiajs/react';
import { CircleCheck, FileSpreadsheet, Plus, Trash2 } from 'lucide-react';
import importaciones, {
    create,
    show,
} from '@/actions/App/Http/Controllers/ImportacionDisponibilidadController';
import { DeleteImportacionDialog } from '@/components/importaciones/delete-importacion-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatearFecha, formatearFechaHora } from '@/lib/format';
import type { ImportacionListItem, Paginator } from '@/types/fleet';

type Props = {
    importaciones: Paginator<ImportacionListItem>;
};

export default function ImportacionesIndex({
    importaciones: paginador,
}: Props) {
    return (
        <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
            <Head title="Importaciones" />

            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Importaciones
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {paginador.total}{' '}
                        {paginador.total === 1
                            ? 'archivo subido'
                            : 'archivos subidos'}
                    </p>
                </div>

                <Button asChild>
                    <Link href={create()}>
                        <Plus className="size-4" />
                        Importar Excel
                    </Link>
                </Button>
            </div>

            {paginador.data.length === 0 ? (
                <div className="flex flex-1 flex-col items-center justify-center rounded-xl border border-dashed py-20 text-center">
                    <div className="mb-4 grid size-14 place-items-center bg-muted text-muted-foreground">
                        <FileSpreadsheet className="size-7" />
                    </div>
                    <p className="font-medium">
                        Todavía no se ha importado ningún Excel
                    </p>
                    <Button asChild variant="outline" className="mt-6">
                        <Link href={create()}>
                            <Plus className="size-4" />
                            Importar el primero
                        </Link>
                    </Button>
                </div>
            ) : (
                <div className="overflow-x-auto rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Fecha del reporte</TableHead>
                                <TableHead>Archivo</TableHead>
                                <TableHead>Subido por</TableHead>
                                <TableHead>Filas</TableHead>
                                <TableHead>Estado</TableHead>
                                <TableHead>Subido el</TableHead>
                                <TableHead className="w-10" />
                            </TableRow>
                        </TableHeader>

                        <TableBody>
                            {paginador.data.map((item) => (
                                <TableRow key={item.id}>
                                    <TableCell className="font-medium whitespace-nowrap">
                                        <Link
                                            href={show(item.id)}
                                            className="underline-offset-4 hover:underline"
                                        >
                                            {formatearFecha(item.fecha)}
                                        </Link>
                                    </TableCell>
                                    <TableCell className="max-w-64 truncate">
                                        {item.archivo_original}
                                    </TableCell>
                                    <TableCell>{item.usuario}</TableCell>
                                    <TableCell>
                                        {item.filas_resueltas} /{' '}
                                        {item.filas_totales}
                                    </TableCell>
                                    <TableCell>
                                        {item.confirmada ? (
                                            <Badge variant="secondary">
                                                <CircleCheck className="size-3.5" />
                                                Confirmada
                                            </Badge>
                                        ) : (
                                            <Badge variant="outline">
                                                Pendiente
                                            </Badge>
                                        )}
                                    </TableCell>
                                    <TableCell className="text-sm whitespace-nowrap text-muted-foreground">
                                        {formatearFechaHora(item.creada)}
                                    </TableCell>
                                    <TableCell>
                                        {!item.confirmada && (
                                            <DeleteImportacionDialog
                                                importacion={item}
                                                trigger={
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        aria-label="Descartar importación"
                                                    >
                                                        <Trash2 className="size-4 text-muted-foreground" />
                                                    </Button>
                                                }
                                            />
                                        )}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
            )}
        </div>
    );
}

ImportacionesIndex.layout = {
    breadcrumbs: [{ title: 'Importaciones', href: importaciones.index().url }],
};
