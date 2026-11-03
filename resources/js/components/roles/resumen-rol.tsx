import { Link } from '@inertiajs/react';
import { Users } from 'lucide-react';
import usuarios from '@/actions/App/Http/Controllers/UserController';
import { descripcionRol } from '@/lib/roles';
import type { Permiso } from '@/types';

/**
 * Las entradas del menú lateral y el permiso que las muestra, en el mismo
 * orden y por área. Si cambia el menú, se cambia acá también.
 */
const MENU: { area: string; entradas: { titulo: string; con: Permiso[] }[] }[] =
    [
        {
            area: 'Inicio',
            entradas: [{ titulo: 'Inicio', con: ['tablero.ver'] }],
        },
        {
            area: 'Operación',
            entradas: [
                { titulo: 'Programación', con: ['programacion.ver'] },
                { titulo: 'Taller', con: ['taller.ver'] },
                {
                    titulo: 'Abastecimiento',
                    con: ['abastecimiento.ver', 'abastecimiento.viaticos'],
                },
                { titulo: 'Guías (GR)', con: ['viajes.ver'] },
            ],
        },
        {
            area: 'Flota',
            entradas: [
                { titulo: 'Unidades', con: ['vehiculos.ver'] },
                { titulo: 'Mantenimiento', con: ['mantenimiento.ver'] },
                { titulo: 'Conductores', con: ['conductores.ver'] },
                { titulo: 'Asistencia', con: ['asistencia.ver'] },
            ],
        },
        {
            area: 'Comercial',
            entradas: [
                {
                    titulo: 'Cotizaciones',
                    con: [
                        'cotizaciones.ver',
                        'cotizaciones.crear',
                        'costos.editar',
                    ],
                },
                { titulo: 'Clientes', con: ['clientes.ver'] },
            ],
        },
        {
            area: 'Finanzas',
            entradas: [{ titulo: 'Contabilidad', con: ['cobranza.ver'] }],
        },
    ];

/**
 * Lo que hace falta para entender un rol sin leer casillas: qué hace, quién
 * lo tiene y cómo ve el menú con los permisos marcados (se actualiza al
 * marcar o desmarcar).
 */
export function ResumenRol({
    nombre,
    permisos,
    miembros,
    todo,
}: {
    nombre: string;
    permisos: Permiso[];
    miembros: { id: number; nombre: string; usuario: string | null }[];
    /** El administrador ve todo, tenga lo que tenga marcado. */
    todo: boolean;
}) {
    const descripcion = descripcionRol(nombre);
    const ve = (con: Permiso[]) =>
        todo || con.some((permiso) => permisos.includes(permiso));
    const menu = MENU.map((grupo) => ({
        ...grupo,
        entradas: grupo.entradas.filter((entrada) => ve(entrada.con)),
    })).filter((grupo) => grupo.entradas.length > 0);

    return (
        <section className="grid gap-4 rounded-xl border border-border bg-card p-5 md:grid-cols-2">
            <div className="flex flex-col gap-3">
                {descripcion && <p className="text-sm">{descripcion}</p>}
                <div className="flex flex-col gap-1.5">
                    <span className="flex items-center gap-1.5 text-xs font-medium text-muted-foreground">
                        <Users className="size-3.5" />
                        Quiénes lo tienen
                    </span>
                    {miembros.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            Nadie todavía. Se asigna desde Usuarios.
                        </p>
                    ) : (
                        <div className="flex flex-wrap gap-1.5">
                            {miembros.map((miembro) => (
                                <Link
                                    key={miembro.id}
                                    href={usuarios.edit(miembro.id).url}
                                    className="rounded-full border px-2.5 py-0.5 text-xs hover:border-foreground/30 hover:bg-accent/50"
                                >
                                    {miembro.nombre}
                                    {miembro.usuario && (
                                        <span className="text-muted-foreground">
                                            {' '}
                                            · {miembro.usuario}
                                        </span>
                                    )}
                                </Link>
                            ))}
                        </div>
                    )}
                </div>
            </div>

            <div className="flex flex-col gap-1.5">
                <span className="text-xs font-medium text-muted-foreground">
                    Así ve el menú
                </span>
                {menu.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        Solo Configuración (su cuenta): no tiene ninguna
                        pantalla.
                    </p>
                ) : (
                    <ul className="flex flex-col gap-1.5 text-sm">
                        {menu.map((grupo) => (
                            <li key={grupo.area} className="flex gap-2">
                                <span className="w-24 shrink-0 text-xs text-muted-foreground">
                                    {grupo.area}
                                </span>
                                <span className="flex flex-wrap gap-1">
                                    {grupo.entradas.map((entrada) => (
                                        <span
                                            key={entrada.titulo}
                                            className="rounded-md bg-primary/10 px-2 py-0.5 text-xs font-medium text-primary"
                                        >
                                            {entrada.titulo}
                                        </span>
                                    ))}
                                </span>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </section>
    );
}
