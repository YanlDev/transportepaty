import { Head, router } from '@inertiajs/react';
import {
    AlertTriangle,
    Check,
    CircleCheck,
    FileWarning,
    Trash2,
} from 'lucide-react';
import importaciones from '@/actions/App/Http/Controllers/ImportacionDisponibilidadController';
import { DeleteImportacionDialog } from '@/components/importaciones/delete-importacion-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { formatearFecha, formatearPlaca } from '@/lib/format';
import type { ImportacionDetalle, ImportacionFilaItem } from '@/types/fleet';

type Props = {
    importacion: ImportacionDetalle;
    filas: ImportacionFilaItem[];
};

export default function ImportacionesShow({ importacion, filas }: Props) {
    const incluidas = filas.filter((fila) => fila.incluir).length;
    const conProblemas = filas.filter(
        (fila) => fila.problemas.length > 0,
    ).length;

    const alternarIncluir = (fila: ImportacionFilaItem) => {
        router.patch(
            importaciones.actualizarFila([importacion.id, fila.id]).url,
            { incluir: !fila.incluir },
            { preserveScroll: true },
        );
    };

    const confirmar = () => {
        if (
            !confirm(
                `¿Aplicar ${incluidas} unidades a la disponibilidad del ${importacion.fecha}? Lo que ya esté confirmado a mano no se va a pisar.`,
            )
        ) {
            return;
        }

        router.post(importaciones.confirmar(importacion.id).url);
    };

    return (
        <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
            <Head title={`Importación ${importacion.fecha}`} />

            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Previsualización — {formatearFecha(importacion.fecha)}
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {importacion.archivo_original} · {filas.length} filas
                        leídas · {incluidas} listas para aplicar
                        {conProblemas > 0 &&
                            ` · ${conProblemas} con algo por revisar`}
                    </p>
                </div>

                {importacion.confirmada ? (
                    <Badge variant="secondary">
                        <CircleCheck className="size-3.5" />
                        Ya confirmada
                    </Badge>
                ) : (
                    <div className="flex items-center gap-2">
                        <DeleteImportacionDialog
                            importacion={importacion}
                            trigger={
                                <Button variant="outline">
                                    <Trash2 className="size-4" />
                                    Descartar
                                </Button>
                            }
                        />
                        <Button onClick={confirmar} disabled={incluidas === 0}>
                            <Check className="size-4" />
                            Aplicar {incluidas} unidades
                        </Button>
                    </div>
                )}
            </div>

            <div className="overflow-x-auto rounded-xl border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            {!importacion.confirmada && (
                                <TableHead className="w-10" />
                            )}
                            <TableHead>Fila</TableHead>
                            <TableHead>Unidad</TableHead>
                            <TableHead>Conductor</TableHead>
                            <TableHead>Carga</TableHead>
                            <TableHead>Ruta</TableHead>
                            <TableHead>Ubicación</TableHead>
                            <TableHead>Revisar</TableHead>
                        </TableRow>
                    </TableHeader>

                    <TableBody>
                        {filas.map((fila) => (
                            <TableRow
                                key={fila.id}
                                className={
                                    !fila.incluir ? 'opacity-50' : undefined
                                }
                            >
                                {!importacion.confirmada && (
                                    <TableCell>
                                        <Checkbox
                                            checked={fila.incluir}
                                            disabled={!fila.puede_aplicarse}
                                            onCheckedChange={() =>
                                                alternarIncluir(fila)
                                            }
                                            aria-label={`Incluir fila ${fila.numero_fila}`}
                                        />
                                    </TableCell>
                                )}

                                <TableCell className="text-muted-foreground">
                                    {fila.numero_fila}
                                </TableCell>

                                <TableCell className="whitespace-nowrap">
                                    {fila.tracto ? (
                                        <span className="font-medium">
                                            {formatearPlaca(fila.tracto)}
                                            {fila.carreta && (
                                                <span className="text-muted-foreground">
                                                    {' / '}
                                                    {formatearPlaca(
                                                        fila.carreta,
                                                    )}
                                                </span>
                                            )}
                                        </span>
                                    ) : (
                                        <span className="text-sm text-destructive">
                                            {fila.crudo.CODIGO ?? '—'}
                                        </span>
                                    )}
                                </TableCell>

                                <TableCell>
                                    {fila.conductor ?? (
                                        <span className="text-muted-foreground">
                                            —
                                        </span>
                                    )}
                                </TableCell>

                                <TableCell>
                                    {fila.tipo_carga_label ?? (
                                        <span className="text-muted-foreground">
                                            —
                                        </span>
                                    )}
                                </TableCell>

                                <TableCell className="whitespace-nowrap">
                                    {fila.origen || fila.destino ? (
                                        <span className="text-sm">
                                            {fila.origen ?? '—'} ⇒{' '}
                                            {fila.destino ?? '—'}
                                        </span>
                                    ) : (
                                        <span className="text-muted-foreground">
                                            —
                                        </span>
                                    )}
                                </TableCell>

                                <TableCell>
                                    {fila.ubicacion ?? (
                                        <span className="text-muted-foreground">
                                            —
                                        </span>
                                    )}
                                </TableCell>

                                <TableCell>
                                    {fila.problemas.length > 0 && (
                                        <Tooltip>
                                            <TooltipTrigger asChild>
                                                <Badge
                                                    variant={
                                                        fila.tracto
                                                            ? 'secondary'
                                                            : 'destructive'
                                                    }
                                                    className="cursor-help"
                                                >
                                                    {fila.tracto ? (
                                                        <AlertTriangle />
                                                    ) : (
                                                        <FileWarning />
                                                    )}
                                                    {fila.problemas.length}
                                                </Badge>
                                            </TooltipTrigger>
                                            <TooltipContent className="max-w-80">
                                                <ul className="list-disc space-y-1 pl-4">
                                                    {fila.problemas.map(
                                                        (problema) => (
                                                            <li key={problema}>
                                                                {problema}
                                                            </li>
                                                        ),
                                                    )}
                                                </ul>
                                            </TooltipContent>
                                        </Tooltip>
                                    )}
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </div>
        </div>
    );
}

ImportacionesShow.layout = {
    breadcrumbs: [{ title: 'Importaciones', href: importaciones.index().url }],
};
