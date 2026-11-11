import { Link } from '@inertiajs/react';
import abastecimientoRutas from '@/actions/App/Http/Controllers/AbastecimientoController';
import guiasRutas from '@/actions/App/Http/Controllers/GuiaPorAsignarController';
import tallerRutas from '@/actions/App/Http/Controllers/TallerController';
import vueltasRutas from '@/actions/App/Http/Controllers/VueltaController';
import { Button } from '@/components/ui/button';
import { usePermisos } from '@/hooks/use-permisos';
import { cn } from '@/lib/utils';
import type { PasoTicket, TarjetaVuelta, Vuelta } from '@/types/vueltas';

/**
 * Lo que sigue dicho como tarea: el paso «En ida» pendiente es, para quien
 * programa, asignarle la GR de ida.
 */
const TAREA: Record<PasoTicket['clave'], string> = {
    programado: 'Programar',
    taller: 'OK de taller',
    abastecimiento_ida: 'Combustible de ida',
    en_ida: 'GR de ida',
    regreso: 'Programar el regreso',
    abastecimiento_vuelta: 'Combustible de vuelta',
    en_vuelta: 'GR de vuelta',
    llego: 'Registrar la llegada',
    cerrado: 'Liquidar y cerrar',
};

/** El nombre de lo que sigue; las alertas lo dicen por su nombre. */
export function tareaSiguiente(siguiente: PasoTicket): string {
    if (siguiente.clave === 'taller' && siguiente.estado === 'alerta') {
        return 'No apto en taller';
    }

    if (siguiente.clave === 'llego' && siguiente.estado === 'alerta') {
        return 'Llegada atrasada';
    }

    return TAREA[siguiente.clave] ?? siguiente.titulo;
}

type Accion =
    | { tipo: 'enlace'; etiqueta: string; href: string }
    | { tipo: 'editar'; etiqueta: string }
    | { tipo: 'abrir'; etiqueta: string };

/**
 * El botón de lo que sigue: lleva a la pantalla donde se hace (taller,
 * abastecimiento, GR por asignar) si el usuario puede entrar; si no, abre
 * el ticket.
 */
function useAccion(vuelta: TarjetaVuelta): Accion {
    const { puede } = usePermisos();
    const ver: Accion = { tipo: 'abrir', etiqueta: 'Ver' };

    switch (vuelta.siguiente?.clave) {
        case 'taller':
            return puede('taller.ver')
                ? {
                      tipo: 'enlace',
                      etiqueta: 'Ir a taller',
                      href: tallerRutas.index().url,
                  }
                : ver;
        case 'abastecimiento_ida':
        case 'abastecimiento_vuelta':
            return puede('abastecimiento.ver') ||
                puede('abastecimiento.viaticos')
                ? {
                      tipo: 'enlace',
                      etiqueta: 'Abastecer',
                      href: abastecimientoRutas.index({
                          query: { ticket: vuelta.id },
                      }).url,
                  }
                : ver;
        case 'en_ida':
        case 'en_vuelta':
            return {
                tipo: 'enlace',
                etiqueta: 'Asignar GR',
                href: guiasRutas.index().url,
            };
        case 'regreso':
            return vuelta.editable
                ? { tipo: 'editar', etiqueta: 'Programar regreso' }
                : ver;
        case 'llego':
            return vuelta.registrable_llegada
                ? { tipo: 'abrir', etiqueta: 'Registrar llegada' }
                : ver;
        case 'cerrado':
            return vuelta.cerrable
                ? {
                      tipo: 'enlace',
                      etiqueta: 'Liquidar',
                      href: vueltasRutas.show(vuelta.id).url,
                  }
                : ver;
        default:
            return ver;
    }
}

export function BotonSiguiente({
    vuelta,
    onAbrir,
    onEditar,
    ocultarVer = false,
    className,
}: {
    vuelta: TarjetaVuelta;
    onAbrir: (vuelta: TarjetaVuelta) => void;
    onEditar: (vuelta: Vuelta) => void;
    /** En el panel del ticket «Ver» sobra: ya está abierto. */
    ocultarVer?: boolean;
    className?: string;
}) {
    const accion = useAccion(vuelta);
    const principal = accion.etiqueta !== 'Ver';

    if (!principal && ocultarVer) {
        return null;
    }

    const estilo = cn(
        'pointer-coarse:min-h-11',
        principal && 'border-primary text-primary hover:text-primary',
        className,
    );

    if (accion.tipo === 'enlace') {
        return (
            <Button variant="outline" size="sm" className={estilo} asChild>
                <Link href={accion.href}>{accion.etiqueta}</Link>
            </Button>
        );
    }

    return (
        <Button
            variant="outline"
            size="sm"
            className={estilo}
            onClick={() =>
                accion.tipo === 'editar' ? onEditar(vuelta) : onAbrir(vuelta)
            }
        >
            {accion.etiqueta}
        </Button>
    );
}

const ESTADO: Record<PasoTicket['estado'], string> = {
    hecho: 'hecho',
    parcial: 'a medias',
    pendiente: 'pendiente',
    alerta: 'con alerta',
};

/**
 * El circuito del ticket en una barra: un tramo por paso, lleno si se hizo,
 * a medias si va por la mitad, rojo si tiene una alerta y ámbar si quedó
 * atrás sin hacer. El paso en curso va con borde.
 */
export function AvanceTicket({
    vuelta,
    className,
}: {
    vuelta: TarjetaVuelta;
    className?: string;
}) {
    const pasos = vuelta.avance ?? [];

    if (pasos.length === 0) {
        return null;
    }

    const actual = vuelta.siguiente
        ? pasos.findIndex((paso) => paso.clave === vuelta.siguiente?.clave)
        : pasos.length;
    const hechos = pasos.filter((paso) => paso.estado === 'hecho').length;

    return (
        <div className={cn('flex min-w-0 flex-col gap-1.5', className)}>
            <div
                className="flex gap-0.5"
                role="img"
                aria-label={`${hechos} de ${pasos.length} pasos hechos`}
            >
                {pasos.map((paso, indice) => (
                    <span
                        key={paso.clave}
                        title={`${paso.titulo}: ${ESTADO[paso.estado]}`}
                        className={cn(
                            'h-1.5 flex-1 rounded-sm',
                            paso.estado === 'hecho'
                                ? 'bg-primary'
                                : indice === actual
                                  ? paso.estado === 'alerta'
                                      ? 'bg-red-600'
                                      : paso.estado === 'parcial'
                                        ? 'bg-primary/45'
                                        : 'bg-background ring-[1.5px] ring-primary ring-inset'
                                  : indice < actual
                                    ? 'bg-amber-400'
                                    : 'bg-muted-foreground/20',
                        )}
                    />
                ))}
            </div>
            <span className="text-[11px] text-muted-foreground">
                {hechos} de {pasos.length} pasos
            </span>
        </div>
    );
}
