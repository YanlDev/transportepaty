import { CircleCheck } from 'lucide-react';
import { galones } from '@/components/abastecimiento/barra-combustible';
import { Placa } from '@/components/placa';
import { Button } from '@/components/ui/button';
import { formatearFecha } from '@/lib/format';
import type {
    CombustibleDelTicket,
    VueltaAbastecimiento,
} from '@/types/abastecimiento';

const NOMBRE_TRAMO = { ida: 'Ida', regreso: 'Vuelta' } as const;

/** Un combustible autorizado que todavía no tiene su voucher. */
export type VoucherPendiente = {
    vuelta: VueltaAbastecimiento;
    registro: CombustibleDelTicket;
    /** Días desde que se autorizó. */
    dias: number;
};

/** Días enteros desde una fecha `Y-m-d` hasta hoy, en la hora del navegador. */
function diasDesde(fecha: string): number {
    const [anio, mes, dia] = fecha.split('-').map(Number);
    const hoy = new Date();
    const desde = Date.UTC(anio, mes - 1, dia);
    const hasta = Date.UTC(hoy.getFullYear(), hoy.getMonth(), hoy.getDate());

    return Math.max(0, Math.round((hasta - desde) / 86_400_000));
}

/**
 * Todo combustible vigente sin voucher, del que más espera al más reciente:
 * sin el voucher no hay costo real ni rendimiento, y un tanque full sin él
 * deja su tramo en espera.
 */
export function vouchersPendientes(
    vueltas: VueltaAbastecimiento[],
): VoucherPendiente[] {
    return vueltas
        .flatMap((vuelta) =>
            vuelta.abastecimientos
                .filter(
                    (registro) =>
                        registro.anulado === null && registro.despacho === null,
                )
                .map((registro) => ({
                    vuelta,
                    registro,
                    dias: diasDesde(registro.fecha),
                })),
        )
        .sort((a, b) => b.dias - a.dias);
}

function hace(dias: number): string {
    return dias === 0 ? 'hoy' : dias === 1 ? 'hace 1 día' : `hace ${dias} días`;
}

/**
 * Lo que hay que hacer en abastecimiento, antes de la tabla: los vouchers
 * que faltan (lo que más se atrasa) y, en una línea cada uno, lo que tiene
 * algún tramo detenido. Con todo al día se dice en una línea y no ocupa más.
 */
