import { Head, useForm } from '@inertiajs/react';
import { Upload } from 'lucide-react';
import { useRef } from 'react';
import importaciones from '@/actions/App/Http/Controllers/ImportacionDisponibilidadController';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

/**
 * Subir el Excel de disponibilidad de un día. La fecha es opcional: si no se
 * indica, se intenta sacar del nombre del archivo y si tampoco se puede, cae en
 * el día de hoy.
 */
export default function ImportacionesCreate() {
    const entrada = useRef<HTMLInputElement>(null);

    const { data, setData, submit, processing, errors } = useForm<{
        archivo: File | null;
        fecha: string;
    }>({ archivo: null, fecha: '' });

    const enviar = (evento: React.FormEvent) => {
        evento.preventDefault();

        submit(importaciones.store(), {
            forceFormData: true,
        });
    };

    return (
        <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
            <Head title="Importar disponibilidad" />

            <div>
                <h1 className="text-2xl font-semibold tracking-tight">
                    Importar disponibilidad
                </h1>
                <p className="text-sm text-muted-foreground">
                    Sube el Excel del día. Vas a poder revisar cada fila antes
                    de que se aplique.
                </p>
            </div>

            <form
                onSubmit={enviar}
                className="grid max-w-lg gap-4 rounded-xl border p-6"
            >
                <div className="grid gap-2">
                    <Label htmlFor="archivo">Archivo Excel (.xlsx)</Label>
                    <input
                        ref={entrada}
                        id="archivo"
                        type="file"
                        accept=".xlsx"
                        className="text-sm file:mr-3 file:rounded-md file:border file:bg-background file:px-3 file:py-1.5 file:text-sm"
                        onChange={(evento) =>
                            setData('archivo', evento.target.files?.[0] ?? null)
                        }
                    />
                    {errors.archivo && (
                        <p className="text-sm text-destructive">
                            {errors.archivo}
                        </p>
                    )}
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="fecha">
                        Fecha del reporte (opcional)
                    </Label>
                    <Input
                        id="fecha"
                        type="date"
                        value={data.fecha}
                        onChange={(evento) =>
                            setData('fecha', evento.target.value)
                        }
                    />
                    <p className="text-xs text-muted-foreground">
                        Si la dejas en blanco, se toma del nombre del archivo o
                        del día de hoy.
                    </p>
                </div>

                <Button
                    type="submit"
                    disabled={processing || !data.archivo}
                    className="mt-2"
                >
                    <Upload className="size-4" />
                    Subir y previsualizar
                </Button>
            </form>
        </div>
    );
}

ImportacionesCreate.layout = {
    breadcrumbs: [
        { title: 'Importaciones', href: importaciones.index().url },
        { title: 'Nueva', href: importaciones.create().url },
    ],
};
