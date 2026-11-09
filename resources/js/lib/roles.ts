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
    contador: 'Contabilidad',
    almacen: 'Almacén',
};

export function nombreRol(rol: string): string {
    return NOMBRES_ROL[rol] ?? rol;
}

/** Qué hace cada rol base, en una frase. */
const DESCRIPCIONES_ROL: Record<string, string> = {
    admin: 'Tiene todo y reparte los permisos. Es el único que maneja usuarios y roles.',
    gerencia:
        'Ve todo el sistema y aprueba las excepciones (combustible por encima del tope).',
    operaciones:
        'Programa los tickets, sube y asigna las GR, registra la llegada y avisa.',
    abastecimiento:
        'Autoriza el combustible, registra los vouchers, deposita viáticos y liquida los tickets.',
    taller: 'Da el OK de salida y lleva el mantenimiento de la flota.',
    rrhh: 'Maneja el padrón de conductores y la asistencia.',
    contador:
        'Factura, registra cobros, corrige viáticos y liquida los tickets.',
    almacen:
        'Lleva el almacén del taller: artículos, proveedores, compras, vales de salida, conteos y kardex.',
};

export function descripcionRol(rol: string): string | null {
    return DESCRIPCIONES_ROL[rol] ?? null;
}
