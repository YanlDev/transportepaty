import { Head, router, setLayoutProps } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useState } from 'react';
import asistencia, {
    actualizarDiasDebidos,
    actualizarNotas,
    destroy,
    marcar,
    show,
} from '@/actions/App/Http/Controllers/AsistenciaController';
import { EstadoAsistenciaOpciones } from '@/components/asistencia/estado-asistencia-opciones';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { estadoConfig } from '@/lib/asistencia';
import { cn } from '@/lib/utils';
import type {
    AsistenciaCalendarioDia,
    AsistenciaCalendarioMes,
    AsistenciaConductor,
    AsistenciaMarca,
    EstadoAsistencia,
} from '@/types/fleet';

type Props = {
    conductor: AsistenciaConductor;
    mes: string;
    cantidadMeses: number;
    calendarios: AsistenciaCalendarioMes[];
};

/** Borde estilo Excel, igual criterio que el rooster general. */
const CELDA_CON_BORDE = 'border-r border-b border-border';

export default function AsistenciaShow({
    conductor,
    mes,
    cantidadMeses,
    calendarios,
}: Props) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Asistencia', href: asistencia.index().url },
            { title: conductor.nombre_completo, href: show(conductor.id).url },
        ],
    });

    const irA = (nuevoMes: string, nuevaCantidad: number) => {
        router.get(
            show(conductor.id).url,
            { mes: nuevoMes, meses: nuevaCantidad },
            { preserveScroll: true },
        );
    };

    const sumarMeses = (cantidad: number) => {
        const [anio, mesNum] = mes.split('-').map(Number);
        const siguiente = new Date(anio, mesNum - 1 + cantidad, 1);
        irA(
            `${siguiente.getFullYear()}-${String(siguiente.getMonth() + 1).padStart(2, '0')}-01`,
            cantidadMeses,
        );
    };

    const cambiarCantidad = (valor: string) => {
        if (!valor) {
            return;
        }

        irA(mes, Number(valor));
    };

    return (
        <div className="mx-auto flex h-full w-full max-w-5xl flex-1 flex-col gap-6 p-4 md:p-6">
            <Head title={`Asistencia · ${conductor.nombre_completo}`} />

            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <h1 className="text-xl font-semibold tracking-tight">
                    {conductor.nombre_completo}
                </h1>

                <div className="flex items-center gap-3">
                    <div className="flex items-center gap-2">
                        <Button
                            variant="outline"
                            size="icon"
                            onClick={() => sumarMeses(-1)}
                            aria-label="Mes anterior"
                        >
                            <ChevronLeft className="size-4" />
                        </Button>
                        <Button
                            variant="outline"
                            size="icon"
                            onClick={() => sumarMeses(1)}
                            aria-label="Mes siguiente"
                        >
                            <ChevronRight className="size-4" />
                        </Button>
                    </div>

                    <ToggleGroup
                        type="single"
                        variant="outline"
                        value={String(cantidadMeses)}
                        onValueChange={cambiarCantidad}
                        aria-label="Cantidad de meses a mostrar"
                    >
                        {[2, 3, 4].map((cantidad) => (
                            <ToggleGroupItem
                                key={cantidad}
                                value={String(cantidad)}
                                className="w-8 text-xs"
                            >
                                {cantidad}
                            </ToggleGroupItem>
                        ))}
                    </ToggleGroup>
                </div>
            </div>

            <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted-foreground">
                {(Object.keys(estadoConfig) as EstadoAsistencia[]).map(
                    (estado) => (
                        <span
                            key={estado}
                            className="inline-flex items-center gap-1.5"
                        >
                            <span
                                className={cn(
                                    'grid size-4 place-items-center rounded-none text-[10px] font-bold',
                                    estadoConfig[estado].badge,
                                )}
                            >
                                {estadoConfig[estado].letra}
                            </span>
                            {estadoConfig[estado].label}
                        </span>
                    ),
                )}
            </div>

            <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                {calendarios.map((calendario) => (
                    <MesCalendario
                        key={calendario.mes}
                        conductorId={conductor.id}
                        calendario={calendario}
                    />
                ))}
            </div>
        </div>
    );
}

/** «agosto 2026» a partir del primer día del mes (Y-m-d). */
function formatearMes(mes: string): string {
    const d = new Date(`${mes}T00:00:00`);

    return d.toLocaleDateString('es-PE', { month: 'long', year: 'numeric' });
}

function MesCalendario({
    conductorId,
    calendario,
}: {
    conductorId: number;
    calendario: AsistenciaCalendarioMes;
}) {
    const semanas: AsistenciaCalendarioDia[][] = [];

    for (let i = 0; i < calendario.dias.length; i += 7) {
        semanas.push(calendario.dias.slice(i, i + 7));
    }

    const nombresDias = semanas[0]?.map((dia) => dia.dia_semana) ?? [];

    return (
        <div>
            <div className="mb-2 flex items-center justify-between gap-3">
                <p className="text-sm font-medium capitalize">
                    {formatearMes(calendario.mes)}
                </p>
                <DiasDebidosInput
                    conductorId={conductorId}
                    mes={calendario.mes}
                    diasDebidos={calendario.dias_debidos}
                />
            </div>

            <div className="border border-border">
                <div className="grid grid-cols-7">
                    {nombresDias.map((nombre, indice) => (
                        <div
                            key={indice}
                            className={cn(
                                CELDA_CON_BORDE,
                                'bg-muted/40 p-1.5 text-center text-xs font-medium text-muted-foreground',
                            )}
                        >
                            {nombre}
                        </div>
                    ))}
                </div>

                {semanas.map((semana, indice) => (
                    <div key={indice} className="grid grid-cols-7">
                        {semana.map((dia) => (
                            <DiaCelda
                                key={dia.fecha}
                                conductorId={conductorId}
                                dia={dia}
                                marca={calendario.marcas[dia.fecha]}
                            />
                        ))}
                    </div>
                ))}
            </div>

            <NotasMesInput
                conductorId={conductorId}
                mes={calendario.mes}
                notas={calendario.notas}
            />
        </div>
    );
}

