import { Link } from '@inertiajs/react';
import {
    CalendarDays,
    IdCard,
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

/**
 * La cabecera de la ficha: quién es el conductor, en una franja a lo ancho.
 * Antes era una columna fija a la izquierda que dejaba media pantalla vacía
 * debajo y le quitaba ancho a lo que de verdad se viene a revisar.
 */
export function ConductorIdentidad({
    conductor,
    puedeEditar,
}: {
    conductor: Conductor;
    puedeEditar: boolean;
}) {
    return (
        <section className="rounded-xl border border-border bg-card p-4 md:p-5">
            <div className="flex flex-col gap-4 lg:flex-row lg:items-center">
                <div className="flex min-w-0 items-center gap-4 lg:w-80 lg:shrink-0">
                    <div className="relative shrink-0">
                        <div className="grid size-16 place-items-center rounded-full bg-primary text-xl font-semibold text-primary-foreground">
                            {iniciales(conductor)}
                        </div>
                        <span
                            className={cn(
                                'absolute right-0.5 bottom-0.5 size-3.5 rounded-full ring-2 ring-card',
                                conductor.activo
                                    ? 'bg-emerald-500'
                                    : 'bg-zinc-400',
                            )}
                            aria-hidden
                        />
                    </div>

                    <div className="min-w-0 flex-1">
                        <h1 className="text-lg leading-tight font-semibold tracking-tight">
                            {conductor.nombre_completo}
                        </h1>
                        <p className="mt-1 flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                            DNI {conductor.documento}
                            <StatusBadge
                                label={conductor.activo ? 'Activo' : 'Inactivo'}
                                tone={conductor.activo ? 'success' : 'neutral'}
                                dot={false}
                            />
                            {conductor.externo && (
                                <StatusBadge
                                    label="Externo"
                                    tone="info"
                                    dot={false}
                                />
                            )}
                        </p>
                    </div>

                    {puedeEditar && (
                        <Button
                            asChild
                            variant="outline"
                            size="sm"
                            className="lg:hidden"
                        >
                            <Link href={edit(conductor.id)}>
                                <Pencil className="size-4" />
                                Editar
                            </Link>
                        </Button>
                    )}
                </div>

                <dl className="grid flex-1 grid-cols-2 gap-x-6 gap-y-3 border-t pt-4 sm:grid-cols-3 lg:border-t-0 lg:border-l lg:pt-0 lg:pl-6 xl:grid-cols-5">
                    <Dato icono={IdCard} etiqueta="Licencia">
                        <span className="font-mono">
                            {conductor.licencia ?? '—'}
                        </span>
                        {conductor.categoria_licencia && (
                            <span className="ml-1.5 text-muted-foreground">
                                {conductor.categoria_licencia}
                            </span>
                        )}
                    </Dato>
                    <Dato icono={ShieldCheck} etiqueta="Revalidación">
                        {conductor.licencia_vence
                            ? formatearFecha(conductor.licencia_vence)
                            : '—'}
                    </Dato>
                    <Dato icono={Phone} etiqueta="Teléfono">
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
                    </Dato>
                    <Dato icono={MapPin} etiqueta="Procedencia">
                        {conductor.procedencia ?? '—'}
                    </Dato>
                    <Dato icono={CalendarDays} etiqueta="Nacimiento">
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
                    </Dato>
                </dl>

                {puedeEditar && (
                    <Button
                        asChild
                        variant="outline"
                        size="sm"
                        className="max-lg:hidden"
                    >
                        <Link href={edit(conductor.id)}>
                            <Pencil className="size-4" />
                            Editar
                        </Link>
                    </Button>
                )}
            </div>

            {!conductor.activo && (
                <div className="mt-4 rounded-lg bg-muted/50 p-3 text-xs">
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
        </section>
    );
}

function Dato({
    icono: Icono,
    etiqueta,
    children,
}: {
    icono: typeof Phone;
    etiqueta: string;
    children: React.ReactNode;
}) {
    return (
        <div className="flex min-w-0 items-start gap-2.5">
            <Icono className="mt-0.5 size-4 shrink-0 text-muted-foreground" />
            <div className="min-w-0">
                <dt className="text-xs text-muted-foreground">{etiqueta}</dt>
                <dd className="truncate text-sm">{children}</dd>
            </div>
        </div>
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
