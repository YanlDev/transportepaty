import { Head, router } from '@inertiajs/react';
import { Pencil, Trash2, Wrench } from 'lucide-react';
import { useEffect, useState } from 'react';
import { index as plantillasRoute } from '@/actions/App/Http/Controllers/PlantillaMantenimientoController';
import { EliminarPlantillaDialog } from '@/components/mantenimiento/eliminar-plantilla-dialog';
import { PlantillaFormDialog } from '@/components/mantenimiento/plantilla-form-dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatearSoles } from '@/lib/format';
import type {
    EnumOption,
    Paginator,
    PlantillaMantenimiento,
} from '@/types/fleet';

type Props = {
    plantillas: Paginator<PlantillaMantenimiento>;
    filtros: { buscar: string };
    tiposVehiculo: EnumOption[];
};

export default function PlantillasMantenimiento({
    plantillas: paginador,
    filtros,
    tiposVehiculo,
}: Props) {
    const [buscar, setBuscar] = useState(filtros.buscar ?? '');
    const [editando, setEditando] = useState<PlantillaMantenimiento | null>(
        null,
    );
    const [eliminando, setEliminando] = useState<PlantillaMantenimiento | null>(
        null,
    );

    useEffect(() => {
        if (buscar === (filtros.buscar ?? '')) {
            return;
        }

        const timeout = setTimeout(() => {
            router.get(
                plantillasRoute().url,
                { buscar: buscar || undefined },
                { preserveState: true, preserveScroll: true, replace: true },
            );
        }, 300);

        return () => clearTimeout(timeout);
    }, [buscar, filtros.buscar]);

    return (
        <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
            <Head title="Plantillas de mantenimiento" />

            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Plantillas de mantenimiento
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Define los servicios y sus intervalos. Sin marca/modelo
                        aplican de forma genérica.
                    </p>
                </div>

                <PlantillaFormDialog tiposVehiculo={tiposVehiculo} />
            </div>

            <Input
                value={buscar}
                onChange={(e) => setBuscar(e.target.value)}
                placeholder="Buscar por nombre, marca o modelo..."
                className="max-w-sm"
            />

            {paginador.data.length === 0 ? (
                <div className="flex flex-1 flex-col items-center justify-center rounded-xl border border-dashed py-20 text-center">
                    <div className="mb-4 grid size-14 place-items-center rounded-full bg-muted text-muted-foreground">
                        <Wrench className="size-7" />
                    </div>
                    <p className="font-medium">No hay plantillas</p>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Crea la primera para empezar a planificar
                        mantenimientos.
                    </p>
                </div>
            ) : (
                <div className="overflow-hidden rounded-xl border border-border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left text-xs text-muted-foreground">
                            <tr>
                                <th className="px-4 py-2.5 font-medium">
                                    Servicio
                                </th>
                                <th className="px-4 py-2.5 font-medium">
                                    Aplica a
                                </th>
                                <th className="px-4 py-2.5 font-medium">
                                    Intervalo
                                </th>
                                <th className="px-4 py-2.5 font-medium">
                                    Costo est.
                                </th>
                                <th className="px-4 py-2.5" />
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-border">
                            {paginador.data.map((p) => (
                                <tr key={p.id} className="hover:bg-muted/30">
                                    <td className="px-4 py-3">
                                        <div className="flex items-center gap-2">
                                            <span className="font-medium text-foreground">
                                                {p.nombre}
                                            </span>
                                            {!p.activo && (
                                                <span className="rounded-full bg-zinc-100 px-1.5 py-0.5 text-[10px] font-medium text-zinc-600">
                                                    Inactiva
                                                </span>
                                            )}
                                        </div>
                                        <span className="text-xs text-muted-foreground">
                                            {p.tipo_mantenimiento}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 text-muted-foreground">
                                        {ambito(p, tiposVehiculo)}
                                    </td>
                                    <td className="px-4 py-3 text-muted-foreground">
                                        {intervalo(p)}
                                    </td>
                                    <td className="px-4 py-3 text-muted-foreground">
                                        {p.costo_estimado != null
                                            ? formatearSoles(p.costo_estimado)
                                            : '—'}
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex items-center justify-end gap-1">
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                className="size-8"
                                                title="Editar"
                                                onClick={() => setEditando(p)}
                                            >
                                                <Pencil className="size-4" />
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                className="size-8 text-destructive hover:text-destructive"
                                                title="Eliminar"
                                                onClick={() => setEliminando(p)}
                                            >
                                                <Trash2 className="size-4" />
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            {editando && (
                <PlantillaFormDialog
                    tiposVehiculo={tiposVehiculo}
                    plantilla={editando}
                    onClose={() => setEditando(null)}
                />
            )}

            {eliminando && (
                <EliminarPlantillaDialog
                    plantilla={eliminando}
                    onClose={() => setEliminando(null)}
                />
            )}
        </div>
    );
}

function ambito(p: PlantillaMantenimiento, tipos: EnumOption[]): string {
    if (p.marca && p.modelo) {
        return `${p.marca} ${p.modelo}`;
    }

    if (p.marca) {
        return p.marca;
    }

    if (p.tipo_vehiculo) {
        return (
            tipos.find((t) => t.value === p.tipo_vehiculo)?.label ??
            p.tipo_vehiculo
        );
    }

    return 'Genérica';
}

function intervalo(p: PlantillaMantenimiento): string {
    if (p.una_vez) {
        return p.intervalo_km != null
            ? `Único · ${p.intervalo_km.toLocaleString('es-PE')} km`
            : 'Único';
    }

    const partes: string[] = [];

    if (p.intervalo_km != null) {
        partes.push(`${p.intervalo_km.toLocaleString('es-PE')} km`);
    }

    if (p.intervalo_meses != null) {
        partes.push(`${p.intervalo_meses} m`);
    }

    return partes.join(' / ') || '—';
}

PlantillasMantenimiento.layout = {
    breadcrumbs: [
        {
            title: 'Plantillas de mantenimiento',
            href: plantillasRoute().url,
        },
    ],
};
