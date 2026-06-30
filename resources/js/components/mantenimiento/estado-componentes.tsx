import {
    Cog,
    Disc3,
    Droplet,
    Filter,
    Gauge,
    Thermometer,
    Wrench,
    Zap,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { PlanMantenimiento } from '@/types/fleet';

const ICONOS: Record<string, LucideIcon> = {
    aceite: Droplet,
    filtro_aire: Filter,
    filtro_combustible: Filter,
    frenos: Disc3,
    neumaticos: Gauge,
    refrigerante: Thermometer,
    transmision: Cog,
    bujias: Zap,
    bateria: Zap,
    cadena: Cog,
};

const ESTADO: Record<string, { label: string; badge: string; icono: string }> =
    {
        ok: {
            label: 'Al día',
            badge: 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-600/20',
            icono: 'text-emerald-600',
        },
        atencion: {
            label: 'Atención',
            badge: 'bg-amber-50 text-amber-700 ring-1 ring-amber-600/20',
            icono: 'text-amber-600',
        },
        proximo: {
            label: 'Revisar pronto',
            badge: 'bg-orange-50 text-orange-700 ring-1 ring-orange-600/20',
            icono: 'text-orange-600',
        },
        vencido: {
            label: 'Vencido',
            badge: 'bg-red-50 text-red-700 ring-1 ring-red-600/20',
            icono: 'text-red-600',
        },
        sin_historial: {
            label: 'Sin registro',
            badge: 'bg-zinc-100 text-zinc-600 ring-1 ring-zinc-500/20',
            icono: 'text-zinc-500',
        },
    };

function statusDe(item: PlanMantenimiento): string {
    if (item.ultimo_km === null && item.ultimo_realizado === null) {
        return 'sin_historial';
    }

    if (item.vencido) {
        return 'vencido';
    }

    if (item.km_restantes !== null && item.km_restantes <= 500) {
        return 'proximo';
    }

    if (item.km_restantes !== null && item.km_restantes <= 1000) {
        return 'atencion';
    }

    if (item.dias_restantes !== null && item.dias_restantes <= 30) {
        return 'proximo';
    }

    return 'ok';
}

export function EstadoComponentes({
    plan,
    odometroVigente,
}: {
    plan: PlanMantenimiento[];
    odometroVigente: number;
}) {
    return (
        <section className="rounded-xl border border-border bg-card p-5">
            <h2 className="mb-4 text-sm font-semibold text-foreground">
                Estado de componentes
            </h2>

            {plan.length === 0 ? (
                <p className="py-3 text-center text-sm text-muted-foreground">
                    No hay plan de mantenimiento configurado para este vehículo.
                </p>
            ) : (
                <div className="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
                    {plan.map((item) => {
                        const estado = ESTADO[statusDe(item)] ?? ESTADO.ok;
                        const Icono = ICONOS[item.tipo_mantenimiento] ?? Wrench;
                        const hace =
                            item.ultimo_km !== null
                                ? Math.max(0, odometroVigente - item.ultimo_km)
                                : null;

                        return (
                            <div
                                key={item.plantilla_id}
                                className="flex flex-col gap-2 rounded-lg border border-border p-3"
                            >
                                <Icono className={`size-5 ${estado.icono}`} />
                                <p className="text-sm leading-tight font-medium text-foreground">
                                    {item.nombre}
                                </p>
                                <span
                                    className={`inline-flex w-fit items-center rounded-full px-2 py-0.5 text-[11px] font-medium ${estado.badge}`}
                                >
                                    {estado.label}
                                </span>
                                <div className="text-[11px] leading-snug text-muted-foreground">
                                    {hace !== null ? (
                                        <p>
                                            Hace {hace.toLocaleString('es-PE')}{' '}
                                            km
                                        </p>
                                    ) : (
                                        <p>Sin registro previo</p>
                                    )}
                                    {item.proximo_km !== null && (
                                        <p>
                                            Próximo:{' '}
                                            {item.proximo_km.toLocaleString(
                                                'es-PE',
                                            )}{' '}
                                            km
                                        </p>
                                    )}
                                </div>
                            </div>
                        );
                    })}
                </div>
            )}
        </section>
    );
}
