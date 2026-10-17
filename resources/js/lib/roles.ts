/**
 * El nombre con que se muestra cada rol base. Los roles creados a mano desde
 * el panel se muestran como se escribieron.
 */
const NOMBRES_ROL: Record<string, string> = {
    admin: 'Administrador',
    gerencia: 'Gerencia',
    operaciones: 'Operaciones',
    rrhh: 'RRHH',
    contador: 'Contador',
    visor: 'Visor',
};

export function nombreRol(rol: string): string {
    return NOMBRES_ROL[rol] ?? rol;
}
