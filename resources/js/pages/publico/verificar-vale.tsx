import { Head, useForm } from '@inertiajs/react';
import { BadgeCheck, CircleX, Search } from 'lucide-react';
import {
    buscar,
    formulario,
} from '@/actions/App/Http/Controllers/VerificarValeController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { formatearFecha, formatearFechaHora } from '@/lib/format';
import type { ValePublico } from '@/types/compras';

type Props = {
    /** Null si todavía no se buscó, o si se buscó y no existe. */
    vale: ValePublico | null;
    buscado: boolean;
};

/**
 * La página que abre el proveedor, sin cuenta: llega por el QR del vale o
 * escribiendo su código. Muestra el vale tal como está hoy en el sistema, y
 * bien grande si sirve o no para comprar.
 */
export default function VerificarVale({ vale, buscado }: Props) {
    return (
        <div className="min-h-screen bg-muted/40 px-4 py-8">
            <Head title="Verificar vale de compra" />

            <main className="mx-auto flex w-full max-w-2xl flex-col gap-5">
                <header className="text-center">
                    <p className="text-lg font-semibold">Transportes Paty</p>
                    <p className="text-sm text-muted-foreground">
                        Verificación de vales de compra
                    </p>
                </header>

                {vale ? (
                    <Vale vale={vale} />
                ) : (
                    <>
                        {buscado && (
                            <section className="flex flex-col items-center gap-2 rounded-xl border border-destructive/40 bg-destructive/5 p-6 text-center text-destructive">
                                <CircleX className="size-10" />
                                <h1 className="text-xl font-semibold">
                                    Este vale no existe
                                </h1>
                                <p className="text-sm">
                                    No hay ningún vale de Transportes Paty con
                                    ese código. No entregue mercadería con este
                                    documento.
                                </p>
                            </section>
                        )}
                        <Buscador />
                    </>
                )}

                <p className="text-center text-xs text-muted-foreground">
                    Lo que vale es lo que dice esta página, no el papel. Si algo
                    no coincide con el vale que le presentaron, no entregue.
                </p>
            </main>
        </div>
    );
}

function Buscador() {
    const { data, setData, post, processing, errors } = useForm({
        codigo: '',
    });

    return (
        <form
            onSubmit={(evento) => {
                evento.preventDefault();
                post(buscar().url);
            }}
            className="flex flex-col gap-3 rounded-xl border bg-card p-6"
        >
            <Label htmlFor="codigo-vale">
                Código de verificación impreso en el vale
            </Label>
            <Input
                id="codigo-vale"
                value={data.codigo}
                onChange={(e) => setData('codigo', e.target.value)}
                placeholder="XXXX-XXXX-XXXX"
                autoComplete="off"
                autoCapitalize="characters"
                className="h-12 text-center font-mono text-lg tracking-widest uppercase"
            />
            <InputError message={errors.codigo} />
            <Button type="submit" className="h-11" disabled={processing}>
                {processing ? <Spinner /> : <Search className="size-4" />}
                Verificar
            </Button>
        </form>
    );
}

