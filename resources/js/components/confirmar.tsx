import {
    createContext,
    useCallback,
    useContext,
    useRef,
    useState,
} from 'react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialogo-responsivo';

type Pregunta = {
    titulo: string;
    /** Qué pasa si se acepta, y si se puede deshacer. */
    descripcion?: ReactNode;
    /** El verbo del botón: «Quitar», «Cerrar ticket». */
    accion?: string;
    /** Rojo para lo que quita o no tiene vuelta; azul para el resto. */
    tono?: 'peligro' | 'normal';
};

type Confirmar = (pregunta: Pregunta) => Promise<boolean>;

const Contexto = createContext<Confirmar | null>(null);

/**
 * La única pregunta de «¿seguro?» de la app, para lo que se decide en el acto
 * (quitar una foto, cerrar un ticket). Reemplaza al `window.confirm` del
 * navegador, que no respeta el tema, corta el texto en el celular y no dice
 * qué hace cada botón.
 *
 * Se usa como promesa, igual de corto que el nativo:
 *
 *     if (await confirmar({ titulo: '¿Quitar Mina?', accion: 'Quitar' })) { … }
 *
 * Para borrar un registro con su ruta `destroy` sigue estando
 * `ConfirmarBorradoDialog`, que además espera al servidor.
 */
export function ConfirmarProvider({ children }: { children: ReactNode }) {
    const [pregunta, setPregunta] = useState<Pregunta | null>(null);
    const [abierto, setAbierto] = useState(false);
    const resolver = useRef<((respuesta: boolean) => void) | null>(null);

    const confirmar = useCallback<Confirmar>((nueva) => {
        // Si quedaba una sin responder, cuenta como «no».
        resolver.current?.(false);
        setPregunta(nueva);
        setAbierto(true);

        return new Promise<boolean>((resolve) => {
            resolver.current = resolve;
        });
    }, []);

    const responder = (respuesta: boolean) => {
        resolver.current?.(respuesta);
        resolver.current = null;
        setAbierto(false);
    };

    return (
        <Contexto.Provider value={confirmar}>
            {children}
            <Dialog
                open={abierto}
                onOpenChange={(abrir) => {
                    if (!abrir) {
                        responder(false);
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{pregunta?.titulo}</DialogTitle>
                        {pregunta?.descripcion && (
                            <DialogDescription>
                                {pregunta.descripcion}
                            </DialogDescription>
                        )}
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            type="button"
                            onClick={() => responder(false)}
                        >
                            Cancelar
                        </Button>
                        <Button
                            type="button"
                            variant={
                                pregunta?.tono === 'normal'
                                    ? 'default'
                                    : 'destructive'
                            }
                            onClick={() => responder(true)}
                        >
                            {pregunta?.accion ?? 'Sí, seguir'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </Contexto.Provider>
    );
}

export function useConfirmar(): Confirmar {
    const confirmar = useContext(Contexto);

    if (!confirmar) {
        throw new Error('useConfirmar se usa dentro de ConfirmarProvider.');
    }

    return confirmar;
}
