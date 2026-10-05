import * as DialogPrimitive from '@radix-ui/react-dialog';
import { XIcon } from 'lucide-react';
import * as React from 'react';

import { cn } from '@/lib/utils';

/**
 * El diálogo de la app: centrado en escritorio y hoja que sube desde abajo en
 * el celular, con las acciones pegadas al borde inferior, donde llega el
 * pulgar. Misma API que `ui/dialog` —se migra cambiando solo el import—.
 *
 * El cambio es puro CSS (variantes `max-md:`/`md:`), no un `useIsMobile` que
 * elija entre Dialog y Sheet: Sheet también es un Dialog de Radix por dentro,
 * así que basta con mover el mismo contenido. Sin JS no hay parpadeo al
 * hidratar ni un componente que se desmonta al girar la tablet.
 *
 * `movil="pantalla"` lo abre a pantalla completa en el celular: para lo que
 * necesita todo el alto, como el visor de PDF.
 */
function Dialog(props: React.ComponentProps<typeof DialogPrimitive.Root>) {
    return <DialogPrimitive.Root data-slot="dialog" {...props} />;
}

function DialogTrigger(
    props: React.ComponentProps<typeof DialogPrimitive.Trigger>,
) {
    return <DialogPrimitive.Trigger data-slot="dialog-trigger" {...props} />;
}

function DialogClose(props: React.ComponentProps<typeof DialogPrimitive.Close>) {
    return <DialogPrimitive.Close data-slot="dialog-close" {...props} />;
}

function DialogContent({
    className,
    children,
    showCloseButton = true,
    movil = 'hoja',
    ...props
}: React.ComponentProps<typeof DialogPrimitive.Content> & {
    /** Ocúltalo cuando el diálogo ponga su propio botón de cerrar. */
    showCloseButton?: boolean;
    /** En el celular: hoja inferior (por defecto) o pantalla completa. */
    movil?: 'hoja' | 'pantalla';
}) {
    return (
        <DialogPrimitive.Portal>
            <DialogPrimitive.Overlay className="fixed inset-0 z-50 bg-black/60 data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:animate-in data-[state=open]:fade-in-0" />
            <DialogPrimitive.Content
                data-slot="dialog-content"
                data-movil={movil}
                className={cn(
                    // La columna es minmax(0,1fr) y no auto: con auto crece
                    // hasta el ancho del texto más largo (un cliente con
                    // nombre kilométrico) y ningún `truncate` llega a cortar.
                    'group/dialogo fixed z-50 grid w-full grid-cols-[minmax(0,1fr)] gap-4 overflow-y-auto overscroll-contain bg-background shadow-lg duration-200 data-[state=closed]:animate-out data-[state=open]:animate-in',
                    // Celular: hoja pegada abajo, a lo ancho, con el alto
                    // dinámico (dvh) para no quedar tapada por la barra del
                    // navegador. Anula los max-w que traiga cada diálogo.
                    'max-md:inset-x-0 max-md:bottom-0 max-md:max-h-[92dvh] max-md:max-w-none! max-md:rounded-t-2xl max-md:border-t max-md:p-5 max-md:pb-[max(1.25rem,env(safe-area-inset-bottom))] max-md:data-[state=closed]:slide-out-to-bottom max-md:data-[state=open]:slide-in-from-bottom',
                    'max-md:data-[movil=pantalla]:inset-0 max-md:data-[movil=pantalla]:h-dvh max-md:data-[movil=pantalla]:max-h-none max-md:data-[movil=pantalla]:rounded-none max-md:data-[movil=pantalla]:border-0',
                    // Tablet y escritorio: el diálogo centrado de siempre.
                    'md:top-1/2 md:left-1/2 md:max-h-[90dvh] md:max-w-lg md:-translate-x-1/2 md:-translate-y-1/2 md:rounded-lg md:border md:p-6 md:data-[state=closed]:fade-out-0 md:data-[state=closed]:zoom-out-95 md:data-[state=open]:fade-in-0 md:data-[state=open]:zoom-in-95',
                    className,
                )}
                {...props}
            >
                {movil === 'hoja' && (
                    // La manija: dice «esto se desliza» sin palabras.
                    <div
                        aria-hidden
                        className="-mt-2 mx-auto h-1.5 w-10 rounded-full bg-muted md:hidden"
                    />
                )}
                {children}
                {showCloseButton && (
                    <DialogPrimitive.Close className="absolute top-3 right-3 grid size-9 place-items-center rounded-md text-muted-foreground transition-colors hover:bg-accent hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-hidden disabled:pointer-events-none pointer-coarse:size-11 md:top-4 md:right-4 md:size-7">
                        <XIcon className="size-4" />
                        <span className="sr-only">Cerrar</span>
                    </DialogPrimitive.Close>
                )}
            </DialogPrimitive.Content>
        </DialogPrimitive.Portal>
    );
}

function DialogHeader({ className, ...props }: React.ComponentProps<'div'>) {
    return (
        <div
            data-slot="dialog-header"
            className={cn('flex flex-col gap-1.5 pr-10 text-left', className)}
            {...props}
        />
    );
}

/**
 * Las acciones. En el celular quedan fijas al pie de la hoja, a lo ancho y
 * con la principal abajo del todo —la más cerca del pulgar—; en escritorio,
 * alineadas a la derecha como siempre.
 */
function DialogFooter({ className, ...props }: React.ComponentProps<'div'>) {
    return (
        <div
            data-slot="dialog-footer"
            className={cn(
                'flex flex-col-reverse gap-2 md:flex-row md:justify-end',
                'max-md:sticky max-md:-bottom-[max(1.25rem,env(safe-area-inset-bottom))] max-md:-mx-5 max-md:-mb-[max(1.25rem,env(safe-area-inset-bottom))] max-md:border-t max-md:bg-background max-md:px-5 max-md:pt-3 max-md:pb-[max(0.75rem,env(safe-area-inset-bottom))] max-md:*:w-full',
                className,
            )}
            {...props}
        />
    );
}

function DialogTitle({
    className,
    ...props
}: React.ComponentProps<typeof DialogPrimitive.Title>) {
    return (
        <DialogPrimitive.Title
            data-slot="dialog-title"
            className={cn('text-lg leading-tight font-semibold', className)}
            {...props}
        />
    );
}

function DialogDescription({
    className,
    ...props
}: React.ComponentProps<typeof DialogPrimitive.Description>) {
    return (
        <DialogPrimitive.Description
            data-slot="dialog-description"
            className={cn('text-sm text-muted-foreground', className)}
            {...props}
        />
    );
}

export {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
};
