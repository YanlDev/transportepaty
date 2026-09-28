export type User = {
    id: number;
    name: string;
    username: string;
    email: string | null;
    avatar?: string;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

/**
 * Espejo de `App\Enums\Permiso`: al agregar uno allá, agregarlo acá.
 */
export type Permiso =
    | 'tablero.ver'
    | 'vehiculos.ver'
    | 'vehiculos.crear'
    | 'vehiculos.editar'
    | 'vehiculos.eliminar'
    | 'conductores.ver'
    | 'conductores.crear'
    | 'conductores.editar'
    | 'conductores.eliminar'
    | 'novedades.registrar'
    | 'novedades.levantar'
    | 'viajes.ver'
    | 'viajes.registrar'
    | 'viajes.emitir'
    | 'viajes.editar'
    | 'viajes.anular'
    | 'viajes.eliminar'
    | 'programacion.ver'
    | 'programacion.crear'
    | 'programacion.editar'
    | 'programacion.eliminar'
    | 'programacion.avisar'
    | 'asistencia.ver'
    | 'asistencia.marcar'
    | 'asistencia.ajustar'
    | 'clientes.ver'
    | 'clientes.crear'
    | 'clientes.editar'
    | 'clientes.eliminar'
    | 'cotizaciones.ver'
    | 'cotizaciones.crear'
    | 'cotizaciones.editar'
    | 'cotizaciones.eliminar'
    | 'costos.editar'
    | 'cobranza.ver'
    | 'cobranza.gestionar'
    | 'cuentas.ver'
    | 'cuentas.gestionar'
    | 'whatsapp.administrar';

/** Un bloque de la matriz de permisos: un módulo con sus acciones. */
export type ModuloPermisos = {
    clave: string;
    nombre: string;
    permisos: { value: Permiso; label: string }[];
};

/** Un rol con lo que habilita, como lo listan el panel y el alta de usuarios. */
export type RolConPermisos = {
    name: string;
    permisos: Permiso[];
};

export type Auth = {
    user: User;
    roles: string[];
    permisos: Permiso[];
};

/* @chisel-passkeys */
export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};
/* @end-chisel-passkeys */

export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};
