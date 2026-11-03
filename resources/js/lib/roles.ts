/**
 * El nombre con que se muestra cada rol base. Los roles creados a mano desde
 * el panel se muestran como se escribieron.
 */
const NOMBRES_ROL: Record<string, string> = {
    admin: 'Administrador',
    gerencia: 'Gerencia',
    operaciones: 'Operaciones',
    abastecimiento: 'Abastecimiento',
    taller: 'Taller',
    rrhh: 'RRHH',
    contador: 'Contador',
    visor: 'Visor',
};

export function nombreRol(rol: string): string {
    return NOMBRES_ROL[rol] ?? rol;
}

/** Qué hace cada rol base, en una frase. */
const DESCRIPCIONES_ROL: Record<string, string> = {
    admin: 'Tiene todo y reparte los permisos. Es el único que maneja usuarios y roles.',
    gerencia: 'Ve todo el sistema y no modifica nada.',
    operaciones: 'Programa los tickets, define las vueltas y sube las GR.',
    abastecimiento:
        'Registra el combustible y los viáticos de cada ticket y mantiene la pizarra.',
    taller: 'Da el apto o no apto de salida a cada ticket.',
    rrhh: 'Maneja el padrón de conductores y la asistencia.',
    contador: 'Factura, registra cobros y corrige viáticos.',
    visor: 'Solo mira: no cambia nada.',
};

export function descripcionRol(rol: string): string | null {
    return DESCRIPCIONES_ROL[rol] ?? null;
}
