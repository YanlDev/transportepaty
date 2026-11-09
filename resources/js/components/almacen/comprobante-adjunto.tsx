import { Camera, FileText, Paperclip, X } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { comprimirImagen } from '@/lib/comprimir-imagen';

/** Para leer un comprobante hace falta más detalle que para la foto de una pieza. */
const LADO_MAXIMO = 2200;
const CALIDAD = 0.85;

/**
 * Adjuntar la foto o el PDF del comprobante. En el celular, «Tomar foto»
 * abre directo la cámara de atrás. Las fotos se achican antes de subirse
 * (pasan de 4-8 MB a menos de 1 MB) para que suban rápido con datos y no
 * choquen con el límite del servidor; un PDF va tal cual.
 *
 * Muestra la foto elegida o el nombre del PDF, y avisa antes de enviar si el
 * archivo pesa más de lo permitido.
 */
export function ComprobanteAdjunto({
    archivo,
    onCambio,
    maximoKb,
    error,
}: {
    archivo: File | null;
    onCambio: (archivo: File | null) => void;
    maximoKb: number;
    error?: string;
}) {
    const camara = useRef<HTMLInputElement>(null);
    const selector = useRef<HTMLInputElement>(null);
    const [preparando, setPreparando] = useState(false);
    const [aviso, setAviso] = useState<string | null>(null);
    const vista = useMemo(
        () =>
            archivo !== null && archivo.type.startsWith('image/')
                ? URL.createObjectURL(archivo)
                : null,
        [archivo],
    );

    useEffect(
        () => () => {
            if (vista) {
                URL.revokeObjectURL(vista);
            }
        },
        [vista],
    );

    const elegir = async (lista: FileList | null) => {
        const elegido = lista?.[0];

        for (const entrada of [camara.current, selector.current]) {
            if (entrada) {
                entrada.value = '';
            }
        }

        if (!elegido) {
            return;
        }

        setPreparando(true);
        const listo = await comprimirImagen(elegido, LADO_MAXIMO, CALIDAD);
        setPreparando(false);

        if (listo.size > maximoKb * 1024) {
            setAviso(
                `Pesa ${(listo.size / 1024 / 1024).toFixed(1)} MB y el máximo es ${Math.round(maximoKb / 1024)} MB. Si es un PDF escaneado, guárdalo en menor calidad; si es una foto, tómala con «Tomar foto».`,
            );
            onCambio(null);

            return;
        }

        setAviso(null);
        onCambio(listo);
    };

    return (
        <div className="grid gap-2">
            <input
                ref={camara}
                type="file"
                accept="image/*"
                capture="environment"
                className="hidden"
                onChange={(e) => elegir(e.target.files)}
            />
            <input
                ref={selector}
                type="file"
                accept="application/pdf,image/jpeg,image/png,image/webp,image/*"
                className="hidden"
                onChange={(e) => elegir(e.target.files)}
            />

            {archivo ? (
                <div className="flex items-center gap-3 rounded-lg border bg-muted/30 p-2">
                    {vista ? (
                        <img
                            src={vista}
                            alt="Comprobante"
                            className="size-16 rounded-md object-cover"
                        />
                    ) : (
                        <span className="flex size-16 items-center justify-center rounded-md bg-background">
                            <FileText className="size-7 text-muted-foreground" />
                        </span>
                    )}
                    <div className="min-w-0 flex-1 text-sm">
                        <p className="truncate font-medium">{archivo.name}</p>
                        <p className="text-xs text-muted-foreground">
                            {archivo.size < 1024 * 1024
                                ? `${Math.ceil(archivo.size / 1024)} KB`
                                : `${(archivo.size / 1024 / 1024).toFixed(1)} MB`}
                        </p>
                    </div>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        aria-label="Quitar el comprobante"
                        onClick={() => onCambio(null)}
                    >
                        <X className="size-4" />
                    </Button>
                </div>
            ) : (
                <div className="grid grid-cols-2 gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        className="h-12"
                        onClick={() => camara.current?.click()}
                        disabled={preparando}
                    >
                        {preparando ? (
                            <Spinner />
                        ) : (
                            <Camera className="size-5" />
                        )}
                        Tomar foto
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        className="h-12"
                        onClick={() => selector.current?.click()}
                        disabled={preparando}
                    >
                        <Paperclip className="size-5" />
                        Subir PDF o foto
                    </Button>
                </div>
            )}

            <InputError message={aviso ?? error} />
        </div>
    );
}
