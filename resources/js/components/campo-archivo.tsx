import { Camera, FileText, ImagePlus, Paperclip, X } from 'lucide-react';
import { useEffect, useRef } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useSubidaArchivos } from '@/hooks/use-subida-archivos';
import type { TipoAceptado } from '@/hooks/use-subida-archivos';
import { formatearPeso, pesoOriginal } from '@/lib/comprimir-imagen';
import type { PerfilDeImagen } from '@/lib/comprimir-imagen';

const ACCEPT: Record<TipoAceptado, string> = {
    imagen: 'image/*',
    pdf: 'application/pdf',
    'imagen-pdf': 'application/pdf,image/*',
};

type Props = {
    /** Los archivos elegidos, todavía sin subir: viajan con el formulario. */
    archivos: File[];
    onArchivos: (archivos: File[]) => void;
    acepta?: TipoAceptado;
    /** Varios archivos (una galería) en vez de uno solo. */
    multiple?: boolean;
    /** Botón «Tomar foto», que en el celular abre la cámara de atrás. */
    camara?: boolean;
    perfil?: PerfilDeImagen;
    maximoKb?: number;
    /** Porcentaje de la subida en curso (`progress?.percentage` de useForm). */
    progreso?: number | null;
    error?: string;
    deshabilitado?: boolean;
    /** Avisa mientras se comprime, para bloquear el botón de enviar. */
    onPreparando?: (preparando: boolean) => void;
};

/**
 * El campo de archivo de todo el sistema: fotos, PDF o ambos, uno o varios.
 *
 * Las fotos se achican en el navegador antes de sumarse al formulario (un
 * PDF va tal cual). Muestra la vista previa, cuánto pesaba y cuánto pesa
 * ahora, el avance de la subida y el error de validación del servidor.
 */
export function CampoArchivo({
    archivos,
    onArchivos,
    acepta = 'imagen-pdf',
    multiple = false,
    camara = acepta !== 'pdf',
    perfil = 'foto',
    maximoKb,
    progreso = null,
    error,
    deshabilitado = false,
    onPreparando,
}: Props) {
    const entradaCamara = useRef<HTMLInputElement>(null);
    const entradaSelector = useRef<HTMLInputElement>(null);
    const { preparando, aviso, vistas, preparar } = useSubidaArchivos({
        archivos,
        acepta,
        perfil,
        maximoKb,
    });
    const subiendo = progreso !== null;
    const bloqueado = deshabilitado || preparando || subiendo;

    useEffect(() => {
        onPreparando?.(preparando);
        // Solo interesa el cambio de estado, no la identidad del callback.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [preparando]);

    const elegir = async (lista: FileList | null) => {
        const elegidos = Array.from(lista ?? []);

        // Se vacían para que elegir otra vez el mismo archivo vuelva a avisar.
        for (const entrada of [
            entradaCamara.current,
            entradaSelector.current,
        ]) {
            if (entrada) {
                entrada.value = '';
            }
        }

        if (elegidos.length === 0) {
            return;
        }

        const listos = await preparar(multiple ? elegidos : [elegidos[0]]);

        if (listos.length > 0) {
            onArchivos(multiple ? [...archivos, ...listos] : listos);
        }
    };

    const quitar = (indice: number) =>
        onArchivos(archivos.filter((_, i) => i !== indice));

    const textoElegir =
        acepta === 'pdf'
            ? 'Elegir PDF'
            : acepta === 'imagen'
              ? 'Galería'
              : 'Subir PDF o foto';

    return (
        <div className="grid gap-2">
            <input
                ref={entradaCamara}
                type="file"
                accept="image/*"
                capture="environment"
                className="hidden"
                onChange={(evento) => elegir(evento.target.files)}
            />
            <input
                ref={entradaSelector}
                type="file"
                accept={ACCEPT[acepta]}
                multiple={multiple}
                className="hidden"
                onChange={(evento) => elegir(evento.target.files)}
            />

            {archivos.length > 0 && (
                <ul className="grid gap-2">
                    {archivos.map((archivo, indice) => (
                        <ArchivoElegido
                            key={`${archivo.name}-${archivo.lastModified}-${indice}`}
                            archivo={archivo}
                            vista={vistas[indice] ?? null}
                            onQuitar={() => quitar(indice)}
                            bloqueado={subiendo || deshabilitado}
                        />
                    ))}
                </ul>
            )}

            {(multiple || archivos.length === 0) && (
                <div
                    className={camara ? 'grid grid-cols-2 gap-2' : 'grid gap-2'}
                >
                    {camara && (
                        <Button
                            type="button"
                            variant="outline"
                            className="h-12"
                            onClick={() => entradaCamara.current?.click()}
                            disabled={bloqueado}
                        >
                            {preparando ? (
                                <Spinner />
                            ) : (
                                <Camera className="size-5" />
                            )}
                            Tomar foto
                        </Button>
                    )}
                    <Button
                        type="button"
                        variant="outline"
                        className="h-12"
                        onClick={() => entradaSelector.current?.click()}
                        disabled={bloqueado}
                    >
                        {preparando && !camara ? (
                            <Spinner />
                        ) : acepta === 'imagen' ? (
                            <ImagePlus className="size-5" />
                        ) : (
                            <Paperclip className="size-5" />
                        )}
                        {textoElegir}
                    </Button>
                </div>
            )}

            {subiendo && (
                <div className="grid gap-1" aria-live="polite">
                    <div
                        className="h-1.5 overflow-hidden rounded-full bg-muted"
                        role="progressbar"
                        aria-valuenow={Math.round(progreso)}
                        aria-valuemin={0}
                        aria-valuemax={100}
                    >
                        <div
                            className="h-full rounded-full bg-primary transition-[width]"
                            style={{ width: `${progreso}%` }}
                        />
                    </div>
                    <span className="text-xs text-muted-foreground">
                        Subiendo… {Math.round(progreso)} %
                    </span>
                </div>
            )}

            <InputError message={aviso ?? error} />
        </div>
    );
}

function ArchivoElegido({
    archivo,
    vista,
    onQuitar,
    bloqueado,
}: {
    archivo: File;
    vista: string | null;
    onQuitar: () => void;
    bloqueado: boolean;
}) {
    const antes = pesoOriginal(archivo);

    return (
        <li className="flex items-center gap-3 rounded-lg border bg-muted/30 p-2">
            {vista ? (
                <a
                    href={vista}
                    target="_blank"
                    rel="noreferrer"
                    className="shrink-0"
                >
                    <img
                        src={vista}
                        alt="Vista previa"
                        className="size-16 rounded-md object-cover"
                    />
                </a>
            ) : (
                <span className="flex size-16 shrink-0 items-center justify-center rounded-md bg-background">
                    <FileText className="size-7 text-muted-foreground" />
                </span>
            )}
            <div className="min-w-0 flex-1 text-sm">
                <p className="truncate font-medium">{archivo.name}</p>
                <p className="text-xs text-muted-foreground tabular-nums">
                    {antes > archivo.size
                        ? `${formatearPeso(antes)} → ${formatearPeso(archivo.size)}`
                        : formatearPeso(archivo.size)}
                </p>
            </div>
            <Button
                type="button"
                variant="ghost"
                size="icon"
                aria-label="Quitar el archivo"
                onClick={onQuitar}
                disabled={bloqueado}
            >
                <X className="size-4" />
            </Button>
        </li>
    );
}
