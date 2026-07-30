import { Search, SlidersHorizontal, X } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { useIsMobile } from '@/hooks/use-mobile';

type Props = {
    buscar: string;
    onBuscar: (valor: string) => void;
    placeholder: string;
    etiquetaBusqueda: string;
    /** Cuántos filtros hay puestos, sin contar la búsqueda. */
    activos: number;
    /** Deja todos los filtros en su valor por defecto. */
    onLimpiar: () => void;
    /** Los selectores; se montan una sola vez y cambian de sitio según el ancho. */
    children: React.ReactNode;
};

/**
 * Búsqueda siempre visible y filtros que se apartan en móvil.
 *
 * En pantalla chica, cuatro selectores apilados empujaban la tabla fuera de la
 * vista: aquí se esconden tras un botón que abre un panel y solo queda una fila.
 * En escritorio van en línea, compactos, porque el espacio sobra.
 *
 * Los selectores se montan una única vez y se reubican con `useIsMobile` en
 * lugar de renderizarse dos veces ocultando uno con CSS: duplicarlos duplicaría
 * también su estado y sus identificadores.
 */
export function FiltrosBarra({
    buscar,
    onBuscar,
    placeholder,
    etiquetaBusqueda,
    activos,
    onLimpiar,
    children,
}: Props) {
    const esMovil = useIsMobile();
    const [abierto, setAbierto] = useState(false);

    const busqueda = (
        <div className="relative flex-1">
            <Search className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
            <Input
                value={buscar}
                onChange={(evento) => onBuscar(evento.target.value)}
                placeholder={placeholder}
                aria-label={etiquetaBusqueda}
                className="h-9 pl-8"
            />
        </div>
    );

    if (!esMovil) {
        return (
            <div className="flex flex-wrap items-center gap-2">
                {busqueda}
                {children}
                {activos > 0 && (
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={onLimpiar}
                        className="h-9 text-muted-foreground"
                    >
                        <X className="size-4" />
                        Limpiar
                    </Button>
                )}
            </div>
        );
    }

    return (
        <div className="flex items-center gap-2">
            {busqueda}

            <Sheet open={abierto} onOpenChange={setAbierto}>
                <SheetTrigger asChild>
                    <Button
                        variant="outline"
                        className="h-9 shrink-0 px-3"
                        aria-label={
                            activos > 0
                                ? `Filtros, ${activos} aplicados`
                                : 'Filtros'
                        }
                    >
                        <SlidersHorizontal className="size-4" />
                        {activos > 0 && (
                            <span className="grid size-5 place-items-center bg-navy-800 text-[11px] font-bold text-white">
                                {activos}
                            </span>
                        )}
                    </Button>
                </SheetTrigger>

                <SheetContent side="bottom" className="max-h-[85vh] gap-0">
                    <SheetHeader className="pb-2">
                        <SheetTitle>Filtros</SheetTitle>
                        <SheetDescription className="sr-only">
                            Acota el listado por sus atributos.
                        </SheetDescription>
                    </SheetHeader>

                    <div className="flex flex-col gap-3 overflow-y-auto px-4 pb-4">
                        {children}
                    </div>

                    <div className="flex gap-2 border-t p-4">
                        <Button
                            variant="outline"
                            className="flex-1"
                            onClick={() => {
                                onLimpiar();
                                setAbierto(false);
                            }}
                            disabled={activos === 0}
                        >
                            Limpiar
                        </Button>
                        <Button
                            className="flex-1"
                            onClick={() => setAbierto(false)}
                        >
                            Ver resultados
                        </Button>
                    </div>
                </SheetContent>
            </Sheet>
        </div>
    );
}
