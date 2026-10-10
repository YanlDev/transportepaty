import { router } from '@inertiajs/react';
import { Upload } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';

/**
 * Cómo se llama lo que se sube, para los textos del botón y del cartel:
 * `{ boton: 'Subir GR', plural: 'las GR' }`.
 */
export type DocumentoPdf = {
    /** Lo que dice el botón: «Subir GR», «Subir facturas». */
    boton: string;
    /** Con artículo, para el cartel: «las GR», «las facturas». */
    plural: string;
};

/**
 * Manda los PDFs al servidor. Los comparten el botón y la zona de arrastre:
 * el archivo llega igual desde el explorador que soltándolo en la pantalla, y
 * el resto lo resuelve el servidor leyendo el documento.
 */
function subirPdfs(
    url: string,
    archivos: File[],
    alTerminar: () => void,
): void {
    const pdfs = archivos.filter(
        (archivo) => archivo.type === 'application/pdf',
    );

    if (pdfs.length === 0) {
        toast.error('Solo se pueden subir archivos PDF.');
        alTerminar();

        return;
    }

    if (pdfs.length < archivos.length) {
        const ignorados = archivos.length - pdfs.length;

        toast.warning(
            ignorados === 1
                ? 'Se ignoró un archivo que no era PDF.'
                : `Se ignoraron ${ignorados} archivos que no eran PDF.`,
        );
    }

    router.post(
        url,
        { archivos: pdfs },
        {
            forceFormData: true,
            preserveScroll: true,
            onFinish: alTerminar,
        },
    );
}

/**
 * Selector de archivos nativo, oculto tras el botón. Sube y sube de una: no
 * hace falta un diálogo con más campos porque todo sale del PDF.
 */
export function BotonSubirPdf({
    url,
    documento,
    variant = 'default',
}: {
    url: string;
    documento: DocumentoPdf;
    variant?: 'default' | 'outline';
}) {
    const fileInput = useRef<HTMLInputElement>(null);
    const [subiendo, setSubiendo] = useState(false);

    const seleccionar = (evento: React.ChangeEvent<HTMLInputElement>) => {
        const archivos = Array.from(evento.target.files ?? []);

        if (archivos.length === 0) {
            return;
        }

        setSubiendo(true);

        subirPdfs(url, archivos, () => {
            setSubiendo(false);

            if (fileInput.current) {
                fileInput.current.value = '';
            }
        });
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
                variant={variant}
                onClick={() => fileInput.current?.click()}
                disabled={subiendo}
            >
                <Upload className="size-4" />
                {subiendo ? 'Subiendo...' : documento.boton}
            </Button>
        </>
    );
}

/**
 * Soltar los PDFs en cualquier parte de la pantalla para subirlos.
 *
 * Escucha en toda la ventana y no en un recuadro: quien llega arrastrando un
 * archivo desde la carpeta no debería tener que apuntarle a una caja chica.
 * El cartel aparece recién cuando lo que se arrastra son archivos, así que
 * seleccionar texto o arrastrar un enlace no lo dispara.
 *
 * En el teléfono no aparece nunca —no hay de dónde arrastrar—, y por eso el
 * botón sigue siendo la vía principal.
 */
export function ZonaSoltarPdf({
    url,
    documento,
}: {
    url: string;
    documento: DocumentoPdf;
}) {
    const [encima, setEncima] = useState(false);
    const [subiendo, setSubiendo] = useState(false);
    // Los eventos de arrastre se disparan también al pasar por encima de cada
    // hijo: sin contar entradas y salidas, el cartel parpadea al recorrer la
    // tabla.
    const profundidad = useRef(0);

    useEffect(() => {
        const traeArchivos = (evento: DragEvent): boolean =>
            Array.from(evento.dataTransfer?.types ?? []).includes('Files');

        const alEntrar = (evento: DragEvent) => {
            if (!traeArchivos(evento)) {
                return;
            }

            profundidad.current += 1;
            setEncima(true);
        };

        const alSalir = () => {
            profundidad.current = Math.max(0, profundidad.current - 1);

            if (profundidad.current === 0) {
                setEncima(false);
            }
        };

        // Sin esto el navegador abre el PDF en una pestaña en vez de dejarlo
        // soltar.
        const alArrastrar = (evento: DragEvent) => {
            if (traeArchivos(evento)) {
                evento.preventDefault();
            }
        };

        const alSoltar = (evento: DragEvent) => {
            if (!traeArchivos(evento)) {
                return;
            }

            evento.preventDefault();
            profundidad.current = 0;
            setEncima(false);

            const archivos = Array.from(evento.dataTransfer?.files ?? []);

            if (archivos.length === 0) {
                return;
            }

            setSubiendo(true);
            subirPdfs(url, archivos, () => setSubiendo(false));
        };

        window.addEventListener('dragenter', alEntrar);
        window.addEventListener('dragleave', alSalir);
        window.addEventListener('dragover', alArrastrar);
        window.addEventListener('drop', alSoltar);

        return () => {
            window.removeEventListener('dragenter', alEntrar);
            window.removeEventListener('dragleave', alSalir);
            window.removeEventListener('dragover', alArrastrar);
            window.removeEventListener('drop', alSoltar);
        };
    }, [url]);

    if (!encima && !subiendo) {
        return null;
    }

    return (
        <div className="pointer-events-none fixed inset-0 z-50 flex items-center justify-center bg-background/80 p-6 backdrop-blur-sm">
            <div className="flex flex-col items-center gap-3 rounded-2xl border-2 border-dashed border-primary px-10 py-8 text-center">
                <Upload
                    className={`size-8 text-primary ${subiendo ? 'animate-pulse' : ''}`}
                />
                <p className="text-lg font-semibold">
                    {subiendo
                        ? `Subiendo ${documento.plural}...`
                        : `Suelta ${documento.plural} aquí`}
                </p>
                <p className="text-sm text-muted-foreground">
                    {subiendo
                        ? 'Se están leyendo los PDF.'
                        : 'Solo PDF. Puedes soltar varias a la vez.'}
                </p>
            </div>
        </div>
    );
}
