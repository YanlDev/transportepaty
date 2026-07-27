export type EnumOption = {
    value: string;
    label: string;
};

export type Conductor = {
    id: number;
    user_id: number | null;
    nombres: string;
    apellidos: string;
    documento: string;
    licencia: string | null;
    categoria_licencia: string | null;
    licencia_vence: string | null;
    telefono: string | null;
    email: string | null;
    fecha_nacimiento: string | null;
    procedencia: string | null;
    activo: boolean;
    nombre_completo: string;
};

export type ConductorListItem = {
    id: number;
    nombres: string;
    apellidos: string;
    nombre_completo: string;
    documento: string;
    licencia: string | null;
    categoria_licencia: string | null;
    licencia_vence: string | null;
    telefono: string | null;
    email: string | null;
    procedencia: string | null;
    activo: boolean;
};

/** A user account as listed in the admin users screen. */
export type UserListItem = {
    id: number;
    name: string;
    email: string;
    email_verified_at: string | null;
    roles: { id: number; name: string }[];
};

/** A conductor option for linking from the user form. */
export type ConductorLinkOption = {
    id: number;
    nombres: string;
    apellidos: string;
    nombre_completo: string;
};

export type VehiculoListItem = {
    id: number;
    placa: string;
    marca: string | null;
    modelo: string | null;
    anio: number | null;
    tipo: string;
    tipo_label: string;
    estado: string;
    caja: string | null;
    caja_label: string | null;
    color: string | null;
    ejes: number | null;
};

export type Vehiculo = {
    id: number;
    placa: string;
    marca: string | null;
    modelo: string | null;
    anio: number | null;
    tipo: string;
    estado: string;
    caja: string | null;
    vin: string | null;
    numero_motor: string | null;
    color: string | null;
    ejes: number | null;
    peso_neto: number | null;
    peso_bruto: number | null;
    carga_util: number | null;
    fecha_adquisicion: string | null;
    observaciones: string | null;
    created_at: string;
    updated_at: string;
};

export type VehiculoDocumentoItem = {
    id: number;
    tipo: string;
    tipo_label: string;
    nombre: string | null;
    numero: string | null;
    fecha_emision: string | null;
    fecha_vencimiento: string | null;
    url: string;
    es_pdf: boolean;
};

export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

export type Paginator<T> = {
    data: T[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    per_page: number;
    from: number | null;
    to: number | null;
    total: number;
};

type EstadoConfig = {
    label: string;
    badge: string;
    dot: string;
};

/**
 * Visual config per vehicle status (label, badge classes, indicator dot).
 */
export const estadoConfig: Record<string, EstadoConfig> = {
    activo: {
        label: 'Operativo',
        badge: 'bg-navy-50 text-navy-700 ring-1 ring-navy-600/20',
        dot: 'bg-navy-500',
    },
    en_mantenimiento: {
        label: 'En mantenimiento',
        badge: 'bg-amber-50 text-amber-700 ring-1 ring-amber-600/20',
        dot: 'bg-amber-500',
    },
    inactivo: {
        label: 'Inactivo',
        badge: 'bg-zinc-100 text-zinc-600 ring-1 ring-zinc-500/20',
        dot: 'bg-zinc-400',
    },
    dado_de_baja: {
        label: 'Dado de baja',
        badge: 'bg-red-50 text-red-700 ring-1 ring-red-600/20',
        dot: 'bg-red-500',
    },
};

export function estadoInfo(estado: string): EstadoConfig {
    return estadoConfig[estado] ?? estadoConfig.inactivo;
}

export const tipoLabels: Record<string, string> = {
    tracto: 'Tracto',
    carreta: 'Carreta',
};

export const cajaLabels: Record<string, string> = {
    mecanica: 'Mecánica',
    automatica: 'Automática',
};
