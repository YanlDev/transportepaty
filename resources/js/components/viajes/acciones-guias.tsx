import { router } from '@inertiajs/react';
import { RefreshCw } from 'lucide-react';
import { useState } from 'react';
import {
    resolver,
    store,
} from '@/actions/App/Http/Controllers/ViajeController';
import { BotonSubirPdf, ZonaSoltarPdf } from '@/components/subida-pdf';
import type { DocumentoPdf } from '@/components/subida-pdf';
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

const GUIAS: DocumentoPdf = { boton: 'Subir GR', plural: 'las GR' };

/** El botón para subir GR desde el explorador de archivos. */
export function SubirGuias() {
    return <BotonSubirPdf url={store().url} documento={GUIAS} />;
}

/** Soltar las GR en cualquier parte de la pantalla de viajes. */
export function ZonaSoltarGuias() {
    return <ZonaSoltarPdf url={store().url} documento={GUIAS} />;
}
