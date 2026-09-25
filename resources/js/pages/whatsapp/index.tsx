import { Head, router, useForm, usePoll } from '@inertiajs/react';
import { WhatsappLogo } from '@phosphor-icons/react';
import { useEffect } from 'react';
import whatsapp from '@/actions/App/Http/Controllers/WhatsappController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';

type EstadoWhatsapp = {
    estado: 'sin_servicio' | 'desconectado' | 'vinculando' | 'conectado';
    /** Imagen del QR (data URL) mientras se espera que lo escaneen. */
    qr: string | null;
    numero: string | null;
};

type Props = {
    estado: EstadoWhatsapp;
    /** El código de 8 caracteres, si se pidió vincular por número. */
    codigoVinculacion: string | null;
};

const ETIQUETAS: Record<
    EstadoWhatsapp['estado'],
    { texto: string; punto: string }
> = {
    conectado: { texto: 'Conectado', punto: 'bg-emerald-500' },
    vinculando: {
        texto: 'Esperando vinculación',
        punto: 'bg-amber-500 animate-pulse',
    },
    desconectado: { texto: 'Sin vincular', punto: 'bg-muted-foreground' },
    sin_servicio: { texto: 'Servicio no disponible', punto: 'bg-destructive' },
};

/**
 * El número de WhatsApp de la empresa, vinculado como un dispositivo más
 * (igual que WhatsApp Web). Desde acá se vincula, se prueba y se desvincula;
 * los avisos de verdad salen desde cada módulo.
 */
export default function WhatsappIndex({ estado, codigoVinculacion }: Props) {
    // Mientras se espera que escaneen el QR o escriban el código, la página
    // se refresca sola para mostrar el QR nuevo y enterarse de cuándo quedó.
    const { start, stop } = usePoll(
        3000,
        { only: ['estado', 'codigoVinculacion'] },
        { autoStart: false },
    );

    useEffect(() => {
        if (estado.estado === 'vinculando') {
            start();
        } else {
            stop();
        }
    }, [estado.estado, start, stop]);

    const etiqueta = ETIQUETAS[estado.estado];

    return (
        <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
            <Head title="WhatsApp" />

            <section className="flex max-w-2xl flex-col gap-5 rounded-xl border bg-card p-5">
                <div className="flex items-center justify-between gap-3">
                    <div className="flex items-center gap-3">
                        <WhatsappLogo
                            weight="fill"
                            className="size-8 text-emerald-600"
                        />
                        <div>
                            <h2 className="font-semibold">
                                Número de la empresa
                            </h2>
                            <p className="font-mono text-sm text-muted-foreground">
                                {estado.numero ? `+${estado.numero}` : '—'}
                            </p>
                        </div>
                    </div>
                    <span className="flex items-center gap-2 text-sm">
                        <span
                            className={cn(
                                'size-2 rounded-full',
                                etiqueta.punto,
                            )}
                        />
                        {etiqueta.texto}
                    </span>
                </div>

                {estado.estado === 'sin_servicio' && (
                    <p className="text-sm text-muted-foreground">
                        El servicio de WhatsApp no está corriendo en el
                        servidor. Mientras tanto, los botones de aviso siguen
                        abriendo WhatsApp como siempre.
                    </p>
                )}

                {estado.estado === 'desconectado' && <Vincular />}

                {estado.estado === 'vinculando' && (
                    <Vinculando qr={estado.qr} codigo={codigoVinculacion} />
                )}

                {estado.estado === 'conectado' && <Conectado />}
            </section>
        </div>
    );
}

