import { Checkbox } from '@/components/ui/checkbox';
import { cn } from '@/lib/utils';
import type { ModuloPermisos, Permiso } from '@/types';

type Props = {
    modulos: ModuloPermisos[];
    seleccionados: Permiso[];
    onChange: (permisos: Permiso[]) => void;
    /** Los que ya vienen dados por el rol: se ven marcados y no se tocan. */
    heredados?: Permiso[];
    /** Todo de solo lectura, como el rol administrador. */
    bloqueado?: boolean;
    className?: string;
};

/**
 * La matriz de casillas: un bloque por módulo, una casilla por acción.
 *
 * Marcar cualquier acción de un módulo marca también su «ver», y quitar el
 * «ver» quita el resto: sin poder entrar a la pantalla, lo demás es una
 * casilla marcada que no habilita nada.
 */
export function MatrizPermisos({
    modulos,
    seleccionados,
    onChange,
    heredados = [],
    bloqueado = false,
    className,
}: Props) {
    const activos = new Set<Permiso>([...seleccionados, ...heredados]);

    const verDe = (modulo: ModuloPermisos): Permiso | undefined =>
        modulo.permisos.find((p) => p.value.endsWith('.ver'))?.value;

    const alternar = (
        modulo: ModuloPermisos,
        permiso: Permiso,
        marcar: boolean,
    ) => {
        const nuevos = new Set(seleccionados);
        const ver = verDe(modulo);

        if (marcar) {
            nuevos.add(permiso);

            if (ver && !heredados.includes(ver)) {
                nuevos.add(ver);
            }
        } else if (permiso === ver) {
            modulo.permisos.forEach((p) => nuevos.delete(p.value));
        } else {
            nuevos.delete(permiso);
        }

        onChange([...nuevos]);
    };

    const alternarModulo = (modulo: ModuloPermisos, marcar: boolean) => {
        const nuevos = new Set(seleccionados);

        modulo.permisos.forEach((p) => {
            if (marcar && !heredados.includes(p.value)) {
                nuevos.add(p.value);
            } else if (!marcar) {
                nuevos.delete(p.value);
            }
        });

        onChange([...nuevos]);
    };

    return (
        <div
            className={cn(
                'grid gap-3 sm:grid-cols-2 xl:grid-cols-3',
                className,
            )}
        >
            {modulos.map((modulo) => {
                const marcados = modulo.permisos.filter((p) =>
                    activos.has(p.value),
                ).length;
                const total = modulo.permisos.length;
                const estadoModulo =
                    marcados === 0
                        ? false
                        : marcados === total
                          ? true
                          : 'indeterminate';
                const idModulo = `modulo-${modulo.clave}`;

                return (
                    <fieldset
                        key={modulo.clave}
                        className={cn(
                            'rounded-xl border bg-card p-4 transition-colors',
                            marcados > 0
                                ? 'border-marca-600/30'
                                : 'border-border',
                        )}
                    >
                        <legend className="sr-only">{modulo.nombre}</legend>
                        <div className="mb-3 flex items-center gap-2.5 border-b border-border pb-3">
                            <Checkbox
                                id={idModulo}
                                checked={estadoModulo}
                                disabled={bloqueado}
                                onCheckedChange={(valor) =>
                                    alternarModulo(modulo, valor === true)
                                }
                            />
                            <label
                                htmlFor={idModulo}
                                className="flex-1 cursor-pointer text-sm font-semibold text-foreground"
                            >
                                {modulo.nombre}
                            </label>
                            <span
                                className={cn(
                                    'rounded-full px-2 py-0.5 text-xs tabular-nums',
                                    marcados > 0
                                        ? 'bg-marca-50 text-marca-700 dark:bg-marca-950 dark:text-marca-300'
                                        : 'bg-muted text-muted-foreground',
                                )}
                            >
                                {marcados}/{total}
                            </span>
                        </div>

                        <ul className="flex flex-col gap-2">
                            {modulo.permisos.map((permiso) => {
                                const heredado = heredados.includes(
                                    permiso.value,
                                );
                                const id = `permiso-${permiso.value}`;

                                return (
                                    <li
                                        key={permiso.value}
                                        className="flex items-center gap-2.5"
                                    >
                                        <Checkbox
                                            id={id}
                                            checked={activos.has(permiso.value)}
                                            disabled={bloqueado || heredado}
                                            onCheckedChange={(valor) =>
                                                alternar(
                                                    modulo,
                                                    permiso.value,
                                                    valor === true,
                                                )
                                            }
                                        />
                                        <label
                                            htmlFor={id}
                                            className={cn(
                                                'flex-1 text-sm',
                                                bloqueado || heredado
                                                    ? 'text-muted-foreground'
                                                    : 'cursor-pointer text-foreground',
                                            )}
                                        >
                                            {permiso.label}
                                        </label>
                                        {heredado && (
                                            <span className="text-[11px] text-muted-foreground">
                                                del rol
                                            </span>
                                        )}
                                    </li>
                                );
                            })}
                        </ul>
                    </fieldset>
                );
            })}
        </div>
    );
}
