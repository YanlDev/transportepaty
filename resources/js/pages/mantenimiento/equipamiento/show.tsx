import { Head, setLayoutProps } from '@inertiajs/react';
import { ClipboardList, Trash2 } from 'lucide-react';
import { useState } from 'react';
import equipamiento from '@/actions/App/Http/Controllers/EquipamientoCarretaController';
import mantenimiento from '@/actions/App/Http/Controllers/MantenimientoController';
import { ConfirmarBorradoDialog } from '@/components/confirmar-borrado-dialog';
import { ConteoDialog } from '@/components/equipamiento/conteo-dialog';
import { EstadoEquipamientoBadge } from '@/components/equipamiento/estado-equipamiento';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { usePermisos } from '@/hooks/use-permisos';
import { formatearFecha, formatearPlaca } from '@/lib/format';
import { cn } from '@/lib/utils';
import type {
    ConteoEquipamiento,
    EquipamientoCarreta,
} from '@/types/equipamiento';

type Props = {
    carreta: EquipamientoCarreta;
    conteos: ConteoEquipamiento[];
    hoy: string;
};

/**
 * Lo que lleva una carreta según su último conteo, frente al mínimo, y los
 * conteos anteriores. Borrar un conteo mal hecho la deja con el anterior.
 */
export default function EquipamientoCarretaShow({
    carreta,
    conteos,
    hoy,
}: Props) {
    const { puede } = usePermisos();
    const puedeContar = puede('mantenimiento.equipamiento');
    const [contando, setContando] = useState(false);
    const placa = formatearPlaca(carreta.placa);

    setLayoutProps({
        breadcrumbs: [
            { title: 'Mantenimiento', href: mantenimiento.index().url },
            { title: 'Equipamiento', href: equipamiento.index().url },
            { title: placa, href: equipamiento.show(carreta.id).url },
        ],
    });

    return (
        <div className="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-6 p-4 md:p-6">
            <Head title={`Equipamiento ${placa}`} />

            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex flex-col gap-1">
                    <div className="flex items-center gap-2">
                        <h1 className="text-xl font-semibold tracking-wide">
                            {placa}
                        </h1>
                        <EstadoEquipamientoBadge
                            estado={carreta.estado}
                            label={carreta.estado_label}
                        />
                    </div>
                    <p className="text-sm text-muted-foreground">
                        Carreta{carreta.marca && ` · ${carreta.marca}`} ·{' '}
                        {carreta.conteo_fecha
                            ? `contada el ${formatearFecha(carreta.conteo_fecha)}`
                            : 'nunca se contó'}
                    </p>
                </div>
                {puedeContar && (
                    <Button onClick={() => setContando(true)}>
                        <ClipboardList className="size-4" />
                        Contar equipamiento
                    </Button>
                )}
            </div>

            <div className="overflow-hidden rounded-xl border bg-card">
                <Table>
                    <TableHeader>
                        <TableRow className="hover:bg-transparent">
                            <TableHead>Ítem</TableHead>
                            <TableHead className="text-right">Mínimo</TableHead>
                            <TableHead className="text-right">Buenas</TableHead>
                            <TableHead className="text-right">
                                Dañadas
                            </TableHead>
                            <TableHead className="text-right">Faltan</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {carreta.items.map((item) => (
                            <TableRow key={item.id}>
                                <TableCell className="font-medium whitespace-normal">
                                    {item.nombre}
                                </TableCell>
                                <TableCell className="text-right text-muted-foreground tabular-nums">
                                    {item.minimo ?? '—'}
                                </TableCell>
                                <TableCell className="text-right tabular-nums">
                                    {item.buenas ?? '—'}
                                </TableCell>
                                <TableCell
                                    className={cn(
                                        'text-right tabular-nums',
                                        (item.danadas ?? 0) > 0 &&
                                            'text-amber-700 dark:text-amber-400',
                                    )}
                                >
                                    {item.danadas ?? '—'}
                                </TableCell>
                                <TableCell
                                    className={cn(
                                        'text-right font-medium tabular-nums',
                                        item.faltan > 0 &&
                                            'text-red-700 dark:text-red-400',
                                    )}
                                >
                                    {item.faltan > 0 ? item.faltan : '—'}
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </div>

            <section className="flex flex-col gap-3">
                <h2 className="font-medium">Conteos</h2>
                {conteos.length === 0 ? (
                    <p className="rounded-xl border border-dashed p-4 text-center text-sm text-muted-foreground">
                        Todavía no se contó esta carreta.
                    </p>
                ) : (
                    <ul className="flex flex-col divide-y rounded-xl border bg-card">
                        {conteos.map((conteo, indice) => (
                            <li
                                key={conteo.id}
                                className="flex items-start gap-2 px-4 py-3 text-sm"
                            >
                                <div className="flex min-w-0 flex-1 flex-col gap-0.5">
                                    <span className="font-medium">
                                        {formatearFecha(conteo.fecha)}
                                        {indice === 0 && (
                                            <span className="ml-2 text-xs font-normal text-muted-foreground">
                                                vigente
                                            </span>
                                        )}
                                    </span>
                                    <span className="text-xs text-muted-foreground">
                                        {conteo.detalles
                                            .map(
                                                (d) =>
                                                    `${d.item}: ${d.buenas}${d.danadas > 0 ? ` (+${d.danadas} dañadas)` : ''}`,
                                            )
                                            .join(' · ')}
                                    </span>
                                    {(conteo.observacion || conteo.usuario) && (
                                        <span className="text-xs">
                                            {[
                                                conteo.observacion,
                                                conteo.usuario,
                                            ]
                                                .filter(Boolean)
                                                .join(' · ')}
                                        </span>
                                    )}
                                </div>
                                {puedeContar && (
                                    <ConfirmarBorradoDialog
                                        url={
                                            equipamiento.destroy(conteo.id).url
                                        }
                                        titulo="¿Eliminar este conteo?"
                                        descripcion={
                                            indice === 0
                                                ? 'La carreta vuelve a quedar con lo de su conteo anterior.'
                                                : 'Se borra del historial; no cambia lo que lleva la carreta.'
                                        }
                                        trigger={
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                className="-my-1 size-8"
                                                aria-label="Eliminar el conteo"
                                            >
                                                <Trash2 className="size-3.5" />
                                            </Button>
                                        }
                                    />
                                )}
                            </li>
                        ))}
                    </ul>
                )}
            </section>

            {contando && (
                <ConteoDialog
                    carretaId={carreta.id}
                    placa={placa}
                    items={carreta.items}
                    hoy={hoy}
                    abierto
                    onAbiertoChange={setContando}
                />
            )}
        </div>
    );
}
