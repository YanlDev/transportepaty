import { Head, router } from '@inertiajs/react';
import { Plus, Truck } from '@phosphor-icons/react';
import { useMemo, useState } from 'react';
import programacion from '@/actions/App/Http/Controllers/ProgramacionController';
import { EmptyState } from '@/components/empty-state';
import { ProgramacionDialog } from '@/components/programacion/programacion-dialog';
import { TarjetaProgramacion } from '@/components/programacion/tarjeta-programacion';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { usePermisos } from '@/hooks/use-permisos';
import { avisarError } from '@/lib/aviso-error';
import { cn } from '@/lib/utils';
import type {
    ClienteOpcion,
    ConductorOpcion,
    DiaDeSemana,
    ProgramacionTarjeta,
    UltimoViaje,
    UnidadOpcion,
} from '@/types/programacion';

type Props = {
    fecha: string;
    programaciones: ProgramacionTarjeta[];
    semana: DiaDeSemana[];
    unidades: UnidadOpcion[];
    conductores: ConductorOpcion[];
    clientes: ClienteOpcion[];
    destinosUsados: string[];
    ultimoViajePorConductor: Record<number, UltimoViaje>;
};

const DIAS_CORTOS = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'] as const;

/**
 * Qué unidades salen con carga particular cada día.
 *
 * Es una grilla de tarjetas y no una tabla porque quien la lee —
 * abastecimiento— no compara columnas: busca una unidad y necesita ver de
 * golpe su cliente, su destino y su conductor. El color de la franja sale
 * del cliente, así que las unidades de un mismo cliente se agrupan solas a
 * la vista aunque estén en filas distintas.
 */
export default function ProgramacionIndex({
    fecha,
    programaciones,
    semana,
    unidades,
    conductores,
    clientes,
    destinosUsados,
    ultimoViajePorConductor,
}: Props) {
    const { puedeEditar } = usePermisos();

    const [dialogoAbierto, setDialogoAbierto] = useState(false);
    const [enEdicion, setEnEdicion] = useState<ProgramacionTarjeta | null>(
        null,
    );

    const irA = (nuevaFecha: string) => {
        router.get(
            programacion.index.url({ query: { fecha: nuevaFecha } }),
            {},
            { preserveScroll: true, preserveState: true },
        );
    };

    const abrirNueva = () => {
        setEnEdicion(null);
        setDialogoAbierto(true);
    };

    const abrirEdicion = (tarjeta: ProgramacionTarjeta) => {
        setEnEdicion(tarjeta);
        setDialogoAbierto(true);
    };

    const borrar = (tarjeta: ProgramacionTarjeta) => {
        if (
            !confirm(
                `¿Quitar la programación de ${tarjeta.placa} para ${tarjeta.cliente}?`,
            )
        ) {
            return;
        }

        router.delete(programacion.destroy(tarjeta.id).url, {
            preserveScroll: true,
            onError: avisarError,
        });
    };

    // Cuántas unidades distintas y cuántos clientes hay en el día: el resumen
    // que abastecimiento mira antes de entrar al detalle.
    const resumen = useMemo(() => {
        const clientesDelDia = new Set(
            programaciones.map((tarjeta) => tarjeta.cliente),
        );

        return {
            unidades: programaciones.length,
            clientes: clientesDelDia.size,
        };
    }, [programaciones]);

    return (
        <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
            <Head title="Programación" />

            <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div className="flex flex-col gap-1">
                    <h1 className="text-xl font-semibold">
                        Programación de carga particular
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {resumen.unidades === 0
                            ? 'Sin unidades programadas para este día.'
                            : `${resumen.unidades} ${resumen.unidades === 1 ? 'unidad' : 'unidades'} para ${resumen.clientes} ${resumen.clientes === 1 ? 'cliente' : 'clientes'}.`}
                    </p>
                </div>

                <div className="flex items-center gap-2">
                    <Input
                        type="date"
                        className="h-9 w-auto"
                        aria-label="Día"
                        value={fecha}
                        onChange={(evento) =>
                            evento.target.value && irA(evento.target.value)
                        }
                    />

                    {puedeEditar && (
                        <Button onClick={abrirNueva}>
                            <Plus className="size-4" />
                            Programar unidad
                        </Button>
                    )}
                </div>
            </div>

            {/* La semana del día que se está viendo, con cuántas unidades
                tiene cada uno: se ve de un vistazo qué días ya tienen plan. */}
            <div className="grid grid-cols-7 gap-1.5">
                {semana.map((dia, indice) => {
                    const esElVisto = dia.fecha === fecha;

                    return (
                        <button
                            key={dia.fecha}
                            type="button"
                            onClick={() => irA(dia.fecha)}
                            aria-current={esElVisto ? 'date' : undefined}
                            className={cn(
                                'flex flex-col items-center gap-0.5 rounded-lg border px-1 py-2 text-xs transition',
                                esElVisto
                                    ? 'border-primary bg-primary/10 font-semibold'
                                    : 'hover:bg-accent',
                            )}
                        >
                            <span className="text-muted-foreground">
                                {DIAS_CORTOS[indice]}
                            </span>
                            <span className="text-sm">
                                {Number(dia.fecha.slice(8, 10))}
                            </span>
                            <span
                                className={cn(
                                    'rounded-full px-1.5 text-[10px] leading-4',
                                    dia.programadas > 0
                                        ? 'bg-primary/15 text-primary'
                                        : 'text-muted-foreground/50',
                                )}
                            >
                                {dia.programadas}
                            </span>
                        </button>
                    );
                })}
            </div>

            {programaciones.length === 0 ? (
                <EmptyState
                    icono={<Truck className="size-7" />}
                    titulo="Nada programado para este día"
                    descripcion={
                        puedeEditar
                            ? 'Agrega la primera unidad con «Programar unidad».'
                            : 'Todavía no se cargó la programación de este día.'
                    }
                />
            ) : (
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    {programaciones.map((tarjeta) => (
                        <TarjetaProgramacion
                            key={tarjeta.id}
                            programacion={tarjeta}
                            editable={puedeEditar}
                            onEditar={() => abrirEdicion(tarjeta)}
                            onBorrar={() => borrar(tarjeta)}
                        />
                    ))}
                </div>
            )}

            {puedeEditar && (
                <ProgramacionDialog
                    // Remonta el formulario al cambiar de tarjeta, para que
                    // los valores iniciales de `useForm` sean los correctos.
                    key={enEdicion?.id ?? `nueva-${fecha}`}
                    open={dialogoAbierto}
                    onOpenChange={setDialogoAbierto}
                    fecha={fecha}
                    programacion={enEdicion}
                    unidades={unidades}
                    conductores={conductores}
                    clientes={clientes}
                    destinosUsados={destinosUsados}
                    ultimoViajePorConductor={ultimoViajePorConductor}
                />
            )}
        </div>
    );
}

ProgramacionIndex.layout = {
    breadcrumbs: [{ title: 'Programación', href: programacion.index().url }],
};
