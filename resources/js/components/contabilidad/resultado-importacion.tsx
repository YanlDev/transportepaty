import { router } from '@inertiajs/react';
import { CircleAlert, CircleCheck, FileX } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialogo-responsivo';
import type { ResultadoImportacionFactura } from '@/types/contabilidad';

/**
 * Lo que pasó con cada factura subida. Un toast no alcanza: lo importante es
 * por qué una GR no se pudo asociar sola, y eso hay que poder leerlo con
 * calma antes de ir a la bandeja a resolverlo.
 *
 * Se abre solo cuando el servidor devuelve el resultado (`flash`), sin
 * importar si la subida vino del botón o de soltar los archivos.
 */
export function ResultadoImportacion() {
    const [resultados, setResultados] = useState<
        ResultadoImportacionFactura[] | null
    >(null);

    useEffect(
        () =>
            router.on('flash', (evento) => {
                const flash = (evento as CustomEvent).detail?.flash;
                const importacion = flash?.importacion_facturas as
                    | ResultadoImportacionFactura[]
                    | undefined;

                if (importacion) {
                    setResultados(importacion);
                }
            }),
        [],
    );

    const conAlertas =
        resultados?.filter((resultado) => resultado.alertas.length > 0)
            .length ?? 0;

    return (
        <Dialog
            open={resultados !== null}
            onOpenChange={(abierto) => !abierto && setResultados(null)}
        >
            <DialogContent className="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Facturas subidas</DialogTitle>
                    <DialogDescription>
                        {conAlertas === 0
                            ? 'Todas se registraron y se asociaron a sus GR.'
                            : 'Las que tienen alertas quedaron en «Facturas por asociar» para resolverlas a mano.'}
                    </DialogDescription>
                </DialogHeader>

                <ul className="flex max-h-[60vh] flex-col gap-3 overflow-y-auto py-2">
                    {resultados?.map((resultado, indice) => (
                        <ItemResultado
                            key={`${resultado.archivo}-${indice}`}
                            resultado={resultado}
                        />
                    ))}
                </ul>

                <DialogFooter>
                    <DialogClose asChild>
                        <Button>Entendido</Button>
                    </DialogClose>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function ItemResultado({
    resultado,
}: {
    resultado: ResultadoImportacionFactura;
}) {
    const Icono = !resultado.reconocida
        ? FileX
        : resultado.alertas.length > 0
          ? CircleAlert
          : CircleCheck;

    const color = !resultado.reconocida
        ? 'text-red-600 dark:text-red-400'
        : resultado.alertas.length > 0
          ? 'text-amber-600 dark:text-amber-400'
          : 'text-emerald-600 dark:text-emerald-400';

    return (
        <li className="flex gap-3 rounded-lg border p-3">
            <Icono className={`mt-0.5 size-5 shrink-0 ${color}`} />
            <div className="flex min-w-0 flex-col gap-1 text-sm">
                <p className="font-medium">
                    {resultado.numero ? (
                        <>
                            <span className="font-mono">
                                {resultado.numero}
                            </span>
                            <span className="ml-2 text-xs font-normal text-muted-foreground">
                                {resultado.nueva
                                    ? 'nueva'
                                    : 'ya existía, se actualizó con el PDF'}
                            </span>
                        </>
                    ) : (
                        <span className="break-all">{resultado.archivo}</span>
                    )}
                </p>

                {resultado.asociadas.length > 0 && (
                    <p className="text-muted-foreground">
                        Asociada a{' '}
                        <span className="font-mono text-foreground">
                            {resultado.asociadas.join(', ')}
                        </span>
                    </p>
                )}

                {resultado.alertas.map((alerta) => (
                    <p key={alerta} className={color}>
                        {alerta}
                    </p>
                ))}
            </div>
        </li>
    );
}