export function ColaDeTrabajo({
    vueltas,
    puedeRegistrar,
    onVoucher,
    onAbrir,
}: {
    vueltas: VueltaAbastecimiento[];
    puedeRegistrar: boolean;
    onVoucher: (pendiente: VoucherPendiente) => void;
    onAbrir: (vuelta: VueltaAbastecimiento) => void;
}) {
    const pendientes = vouchersPendientes(vueltas);

    const enEspera = vueltas.flatMap((vuelta) =>
        vuelta.tramos
            .filter((tramo) => tramo.tanque_lleno.sin_voucher)
            .map((tramo) => ({ vuelta, tramo })),
    );
    const sinTope = vueltas.flatMap((vuelta) =>
        vuelta.tramos
            .filter((tramo) => tramo.rutas.length > 0 && !tramo.confirmada)
            .map((tramo) => ({ vuelta, tramo })),
    );
    const sinKm = vueltas.filter(
        (vuelta) => vuelta.llegada_el !== null && vuelta.km_llegada === null,
    );

    const avisos = [
        {
            clave: 'full',
            filas: enEspera,
            titulo: (cantidad: number) =>
                cantidad === 1
                    ? '1 tramo en espera por tanque full'
                    : `${cantidad} tramos en espera por tanque full`,
            ayuda: 'Falta el voucher del full: no se autoriza más hasta registrarlo.',
        },
        {
            clave: 'tope',
            filas: sinTope,
            titulo: (cantidad: number) =>
                cantidad === 1
                    ? '1 tramo sin tope'
                    : `${cantidad} tramos sin tope`,
            ayuda: 'Falta elegir la variante por peso o definir galones en la tabla de rutas.',
        },
        {
            clave: 'km',
            filas: sinKm.map((vuelta) => ({ vuelta, tramo: null })),
            titulo: (cantidad: number) =>
                cantidad === 1
                    ? '1 unidad llegó a base sin km'
                    : `${cantidad} unidades llegaron a base sin km`,
            ayuda: 'Sin el km de llegada no se calcula el rendimiento del ticket.',
        },
    ].filter((aviso) => aviso.filas.length > 0);

    if (pendientes.length === 0 && avisos.length === 0) {
        return (
            <p className="flex items-center gap-2 text-sm text-muted-foreground">
                <CircleCheck className="size-4 text-emerald-600" />
                Al día: no hay vouchers pendientes ni tramos detenidos.
            </p>
        );
    }

    return (
        <section
            aria-label="Lo que hay que hacer"
            className="flex flex-col gap-3"
        >
            <h2 className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                Lo que hay que hacer
            </h2>

            {pendientes.length > 0 && (
                <div className="overflow-hidden rounded-xl border bg-card">
                    <div className="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 border-b border-amber-200 bg-amber-50 px-4 py-3 text-amber-900 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200">
                        <div className="flex flex-col">
                            <strong className="text-sm">
                                {pendientes.length === 1
                                    ? '1 voucher pendiente'
                                    : `${pendientes.length} vouchers pendientes`}
                            </strong>
                            <span className="text-xs">
                                Combustible autorizado sin su voucher: sin él no
                                hay costo real ni rendimiento.
                            </span>
                        </div>
                        {pendientes[0].dias > 0 && (
                            <span className="text-xs font-semibold">
                                El más antiguo espera{' '}
                                {pendientes[0].dias === 1
                                    ? '1 día'
                                    : `${pendientes[0].dias} días`}
                            </span>
                        )}
                    </div>
                    <ul className="divide-y">
                        {pendientes.map((pendiente) => (
                            <li
                                key={pendiente.registro.id}
                                className="flex flex-wrap items-center gap-x-4 gap-y-2 px-4 py-2.5 text-sm"
                            >
                                <button
                                    type="button"
                                    onClick={() => onAbrir(pendiente.vuelta)}
                                    className="rounded-md text-left hover:opacity-80"
                                    title="Abrir la ficha del ticket"
                                >
                                    <Placa
                                        placa={pendiente.vuelta.tracto.placa}
                                    />
                                </button>
                                <span className="min-w-40 flex-1">
                                    <span className="font-mono text-xs text-muted-foreground">
                                        {pendiente.vuelta.codigo}
                                    </span>{' '}
                                    ·{' '}
                                    {pendiente.registro.sentido
                                        ? NOMBRE_TRAMO[
                                              pendiente.registro.sentido
                                          ]
                                        : 'Sin tramo'}{' '}
                                    · {pendiente.registro.punto ?? 'Sin punto'}
                                </span>
                                <span className="text-muted-foreground tabular-nums">
                                    {pendiente.registro.tanque_lleno
                                        ? 'Tanque full'
                                        : `${galones(pendiente.registro.galones)} autorizados`}{' '}
                                    el{' '}
                                    {formatearFecha(pendiente.registro.fecha)}
                                </span>
                                <span
                                    className={
                                        pendiente.dias >= 3
                                            ? 'w-24 font-semibold text-amber-700 tabular-nums dark:text-amber-400'
                                            : 'w-24 text-muted-foreground tabular-nums'
                                    }
                                >
                                    {hace(pendiente.dias)}
                                </span>
                                {puedeRegistrar &&
                                    pendiente.vuelta.bloqueo_gastos ===
                                        null && (
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            className="pointer-coarse:min-h-11"
                                            onClick={() => onVoucher(pendiente)}
                                        >
                                            Registrar voucher
                                        </Button>
                                    )}
                            </li>
                        ))}
                    </ul>
                </div>
            )}

            {avisos.length > 0 && (
                <div className="grid gap-3 md:grid-cols-3">
                    {avisos.map((aviso) => (
                        <div
                            key={aviso.clave}
                            className="flex flex-col gap-1.5 rounded-xl border bg-card px-4 py-3 text-sm"
                        >
                            <strong>{aviso.titulo(aviso.filas.length)}</strong>
                            <span className="text-xs text-muted-foreground">
                                {aviso.ayuda}
                            </span>
                            <div className="flex flex-wrap gap-1.5">
                                {aviso.filas.map(({ vuelta, tramo }) => (
                                    <button
                                        key={`${vuelta.id}-${tramo?.sentido ?? 'km'}`}
                                        type="button"
                                        onClick={() => onAbrir(vuelta)}
                                        className="rounded-md border px-2 py-1 text-xs font-medium hover:bg-accent pointer-coarse:min-h-11"
                                        title={
                                            tramo?.pendiente ??
                                            'Abrir la ficha del ticket'
                                        }
                                    >
                                        {vuelta.tracto.placa}
                                        {tramo &&
                                            ` · ${NOMBRE_TRAMO[tramo.sentido]}`}
                                    </button>
                                ))}
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </section>
    );
}
