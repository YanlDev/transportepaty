import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import type { Paginator } from '@/types/fleet';

/**
 * Los enlaces de página que arma el paginador de Laravel. No se muestra nada
 * cuando hay una sola página: el rango «1–20 de 20» no le dice nada a nadie.
 *
 * Las etiquetas llegan del backend con entidades HTML (`&laquo;`), por eso el
 * `dangerouslySetInnerHTML`: son literales de Laravel, no entrada del usuario.
 */
export function Paginacion<T>({ paginador }: { paginador: Paginator<T> }) {
    if (paginador.last_page <= 1) {
        return null;
    }

    return (
        <div className="mt-auto flex flex-wrap items-center justify-between gap-3 pt-2">
            <p className="text-sm text-muted-foreground">
                Mostrando {paginador.from}–{paginador.to} de {paginador.total}
            </p>
            <div className="flex flex-wrap gap-1">
                {paginador.links.map((link, indice) => (
                    <Button
                        key={indice}
                        asChild={!!link.url}
                        size="sm"
                        variant={link.active ? 'default' : 'outline'}
                        disabled={!link.url}
                    >
                        {link.url ? (
                            <Link
                                href={link.url}
                                preserveScroll
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ) : (
                            <span
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        )}
                    </Button>
                ))}
            </div>
        </div>
    );
}
