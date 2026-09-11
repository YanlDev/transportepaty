import { Link } from '@inertiajs/react';
import {
    CalendarDays,
    Mail,
    MapPin,
    Pencil,
    Phone,
    ShieldCheck,
} from 'lucide-react';
import { edit } from '@/actions/App/Http/Controllers/ConductorController';
import { Button } from '@/components/ui/button';
import { StatusBadge } from '@/components/ui/status-badge';
import { formatearFecha } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { Conductor } from '@/types/fleet';

export function ConductorIdentidad({
    conductor,
    puedeEditar,
}: {
    conductor: Conductor;
    puedeEditar: boolean;
}) {
    return (
        <section className="flex flex-col gap-5 rounded-xl border border-border bg-card p-5 lg:sticky lg:top-4 lg:self-start">
            <div className="flex flex-col items-center gap-3 text-center lg:items-start lg:text-left">
                <div className="relative">
                    <div className="grid size-20 place-items-center rounded-full bg-primary text-2xl font-semibold text-primary-foreground">
                        {iniciales(conductor)}
                    </div>
                    <span
                        className={cn(
                            'absolute right-1 bottom-1 size-4 rounded-full ring-2 ring-card',
                            conductor.activo ? 'bg-emerald-500' : 'bg-zinc-400',
                        )}
                        aria-hidden
                    />
                </div>

                <div>
                    <h1 className="text-lg font-semibold tracking-tight">
                        {conductor.nombre_completo}
                    </h1>
                    <p className="mt-1 flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                        DNI {conductor.documento}
                        <StatusBadge
                            label={conductor.activo ? 'Activo' : 'Inactivo'}
                            tone={conductor.activo ? 'success' : 'neutral'}
                            dot={false}
                        />
                    </p>
                </div>

                {puedeEditar && (
                    <Button
                        asChild
                        variant="outline"
                        size="sm"
                        className="w-full"
                    >
                        <Link href={edit(conductor.id)}>
                            <Pencil className="size-4" />
                            Editar
                        </Link>
                    </Button>
                )}
            </div>

            {!conductor.activo && (
                <div className="rounded-lg bg-muted/50 p-3 text-xs">
                    <p className="font-medium">
                        Baja
                        {conductor.fecha_baja &&
                            ` · ${formatearFecha(conductor.fecha_baja)}`}
                    </p>
                    {conductor.motivo_baja && (
                        <p className="mt-1 text-muted-foreground">
                            {conductor.motivo_baja}
                        </p>
                    )}
                </div>
            )}

            <div className="grid grid-cols-2 gap-3 border-t pt-4">
                <div>
                    <p className="text-xs text-muted-foreground">Licencia</p>
                    <p className="mt-0.5 font-mono text-sm">
                        {conductor.licencia ?? '—'}
                    </p>
                </div>
                <div>
                    <p className="text-xs text-muted-foreground">Categoría</p>
                    <p className="mt-0.5 text-sm">
                        {conductor.categoria_licencia ?? '—'}
                    </p>
                </div>
            </div>

            <div className="border-t pt-4">
                <h2 className="mb-3 text-sm font-semibold">
                    Información personal
                </h2>
                <ul className="flex flex-col gap-2.5 text-sm">
                    <DatoPersonal icono={Phone}>
                        {conductor.telefono ? (
                            <a
                                href={`tel:${conductor.telefono}`}
                                className="tabular-nums hover:underline"
                            >
                                {conductor.telefono}
                            </a>
                        ) : (
                            '—'
                        )}
                    </DatoPersonal>
                    <DatoPersonal icono={Mail}>
                        {conductor.email ?? '—'}
                    </DatoPersonal>
                    <DatoPersonal icono={MapPin}>
                        {conductor.procedencia ?? '—'}
                    </DatoPersonal>
                    <DatoPersonal icono={CalendarDays}>
                        {conductor.fecha_nacimiento ? (
                            <>
                                {formatearFecha(conductor.fecha_nacimiento)}
                                <span className="ml-1.5 text-muted-foreground">
                                    ({edad(conductor.fecha_nacimiento)} años)
                                </span>
                            </>
                        ) : (
                            '—'
                        )}
                    </DatoPersonal>
                    <DatoPersonal icono={ShieldCheck}>
                        Revalidación{' '}
                        {conductor.licencia_vence
                            ? formatearFecha(conductor.licencia_vence)
                            : '—'}
                    </DatoPersonal>
                </ul>
            </div>
        </section>
    );
}

function DatoPersonal({
    icono: Icono,
    children,
}: {
    icono: typeof Phone;
    children: React.ReactNode;
}) {
    return (
        <li className="flex items-center gap-2.5">
            <Icono className="size-4 shrink-0 text-muted-foreground" />
            <span className="min-w-0 truncate">{children}</span>
        </li>
    );
}

function iniciales(conductor: Conductor): string {
    return `${conductor.nombres.charAt(0)}${conductor.apellidos.charAt(0)}`.toUpperCase();
}

function edad(fechaNacimiento: string): number {
    const nacimiento = new Date(`${fechaNacimiento}T00:00:00`);
    const hoy = new Date();
    const anios = hoy.getFullYear() - nacimiento.getFullYear();
    const cumplioEsteAnio =
        hoy.getMonth() > nacimiento.getMonth() ||
        (hoy.getMonth() === nacimiento.getMonth() &&
            hoy.getDate() >= nacimiento.getDate());

    return cumplioEsteAnio ? anios : anios - 1;
}
