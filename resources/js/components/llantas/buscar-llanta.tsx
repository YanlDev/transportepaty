import { router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useState } from 'react';
import { buscar } from '@/actions/App/Http/Controllers/LlantaAlmacenController';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

/** Buscar una llanta por el código con que la marcaron: va a su ficha. */
export function BuscarLlanta({ inicial = '' }: { inicial?: string }) {
    const [codigo, setCodigo] = useState(inicial);

    return (
        <form
            className="flex gap-2"
            onSubmit={(evento) => {
                evento.preventDefault();

                if (codigo.trim() !== '') {
                    router.get(buscar().url, { codigo: codigo.trim() });
                }
            }}
        >
            <div className="relative flex-1 sm:w-64">
                <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                    placeholder="Código de la llanta"
                    aria-label="Buscar una llanta por su código"
                    value={codigo}
                    onChange={(e) => setCodigo(e.target.value)}
                    className="pl-9"
                />
            </div>
            <Button type="submit" variant="outline">
                Buscar
            </Button>
        </form>
    );
}
