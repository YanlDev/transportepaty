import { Head, router, usePoll } from '@inertiajs/react';
import { Plus, Truck } from '@phosphor-icons/react';
import { useMemo, useState } from 'react';
import programacion from '@/actions/App/Http/Controllers/ProgramacionController';
import { EmptyState } from '@/components/empty-state';
import { ProgramacionDialog } from '@/components/programacion/programacion-dialog';
import { TableroSalidas } from '@/components/programacion/tablero-salidas';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { usePermisos } from '@/hooks/use-permisos';
import { avisarError } from '@/lib/aviso-error';
import { cn } from '@/lib/utils';
import type {
    AvisoOperaciones,
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
    avisoOperaciones: AvisoOperaciones;
    advertencia: string;
    semana: DiaDeSemana[];
    unidades: UnidadOpcion[];
    conductores: ConductorOpcion[];
    clientes: ClienteOpcion[];
    destinosUsados: string[];
    ultimoViajePorConductor: Record<number, UltimoViaje>;
};

const DIAS_CORTOS = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'] as const;

/**
 * Qué unidades salen con carga particular cada día, como la pantalla de
 * salidas de un aeropuerto: una fila por unidad, con su cliente, destino,
 * conductor y si ya salió.
 *
 * Quien la lee —abastecimiento, o el televisor del patio— la mira de lejos,
 * así que el tablero se refresca solo cada minuto: cuando se registra la GR
 * de una unidad, su fila pasa a «SALIÓ» sin que nadie recargue.
 */
export default function ProgramacionIndex({
    fecha,
    programaciones,
    avisoOperaciones,
    advertencia,
    semana,
    unidades,
    conductores,
    clientes,
    destinosUsados,
    ultimoViajePorConductor,
}: Props) {
    const { puedeEditar } = usePermisos();

    usePoll(60_000, {
        only: ['programaciones', 'semana', 'avisoOperaciones'],
    });

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
                    titulo="Sin salidas programadas"
                    descripcion="Programa una unidad para este día."
                />
            ) : (
                <TableroSalidas
                    programaciones={programaciones}
                    avisoOperaciones={avisoOperaciones}
                    advertencia={advertencia}
                    editable={puedeEditar}
                    onEditar={abrirEdicion}
                    onBorrar={borrar}
                />
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
