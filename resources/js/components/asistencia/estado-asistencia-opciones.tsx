import {
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import { estadoConfig } from '@/lib/asistencia';
import { cn } from '@/lib/utils';
import type { AsistenciaMarca, EstadoAsistencia } from '@/types/fleet';

type Props = {
    marca: AsistenciaMarca | undefined;
    onSeleccionar: (estado: EstadoAsistencia) => void;
    onQuitar: () => void;
    align?: 'start' | 'center' | 'end';
};

/**
 * Contenido del dropdown para marcar un día: los 4 estados posibles y,
 * si ya hay una marca, la opción de quitarla. Compartido entre la celda
 * del rooster y la celda del calendario individual —el trigger visual de
 * cada una es distinto, pero las opciones son las mismas.
 */
export function EstadoAsistenciaOpciones({
    marca,
    onSeleccionar,
    onQuitar,
    align = 'start',
}: Props) {
    return (
        <DropdownMenuContent align={align}>
            {(Object.keys(estadoConfig) as EstadoAsistencia[]).map((estado) => (
                <DropdownMenuItem
                    key={estado}
                    onSelect={() => onSeleccionar(estado)}
                >
                    <span
                        className={cn(
                            'grid size-4 place-items-center rounded-none text-[10px] font-bold',
                            estadoConfig[estado].badge,
                        )}
                    >
                        {estadoConfig[estado].letra}
                    </span>
                    {estadoConfig[estado].label}
                </DropdownMenuItem>
            ))}
            {marca && (
                <>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem
                        onSelect={onQuitar}
                        className="text-muted-foreground"
                    >
                        Quitar marca
                    </DropdownMenuItem>
                </>
            )}
        </DropdownMenuContent>
    );
}