/**
 * Notas libres del mes, aparte de las marcas diarias y del balance de días:
 * incidencias, acuerdos verbales, lo que no encaja en ninguno de los dos.
 * Se guarda al salir del campo, igual que el balance de días de al lado.
 */
function NotasMesInput({
    conductorId,
    mes,
    notas,
}: {
    conductorId: number;
    mes: string;
    notas: string | null;
}) {
    const [valor, setValor] = useState(notas ?? '');

    const guardar = () => {
        if (valor === (notas ?? '')) {
            return;
        }

        router.patch(
            actualizarNotas(conductorId).url,
            { mes, notas: valor },
            { preserveScroll: true },
        );
    };

    return (
        <Textarea
            value={valor}
            onChange={(evento) => setValor(evento.target.value)}
            onBlur={guardar}
            placeholder="Notas del mes..."
            rows={2}
            className="mt-2 resize-none text-xs"
        />
    );
}

function DiaCelda({
    conductorId,
    dia,
    marca,
}: {
    conductorId: number;
    dia: AsistenciaCalendarioDia;
    marca: AsistenciaMarca | undefined;
}) {
    const info = marca ? estadoConfig[marca.estado] : null;

    const marcarComo = (estado: EstadoAsistencia) => {
        router.patch(
            marcar(conductorId).url,
            { fecha: dia.fecha, estado },
            { preserveScroll: true },
        );
    };

    const quitarMarca = () => {
        if (!marca) {
            return;
        }

        router.delete(destroy(marca.asistencia_id).url, {
            preserveScroll: true,
        });
    };

    if (dia.es_relleno) {
        return (
            <div
                className={cn(
                    CELDA_CON_BORDE,
                    'min-h-14 p-1.5 text-xs text-muted-foreground/40',
                )}
            >
                {dia.numero}
            </div>
        );
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger
                className={cn(
                    CELDA_CON_BORDE,
                    'flex min-h-14 w-full cursor-pointer flex-col items-start gap-1 p-1.5 text-left hover:bg-muted/40',
                    dia.es_domingo && 'bg-muted/20',
                )}
                title={info ? info.label : 'Sin marcar'}
            >
                <span className="text-xs tabular-nums">{dia.numero}</span>
                {info && (
                    <span
                        className={cn(
                            'w-full truncate rounded-none px-1 py-0.5 text-center text-[10px] font-bold',
                            info.badge,
                        )}
                    >
                        {info.letra}
                    </span>
                )}
            </DropdownMenuTrigger>
            <EstadoAsistenciaOpciones
                align="start"
                marca={marca}
                onSeleccionar={marcarComo}
                onQuitar={quitarMarca}
            />
        </DropdownMenu>
    );
}

/**
 * El saldo de descanso del conductor ese mes. Es un número que escribe el
 * admin a mano —no lo calcula la app—, porque la deuda real arrastra meses
 * de antes de este sistema que no tienen marcas con las que reconstruirla.
 * Cada mes es independiente: no arrastra saldo del anterior.
 *
 * Tiene signo: positivo es la empresa debiéndole días de descanso al
 * conductor (el caso normal, le faltó descansar); negativo es al revés
 * —descansó de más ese mes y le debe un día de trabajo a la empresa—, así
 * que la etiqueta a la izquierda cambia según para qué lado cae el saldo.
 */
function DiasDebidosInput({
    conductorId,
    mes,
    diasDebidos,
}: {
    conductorId: number;
    mes: string;
    diasDebidos: number;
}) {
    const [valor, setValor] = useState(String(diasDebidos));

    const guardar = () => {
        const numero = Math.min(31, Math.max(-31, Number(valor) || 0));

        setValor(String(numero));

        if (numero === diasDebidos) {
            return;
        }

        router.patch(
            actualizarDiasDebidos(conductorId).url,
            { mes, dias_debidos: numero },
            { preserveScroll: true },
        );
    };

    const quienDebe =
        diasDebidos > 0
            ? 'Empresa debe'
            : diasDebidos < 0
              ? 'Conductor debe'
              : 'Balance';

    const colorEtiqueta =
        diasDebidos < 0
            ? 'text-red-700 dark:text-red-400'
            : 'text-muted-foreground';

    return (
        <label className="flex shrink-0 items-center gap-1.5 text-xs">
            <span className={colorEtiqueta}>{quienDebe}</span>
            <Input
                type="number"
                min={-31}
                max={31}
                value={valor}
                onChange={(evento) => setValor(evento.target.value)}
                onBlur={guardar}
                onKeyDown={(evento) => {
                    if (evento.key === 'Enter') {
                        evento.currentTarget.blur();
                    }
                }}
                className="h-7 w-14 px-2 text-center text-xs"
            />
            <span className="text-muted-foreground">días</span>
        </label>
    );
}
