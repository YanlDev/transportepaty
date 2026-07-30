import { Check, Copy } from 'lucide-react';
import { useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatearFecha } from '@/lib/format';
import type { FilaProgramacion } from '@/types/fleet';

type Props = {
    filas: FilaProgramacion[];
};

/**
 * La tabla tal como la espera Cargo Transport. El botón copia la selección
 * como HTML, que es lo que hace que al pegarla en el correo llegue como tabla
 * de verdad y no como texto suelto.
 */
export function TablaCargoTransport({ filas }: Props) {
    const tabla = useRef<HTMLTableElement>(null);
    const [copiado, setCopiado] = useState(false);

    const copiar = async () => {
        if (!tabla.current) {
            return;
        }

        const html = tabla.current.outerHTML;
        const texto = tabla.current.innerText;

        try {
            await navigator.clipboard.write([
                new ClipboardItem({
                    'text/html': new Blob([html], { type: 'text/html' }),
                    'text/plain': new Blob([texto], { type: 'text/plain' }),
                }),
            ]);
        } catch {
            // Navegadores sin permiso de portapapeles enriquecido: al menos el
            // texto plano llega.
            await navigator.clipboard.writeText(texto);
        }

        setCopiado(true);
        setTimeout(() => setCopiado(false), 2000);
    };

    return (
        <div className="flex flex-col gap-3">
            <div className="flex items-center justify-between gap-2">
                <p className="text-sm text-muted-foreground">
                    {filas.length} {filas.length === 1 ? 'unidad' : 'unidades'}{' '}
                    en la tabla
                </p>

                <Button
                    variant="outline"
                    size="sm"
                    onClick={copiar}
                    disabled={filas.length === 0}
                >
                    {copiado ? (
                        <Check className="size-4" />
                    ) : (
                        <Copy className="size-4" />
                    )}
                    {copiado ? 'Copiada' : 'Copiar para el correo'}
                </Button>
            </div>

            <div className="overflow-x-auto rounded-xl border">
                <Table ref={tabla}>
                    <TableHeader>
                        <TableRow>
                            <TableHead>N°</TableHead>
                            <TableHead>FECHA</TableHead>
                            <TableHead>HORA PROGRAMADA</TableHead>
                            <TableHead>EMPRESA</TableHead>
                            <TableHead>VEHÍCULO</TableHead>
                            <TableHead>CONDUCTOR</TableHead>
                            <TableHead>TIPO CARGA</TableHead>
                            <TableHead>ESTADO UNIDAD</TableHead>
                            <TableHead>OBSERVACIONES</TableHead>
                        </TableRow>
                    </TableHeader>

                    <TableBody>
                        {filas.map((fila) => (
                            <TableRow
                                key={`${fila.tracto_id}-${fila.numero ?? 't'}`}
                            >
                                <TableCell className="font-medium">
                                    {fila.numero ?? '—'}
                                </TableCell>
                                <TableCell className="whitespace-nowrap">
                                    {formatearFecha(fila.fecha)}
                                </TableCell>
                                <TableCell>{fila.hora ?? '—'}</TableCell>
                                <TableCell className="whitespace-nowrap">
                                    {fila.empresa}
                                </TableCell>
                                <TableCell className="font-medium whitespace-nowrap">
                                    {fila.vehiculo}
                                </TableCell>
                                <TableCell className="whitespace-nowrap">
                                    {fila.conductor ?? '—'}
                                </TableCell>
                                <TableCell>{fila.tipo_carga ?? '—'}</TableCell>
                                <TableCell>
                                    {fila.estado_unidad ?? '—'}
                                </TableCell>
                                <TableCell>
                                    {fila.observaciones ?? ''}
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </div>
        </div>
    );
}