function Vincular() {
    const { data, setData, post, processing, errors } = useForm({
        telefono: '',
    });

    return (
        <div className="flex flex-col gap-4">
            <p className="text-sm text-muted-foreground">
                Usa un chip dedicado, no el número principal. Se vincula como un
                dispositivo más: el celular sigue funcionando normal.
            </p>

            <div>
                <Button
                    disabled={processing}
                    onClick={() =>
                        router.post(
                            whatsapp.vincular().url,
                            {},
                            { preserveScroll: true },
                        )
                    }
                >
                    Vincular con QR
                </Button>
            </div>

            <form
                className="flex flex-col gap-1.5"
                onSubmit={(evento) => {
                    evento.preventDefault();
                    post(whatsapp.vincular().url, { preserveScroll: true });
                }}
            >
                <Label htmlFor="telefono">
                    O con código, si el QR no se puede escanear
                </Label>
                <div className="flex gap-2">
                    <Input
                        id="telefono"
                        value={data.telefono}
                        onChange={(evento) =>
                            setData('telefono', evento.target.value)
                        }
                        placeholder="Número a vincular, ej. 950301881"
                        inputMode="tel"
                        className="max-w-60"
                    />
                    <Button
                        type="submit"
                        variant="outline"
                        disabled={processing || !data.telefono}
                    >
                        {processing && <Spinner />}
                        Pedir código
                    </Button>
                </div>
                <InputError message={errors.telefono} />
            </form>
        </div>
    );
}

function Vinculando({
    qr,
    codigo,
}: {
    qr: string | null;
    codigo: string | null;
}) {
    return (
        <div className="flex flex-col gap-4 sm:flex-row sm:items-start">
            {codigo ? (
                <p className="rounded-lg border bg-muted px-4 py-3 text-center font-mono text-2xl font-bold tracking-[0.3em]">
                    {codigo.slice(0, 4)}-{codigo.slice(4)}
                </p>
            ) : qr ? (
                <img
                    src={qr}
                    alt="Código QR para vincular WhatsApp"
                    className="size-56 rounded-lg border bg-white p-2"
                />
            ) : (
                <div className="grid size-56 animate-pulse place-items-center rounded-lg border bg-muted text-sm text-muted-foreground">
                    Generando QR…
                </div>
            )}

            <ol className="list-decimal space-y-1 pl-5 text-sm text-muted-foreground">
                <li>Abre WhatsApp en el celular del chip de la empresa.</li>
                <li>
                    Ve a <b>Dispositivos vinculados</b> →{' '}
                    <b>Vincular un dispositivo</b>.
                </li>
                {codigo ? (
                    <li>
                        Elige <b>Vincular con el número de teléfono</b> y
                        escribe el código.
                    </li>
                ) : (
                    <li>Escanea el QR. Se renueva solo cada ~20 segundos.</li>
                )}
                <li>Esta página se actualiza sola cuando quede vinculado.</li>
            </ol>
        </div>
    );
}

function Conectado() {
    const { data, setData, post, processing, errors } = useForm({
        numero: '',
    });

    const desvincular = () => {
        if (
            window.confirm(
                '¿Desvincular el número? Los avisos dejarán de salir solos hasta volver a vincularlo.',
            )
        ) {
            router.post(
                whatsapp.desvincular().url,
                {},
                { preserveScroll: true },
            );
        }
    };

    return (
        <div className="flex flex-col gap-4">
            <form
                className="flex flex-col gap-1.5"
                onSubmit={(evento) => {
                    evento.preventDefault();
                    post(whatsapp.probar().url, { preserveScroll: true });
                }}
            >
                <Label htmlFor="numero">Mandar un mensaje de prueba a</Label>
                <div className="flex gap-2">
                    <Input
                        id="numero"
                        value={data.numero}
                        onChange={(evento) =>
                            setData('numero', evento.target.value)
                        }
                        placeholder="Celular, ej. 987654321"
                        inputMode="tel"
                        className="max-w-60"
                    />
                    <Button type="submit" disabled={processing || !data.numero}>
                        {processing && <Spinner />}
                        Enviar prueba
                    </Button>
                </div>
                <InputError message={errors.numero} />
            </form>

            <div>
                <Button
                    variant="ghost"
                    size="sm"
                    className="text-muted-foreground hover:text-destructive"
                    onClick={desvincular}
                >
                    Desvincular número
                </Button>
            </div>
        </div>
    );
}

WhatsappIndex.layout = {
    breadcrumbs: [{ title: 'WhatsApp', href: whatsapp.index().url }],
};