function Vale({ vale }: { vale: ValePublico }) {
    const monto = (valor: number) =>
        `${vale.simbolo} ${valor.toLocaleString('es-PE', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        })}`;

    return (
        <>
            <section
                className={
                    vale.valido
                        ? 'flex flex-col items-center gap-2 rounded-xl border border-emerald-600/40 bg-emerald-600/10 p-6 text-center text-emerald-700 dark:text-emerald-400'
                        : 'flex flex-col items-center gap-2 rounded-xl border border-destructive/40 bg-destructive/5 p-6 text-center text-destructive'
                }
            >
                {vale.valido ? (
                    <BadgeCheck className="size-12" />
                ) : (
                    <CircleX className="size-12" />
                )}
                <h1 className="text-2xl font-semibold">
                    {vale.valido ? 'Vale válido' : 'No válido para comprar'}
                </h1>
                <p className="text-sm">{motivo(vale, monto)}</p>
            </section>

            <section className="flex flex-col gap-4 rounded-xl border bg-card p-6 text-sm">
                <div className="flex flex-wrap items-baseline justify-between gap-2">
                    <span className="text-lg font-semibold">{vale.numero}</span>
                    <span className="font-mono text-xs text-muted-foreground">
                        {vale.codigo_verificacion}
                    </span>
                </div>

                <dl className="grid gap-3 sm:grid-cols-2">
                    <Campo titulo="Proveedor autorizado">
                        {vale.proveedor}
                        {vale.proveedor_ruc && (
                            <span className="block text-xs text-muted-foreground">
                                RUC {vale.proveedor_ruc}
                            </span>
                        )}
                    </Campo>
                    <Campo titulo="Total a pagar">
                        <span className="text-lg font-semibold tabular-nums">
                            {monto(vale.total)}
                        </span>
                    </Campo>
                    <Campo titulo="Pidió">{vale.solicitado_por ?? '—'}</Campo>
                    <Campo titulo="Aprobó">
                        {vale.aprobado_por ?? 'Todavía nadie'}
                        {vale.aprobado_at && (
                            <span className="block text-xs text-muted-foreground">
                                {formatearFechaHora(vale.aprobado_at)}
                            </span>
                        )}
                    </Campo>
                    <Campo titulo="Estado">{vale.situacion_label}</Campo>
                    <Campo titulo="Vale hasta">
                        {vale.vence_el ? formatearFecha(vale.vence_el) : '—'}
                    </Campo>
                    {vale.placa && (
                        <Campo titulo="Para la unidad">{vale.placa}</Campo>
                    )}
                    <Campo titulo="Tipo">{vale.tipo_label}</Campo>
                </dl>

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-left text-sm">
                        <thead className="bg-muted/60 text-xs text-muted-foreground">
                            <tr>
                                <th className="px-3 py-2 font-medium">
                                    Descripción
                                </th>
                                <th className="px-3 py-2 text-right font-medium">
                                    Cantidad
                                </th>
                                <th className="px-3 py-2 text-right font-medium">
                                    Precio
                                </th>
                                <th className="px-3 py-2 text-right font-medium">
                                    Importe
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {vale.detalles.map((linea, indice) => (
                                <tr key={indice}>
                                    <td className="px-3 py-2">
                                        {linea.descripcion}
                                    </td>
                                    <td className="px-3 py-2 text-right whitespace-nowrap tabular-nums">
                                        {linea.cantidad.toLocaleString('es-PE')}{' '}
                                        {linea.unidad}
                                    </td>
                                    <td className="px-3 py-2 text-right whitespace-nowrap tabular-nums">
                                        {monto(linea.precio_unitario)}
                                    </td>
                                    <td className="px-3 py-2 text-right whitespace-nowrap tabular-nums">
                                        {monto(linea.total)}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </section>

            <Button variant="outline" asChild className="self-center">
                <a href={formulario().url}>Verificar otro vale</a>
            </Button>
        </>
    );
}

/** Por qué sirve o no, en una frase para quien está en el mostrador. */
function motivo(vale: ValePublico, monto: (valor: number) => string): string {
    if (vale.firma_valida === false) {
        return 'El vale fue modificado después de aprobarse: no coincide con lo que se firmó. No entregue.';
    }

    switch (vale.situacion) {
        case 'vigente':
            return `Sirve para una compra por ${monto(vale.total)}, hasta el ${formatearFecha(vale.vence_el)}.`;
        case 'parcial':
            return `Ya se compraron ${monto(vale.recibido)}. Quedan ${monto(vale.saldo)}, hasta el ${formatearFecha(vale.vence_el)}.`;
        case 'usado':
            return 'Este vale ya se usó: la compra ya está registrada. No sirve para otra.';
        case 'vencido':
            return `Venció el ${formatearFecha(vale.vence_el)}. Pida un vale nuevo.`;
        case 'anulado':
            return 'La empresa anuló este vale.';
        case 'rechazado':
            return 'La compra fue rechazada: este vale nunca se aprobó.';
        default:
            return 'Todavía no está aprobado: no sirve para comprar hasta que lo esté.';
    }
}

function Campo({
    titulo,
    children,
}: {
    titulo: string;
    children: React.ReactNode;
}) {
    return (
        <div className="min-w-0">
            <dt className="text-xs text-muted-foreground">{titulo}</dt>
            <dd className="mt-0.5 break-words">{children}</dd>
        </div>
    );
}
