import { Head, useForm } from '@inertiajs/react';
import {
    BadgeCheck,
    Banknote,
    CircleDollarSign,
    CircleX,
    Hourglass,
    PackageCheck,
    Search,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
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
 * La página que abre el QR del pedido, sin cuenta: es para la propia empresa
 * (gerencia, quien paga, quien recibe). Muestra el pedido tal como está hoy
 * en el sistema y, bien grande, en qué paso va: aprobar, pagar o recibir.
 */
export default function VerificarVale({ vale, buscado }: Props) {
    return (
        <div className="min-h-screen bg-muted/40 px-4 py-8">
            <Head title="Verificar pedido de compra" />

            <main className="mx-auto flex w-full max-w-2xl flex-col gap-5">
                <header className="text-center">
                    <p className="text-lg font-semibold">Transportes Paty</p>
                    <p className="text-sm text-muted-foreground">
                        Estado de un pedido de compra
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
                                    Este pedido no existe
                                </h1>
                                <p className="text-sm">
                                    No hay ningún pedido de Transportes Paty con
                                    ese código. Revisa que esté bien escrito.
                                </p>
                            </section>
                        )}
                        <Buscador />
                    </>
                )}

                <p className="text-center text-xs text-muted-foreground">
                    Uso interno de Transportes Paty. Lo que vale es lo que dice
                    esta página: es el estado del pedido en este momento.
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
                Código del pedido (está junto al QR)
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
                Ver el estado
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
            <Etapa vale={vale} monto={monto} />

            <section className="flex flex-col gap-4 rounded-xl border bg-card p-6 text-sm">
                <div className="flex flex-wrap items-baseline justify-between gap-2">
                    <span className="text-lg font-semibold">{vale.numero}</span>
                    <span className="font-mono text-xs text-muted-foreground">
                        {vale.codigo_verificacion}
                    </span>
                </div>

                <dl className="grid gap-3 sm:grid-cols-2">
                    <Campo titulo="Proveedor">
                        {vale.proveedor}
                        {vale.proveedor_ruc && (
                            <span className="block text-xs text-muted-foreground">
                                RUC {vale.proveedor_ruc}
                            </span>
                        )}
                    </Campo>
                    <Campo titulo="Total del pedido">
                        <span className="text-lg font-semibold tabular-nums">
                            {monto(vale.total)}
                        </span>
                        {vale.aprobado && (
                            <span className="block text-xs text-muted-foreground">
                                {vale.pagado_completo
                                    ? 'Pagado por completo'
                                    : vale.pagado > 0
                                      ? `Pagado ${monto(vale.pagado)} · falta ${monto(vale.por_pagar)}`
                                      : 'Todavía sin pagos anotados'}
                                {vale.ultimo_pago_el &&
                                    ` · último pago ${formatearFecha(vale.ultimo_pago_el)}`}
                            </span>
                        )}
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
                    <Campo titulo="Recibido en el almacén">
                        {vale.recibido > 0
                            ? `${monto(vale.recibido)} de ${monto(vale.total)}`
                            : 'Todavía nada'}
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
                <a href={formulario().url}>Ver otro pedido</a>
            </Button>
        </>
    );
}

const ETAPAS: Record<
    ValePublico['etapa'],
    {
        titulo: string;
        icono: LucideIcon;
        tono: 'verde' | 'azul' | 'ambar' | 'gris' | 'rojo';
    }
> = {
    por_aprobar: {
        titulo: 'Falta la aprobación de gerencia',
        icono: Hourglass,
        tono: 'gris',
    },
    por_pagar: {
        titulo: 'Aprobado: falta pagar',
        icono: Banknote,
        tono: 'ambar',
    },
    por_recibir: {
        titulo: 'Pagado: falta recibir',
        icono: CircleDollarSign,
        tono: 'azul',
    },
    recibido_por_pagar: {
        titulo: 'Recibido: falta pagar',
        icono: Banknote,
        tono: 'ambar',
    },
    completo: {
        titulo: 'Pagado y recibido',
        icono: PackageCheck,
        tono: 'verde',
    },
    modificado: {
        titulo: 'Fue modificado después de aprobarse',
        icono: CircleX,
        tono: 'rojo',
    },
    anulado: { titulo: 'Pedido anulado', icono: CircleX, tono: 'rojo' },
    rechazado: { titulo: 'Pedido rechazado', icono: CircleX, tono: 'rojo' },
};

const TONOS = {
    verde: 'border-emerald-600/40 bg-emerald-600/10 text-emerald-700 dark:text-emerald-400',
    azul: 'border-sky-600/40 bg-sky-600/10 text-sky-800 dark:text-sky-300',
    ambar: 'border-amber-600/40 bg-amber-500/10 text-amber-800 dark:text-amber-300',
    gris: 'border-border bg-card text-foreground',
    rojo: 'border-destructive/40 bg-destructive/5 text-destructive',
};

/** En qué paso va el pedido, bien grande, y qué falta. */
function Etapa({
    vale,
    monto,
}: {
    vale: ValePublico;
    monto: (valor: number) => string;
}) {
    const etapa = ETAPAS[vale.etapa];
    const Icono = vale.etapa === 'completo' ? BadgeCheck : etapa.icono;

    return (
        <section
            className={`flex flex-col items-center gap-2 rounded-xl border p-6 text-center ${TONOS[etapa.tono]}`}
        >
            <Icono className="size-12" />
            <h1 className="text-2xl font-semibold">{etapa.titulo}</h1>
            <p className="text-sm">{detalleDeEtapa(vale, monto)}</p>
        </section>
    );
}

function detalleDeEtapa(
    vale: ValePublico,
    monto: (valor: number) => string,
): string {
    const pagado =
        vale.pagado > 0
            ? `Se pagaron ${monto(vale.pagado)}${vale.ultimo_pago_el ? ` (último pago: ${formatearFecha(vale.ultimo_pago_el)})` : ''}`
            : '';

    switch (vale.etapa) {
        case 'por_aprobar':
            return `Pedido por ${monto(vale.total)}. Todavía no se puede pagar ni comprar.`;
        case 'por_pagar':
            return vale.pagado > 0
                ? `${pagado}. Faltan ${monto(vale.por_pagar)} de ${monto(vale.total)}.`
                : `Gerencia lo aprobó por ${monto(vale.total)}. Todavía no hay pagos anotados.`;
        case 'por_recibir':
            return `${pagado}. No corresponde pagar más: falta que el almacén reciba la mercadería.`;
        case 'recibido_por_pagar':
            return `La mercadería ya entró al almacén. Faltan pagar ${monto(vale.por_pagar)} de ${monto(vale.total)}.`;
        case 'completo':
            return `${pagado} y la mercadería ya entró al almacén. No queda nada pendiente.`;
        case 'modificado':
            return 'Lo que hay hoy en el sistema no coincide con lo que gerencia aprobó. Hay que corregirlo y volver a aprobar.';
        case 'anulado':
            return 'La empresa anuló este pedido.';
        default:
            return 'Gerencia rechazó esta compra.';
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
