import * as React from 'react';
import { cn } from '@/lib/utils';

function Table({ className, ...props }: React.ComponentProps<'table'>) {
    return (
        <div
            data-slot="table-container"
            className="relative w-full overflow-x-auto"
        >
            <table
                data-slot="table"
                className={cn('w-full caption-bottom text-sm', className)}
                {...props}
            />
        </div>
    );
}

function TableHeader({ className, ...props }: React.ComponentProps<'thead'>) {
    return (
        <thead
            data-slot="table-header"
            className={cn('[&_tr]:border-b', className)}
            {...props}
        />
    );
}

function TableBody({ className, ...props }: React.ComponentProps<'tbody'>) {
    return (
        <tbody
            data-slot="table-body"
            className={cn('[&_tr:last-child]:border-0', className)}
            {...props}
        />
    );
}

/**
 * Elementos que ya manejan su propio foco (botones, links, selects, celdas
 * editables). Si el click viene de ahí, la fila no debe robarles el foco:
 * pasaría, por ejemplo, con el input de una celda editable a medio escribir.
 */
function esElementoInteractivo(elemento: HTMLElement): boolean {
    return (
        elemento.closest(
            'button, a, input, select, textarea, [role="menuitem"], [contenteditable="true"]',
        ) !== null
    );
}

function TableRow({
    className,
    onClick,
    ...props
}: React.ComponentProps<'tr'>) {
    return (
        <tr
            data-slot="table-row"
            tabIndex={-1}
            onClick={(evento) => {
                if (
                    !evento.currentTarget.closest('thead') &&
                    !esElementoInteractivo(evento.target as HTMLElement)
                ) {
                    evento.currentTarget.focus();
                }

                onClick?.(evento);
            }}
            className={cn(
                'border-b outline-none transition-colors hover:bg-muted/50 focus:bg-navy-100 data-[state=selected]:bg-muted dark:focus:bg-navy-900/50',
                className,
            )}
            {...props}
        />
    );
}

function TableHead({ className, ...props }: React.ComponentProps<'th'>) {
    return (
        <th
            data-slot="table-head"
            className={cn(
                'h-8 px-2 text-left align-middle text-xs font-medium whitespace-nowrap text-muted-foreground',
                className,
            )}
            {...props}
        />
    );
}

function TableCell({ className, ...props }: React.ComponentProps<'td'>) {
    return (
        <td
            data-slot="table-cell"
            className={cn(
                'px-2 py-1.5 align-middle text-xs whitespace-nowrap',
                className,
            )}
            {...props}
        />
    );
}

export { Table, TableBody, TableCell, TableHead, TableHeader, TableRow };
