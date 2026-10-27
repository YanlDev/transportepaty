import { cn } from '@/lib/utils';

/** Cómo se lee cada campo en el historial; lo que no está se muestra tal cual. */
const CAMPOS: Record<string, string> = {
    monto: 'Monto',
    monto_previsto: 'Previsto',
    fecha: 'Fecha',
    numero_operacion: 'N.º de operación',
    observacion: 'Observación',
    concepto: 'Concepto',
    motivo_anulacion: 'Motivo',
    anulado_at: 'Anulado',
    anulado_por: 'Anulado por',
    estado: 'Estado',
    galones: 'Galones',
    precio_galon: 'Precio por galón',
    importe: 'Importe',
    tipo_carga: 'Carga',
    zona_origen: 'Desde',
    zona_destino: 'Hasta',
    inicio: 'Salida',
    fin: 'Fin',
    placa: 'Placa',
    conductor_id: 'Conductor',
    carreta_id: 'Carreta',
    tracto_id: 'Unidad',
    vehiculo_id: 'Unidad',
    vuelta_id: 'Ticket',
    cliente_id: 'Cliente',
    user_id: 'Registrado por',
    password: 'Contraseña',
};

/** Los campos técnicos no aportan al leer el historial de un vistazo. */
const OCULTOS = new Set([
    'anulado_por',
    'user_id',
    'linea_regla_abastecimiento_id',
]);

export const ACCIONES: Record<string, { texto: string; clase: string }> = {
    creado: {
        texto: 'Creó',
        clase: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-950 dark:text-emerald-300',
    },
    editado: {
        texto: 'Cambió',
        clase: 'bg-sky-50 text-sky-700 ring-sky-600/20 dark:bg-sky-950 dark:text-sky-300',
    },
    anulado: {
        texto: 'Anuló',
        clase: 'bg-amber-50 text-amber-800 ring-amber-600/20 dark:bg-amber-950 dark:text-amber-300',
    },
    eliminado: {
        texto: 'Eliminó',
        clase: 'bg-red-50 text-red-700 ring-red-600/20 dark:bg-red-950 dark:text-red-300',
    },
};

export function etiquetaCampo(campo: string): string {
    return CAMPOS[campo] ?? campo.replace(/_/g, ' ');
}

function valor(dato: unknown): string {
    if (dato === null || dato === undefined || dato === '') {
        return '—';
    }

    if (typeof dato === 'boolean') {
        return dato ? 'sí' : 'no';
    }

    const texto = typeof dato === 'string' ? dato : JSON.stringify(dato);

    // Fechas guardadas como «2026-10-27 15:04:00» o ISO: se leen dd/mm/aaaa.
    const fecha = /^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2}))?/.exec(
        texto,
    );

    if (fecha) {
        return `${fecha[3]}/${fecha[2]}/${fecha[1]}${fecha[4] ? ` ${fecha[4]}:${fecha[5]}` : ''}`;
    }

    return texto.length > 80 ? `${texto.slice(0, 80)}…` : texto;
}

/**
 * Qué cambió en un movimiento: «Monto: 143.00 → 150.00». Al crear o
 * eliminar no hay antes o después, y se muestra solo el valor.
 */
export function CambiosMovimiento({
    accion,
    cambios,
    className,
}: {
    accion: string;
    cambios: Record<string, [unknown, unknown]>;
    className?: string;
}) {
    const filas = Object.entries(cambios).filter(
        ([campo, [, despues]]) =>
            !OCULTOS.has(campo) &&
            !(accion === 'creado' && (despues === null || despues === '')),
    );

    if (filas.length === 0) {
        return null;
    }

    return (
        <ul className={cn('flex flex-col gap-0.5 text-xs', className)}>
            {filas.map(([campo, [antes, despues]]) => (
                <li key={campo} className="break-words">
                    <span className="text-muted-foreground">
                        {etiquetaCampo(campo)}:
                    </span>{' '}
                    {accion === 'creado' ? (
                        valor(despues)
                    ) : accion === 'eliminado' ? (
                        <span className="line-through">{valor(antes)}</span>
                    ) : (
                        <>
                            <span className="text-muted-foreground line-through">
                                {valor(antes)}
                            </span>{' '}
                            → {valor(despues)}
                        </>
                    )}
                </li>
            ))}
        </ul>
    );
}
