import { router } from '@inertiajs/react';
import { RefreshCw, Upload } from 'lucide-react';
import { useRef, useState } from 'react';
import {
    resolver,
    store,
} from '@/actions/App/Http/Controllers/ViajeController';
import { Button } from '@/components/ui/button';

/**
 * Vuelve a intentar resolver tracto/carreta/conductor contra el padrón de
 * hoy. Existe porque la GR suele subirse antes de que la unidad o el
 * conductor estén cargados: crearlos después no actualiza solo lo ya
 * importado, hay que pedirlo.
 */
export function ReintentarCoincidencias({
    pendientes,
}: {
    pendientes: number;
}) {
    const [procesando, setProcesando] = useState(false);

    const reintentar = () => {
        setProcesando(true);

        router.post(
            resolver().url,
            {},
            {
                preserveScroll: true,
                onFinish: () => setProcesando(false),
            },
        );
    };

    return (
        <Button variant="outline" onClick={reintentar} disabled={procesando}>
            <RefreshCw
                className={`size-4 ${procesando ? 'animate-spin' : ''}`}
            />
            Reintentar coincidencias
            <span className="ml-1 inline-flex min-w-5 items-center justify-center rounded-full bg-amber-500 px-1.5 text-xs font-semibold text-white">
                {pendientes}
            </span>
        </Button>
    );
}

/**
 * Selector de archivos nativo, oculto tras el botón. Sube y sube de una: no
 * hace falta un diálogo con más campos porque todo sale del PDF.
 */
export function SubirGuias() {
    const fileInput = useRef<HTMLInputElement>(null);
    const [subiendo, setSubiendo] = useState(false);

    const seleccionar = (evento: React.ChangeEvent<HTMLInputElement>) => {
        const archivos = Array.from(evento.target.files ?? []);

        if (archivos.length === 0) {
            return;
        }

        setSubiendo(true);

        router.post(
            store().url,
            { archivos },
            {
                forceFormData: true,
                preserveScroll: true,
                onFinish: () => {
                    setSubiendo(false);

                    if (fileInput.current) {
                        fileInput.current.value = '';
                    }
                },
            },
        );
    };

    return (
        <>
            <input
                ref={fileInput}
                type="file"
                accept="application/pdf"
                multiple
                className="hidden"
                onChange={seleccionar}
            />
            <Button
                onClick={() => fileInput.current?.click()}
                disabled={subiendo}
            >
                <Upload className="size-4" />
                {subiendo ? 'Subiendo...' : 'Subir GR'}
            </Button>
        </>
    );
}
