import { Head, Link, router, setLayoutProps, useForm } from '@inertiajs/react';
import { BellRing, MessageCircle, Send, TriangleAlert, X } from 'lucide-react';
import { useId } from 'react';
import {
    index,
    probar,
    update,
} from '@/actions/App/Http/Controllers/ConfiguracionAlertaController';
import configuracion from '@/actions/App/Http/Controllers/ConfiguracionController';
import whatsapp from '@/actions/App/Http/Controllers/WhatsappController';
import { EncabezadoPantalla } from '@/components/encabezado-pantalla';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { EstadoEnvio } from '@/components/whatsapp/estado-envio';
import { formatearFechaHora } from '@/lib/format';
import { nombreRol } from '@/lib/roles';
import { cn } from '@/lib/utils';
import type {
    AreaAlerta,
    ConfiguracionTipoAlerta,
    EnvioAlerta,
    UsuarioAlerta,
} from '@/types/alertas';

type Props = {
    tipos: ConfiguracionTipoAlerta[];
    roles: string[];
    usuarios: UsuarioAlerta[];
    areas: AreaAlerta[];
    whatsappConectado: boolean;
    envios: EnvioAlerta[];
};

/**
 * Qué alertas salen, por dónde y a quién. Cada tipo se guarda por separado:
 * prendido o no, por la campanita de la app y/o por WhatsApp, a qué roles,
 * a qué usuarios sueltos y a qué números de área.
 */
export default function ConfiguracionAlertas({
    tipos,
    roles,
    usuarios,
    areas,
    whatsappConectado,
    envios,
}: Props) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Configuración', href: configuracion.index().url },
            { title: 'Alertas', href: index().url },
        ],
    });

    const usanWhatsapp = tipos.some((tipo) => tipo.activa && tipo.por_whatsapp);
    const sinCelular = usuarios.filter((usuario) => !usuario.con_celular);

    return (
        <div className="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6 p-4 md:p-6">
            <Head title="Alertas" />

            <EncabezadoPantalla
                titulo="Alertas"
                descripcion="Qué avisa el sistema, por dónde y a quién. Por la app llega a la campanita; por WhatsApp, al celular de cada usuario o al número de un área."
                icono={BellRing}
            />

            {usanWhatsapp && !whatsappConectado && (
                <Aviso>
                    El número de WhatsApp de la empresa no está conectado: las
                    alertas por WhatsApp no van a salir hasta que se vincule.{' '}
                    <Link
                        href={whatsapp.index()}
                        className="font-medium underline"
                    >
                        Ir a WhatsApp
                    </Link>
                </Aviso>
            )}

            {usanWhatsapp && sinCelular.length > 0 && (
                <p className="text-xs text-muted-foreground">
                    Sin celular cargado (no les llega por WhatsApp):{' '}
                    {sinCelular.map((usuario) => usuario.nombre).join(', ')}. Se
                    carga en Configuración → Usuarios.
                </p>
            )}

            <div className="flex flex-col gap-4">
                {tipos.map((tipo) => (
                    <TarjetaAlerta
                        key={tipo.tipo}
                        tipo={tipo}
                        roles={roles}
                        usuarios={usuarios}
                        areas={areas}
                    />
                ))}
            </div>

            <EnviosRecientes envios={envios} />
        </div>
    );
}

type Formulario = {
    activa: boolean;
    por_app: boolean;
    por_whatsapp: boolean;
    roles: string[];
    usuarios: number[];
    areas: number[];
    hora: string;
};

function TarjetaAlerta({
    tipo,
    roles,
    usuarios,
    areas,
}: {
    tipo: ConfiguracionTipoAlerta;
    roles: string[];
    usuarios: UsuarioAlerta[];
    areas: AreaAlerta[];
}) {
    const ids = {
        activa: useId(),
        app: useId(),
        whatsapp: useId(),
        hora: useId(),
    };
    const { data, setData, put, processing, isDirty, errors } =
        useForm<Formulario>({
            activa: tipo.activa,
            por_app: tipo.por_app,
            por_whatsapp: tipo.por_whatsapp,
            roles: tipo.roles,
            usuarios: tipo.usuarios,
            areas: tipo.areas,
            hora: tipo.hora ?? '',
        });

    const alternar = <T,>(lista: T[], valor: T): T[] =>
        lista.includes(valor)
            ? lista.filter((otro) => otro !== valor)
            : [...lista, valor];

    const guardar = (event: React.FormEvent) => {
        event.preventDefault();
        put(update(tipo.tipo).url, { preserveScroll: true });
    };

    const sueltos = usuarios.filter((usuario) =>
        data.usuarios.includes(usuario.id),
    );
    const paraAgregar = usuarios.filter(
        (usuario) => !data.usuarios.includes(usuario.id),
    );

    return (
        <form
            onSubmit={guardar}
            className={cn(
                'flex flex-col gap-4 rounded-xl border bg-card p-4 md:p-5',
                !data.activa && 'opacity-70',
            )}
        >
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <h2 className="flex flex-wrap items-center gap-2 text-sm font-semibold">
                        {tipo.label}
                        {tipo.es_resumen && (
                            <span className="rounded-full bg-muted px-2 py-0.5 text-[11px] font-normal text-muted-foreground">
                                una vez al día
                            </span>
                        )}
                    </h2>
                    <p className="text-xs text-muted-foreground">
                        {tipo.descripcion}
                    </p>
                </div>
                <div className="flex shrink-0 items-center gap-2">
                    <Checkbox
                        id={ids.activa}
                        checked={data.activa}
                        onCheckedChange={(valor) =>
                            setData('activa', valor === true)
                        }
                    />
                    <Label htmlFor={ids.activa} className="text-sm">
                        Prendida
                    </Label>
                </div>
            </div>

            <Grupo titulo="Por dónde">
                <div className="flex flex-wrap gap-x-6 gap-y-2">
                    <div className="flex items-center gap-2">
                        <Checkbox
                            id={ids.app}
                            checked={data.por_app}
                            onCheckedChange={(valor) =>
                                setData('por_app', valor === true)
                            }
                        />
                        <Label htmlFor={ids.app} className="font-normal">
                            En la app (campanita)
                        </Label>
                    </div>
                    <div className="flex items-center gap-2">
                        <Checkbox
                            id={ids.whatsapp}
                            checked={data.por_whatsapp}
                            onCheckedChange={(valor) =>
                                setData('por_whatsapp', valor === true)
                            }
                        />
                        <Label htmlFor={ids.whatsapp} className="font-normal">
                            Por WhatsApp
                        </Label>
                    </div>
                </div>
            </Grupo>

            <Grupo titulo="A qué roles">
                <div className="flex flex-wrap gap-1.5">
                    {roles.map((rol) => (
                        <Chip
                            key={rol}
                            activo={data.roles.includes(rol)}
                            onClick={() =>
                                setData('roles', alternar(data.roles, rol))
                            }
                        >
                            {nombreRol(rol)}
                        </Chip>
                    ))}
                </div>
            </Grupo>

            <Grupo titulo="Además, a estas personas">
                <div className="flex flex-wrap items-center gap-1.5">
                    {sueltos.map((usuario) => (
                        <span
                            key={usuario.id}
                            className="inline-flex items-center gap-1 rounded-full border bg-primary/10 py-0.5 pr-1 pl-2.5 text-xs"
                        >
                            {usuario.nombre}
                            <button
                                type="button"
                                onClick={() =>
                                    setData(
                                        'usuarios',
                                        data.usuarios.filter(
                                            (id) => id !== usuario.id,
                                        ),
                                    )
                                }
                                className="rounded-full p-0.5 hover:bg-foreground/10"
                                aria-label={`Quitar a ${usuario.nombre}`}
                            >
                                <X className="size-3" />
                            </button>
                        </span>
                    ))}
                    {paraAgregar.length > 0 && (
                        <Select
                            value=""
                            onValueChange={(valor) =>
                                setData('usuarios', [
                                    ...data.usuarios,
                                    Number(valor),
                                ])
                            }
                        >
                            <SelectTrigger className="h-7 w-auto gap-1 rounded-full text-xs">
                                <SelectValue placeholder="+ Agregar persona" />
                            </SelectTrigger>
                            <SelectContent>
                                {paraAgregar.map((usuario) => (
                                    <SelectItem
                                        key={usuario.id}
                                        value={String(usuario.id)}
                                    >
                                        {usuario.nombre}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    )}
                </div>
            </Grupo>

            {data.por_whatsapp && areas.length > 0 && (
                <Grupo
                    titulo="Y a estos números de área (WhatsApp)"
                    ayuda="Los números del panel de WhatsApp. No tienen campanita: solo les llega el mensaje."
                >
                    <div className="flex flex-wrap gap-1.5">
                        {areas.map((area) => (
                            <Chip
                                key={area.id}
                                activo={data.areas.includes(area.id)}
                                onClick={() =>
                                    setData(
                                        'areas',
                                        alternar(data.areas, area.id),
                                    )
                                }
                            >
                                {area.nombre}
                                {!area.activa && ' (inactiva)'}
                            </Chip>
                        ))}
                    </div>
                </Grupo>
            )}

            {tipo.es_resumen && (
                <Grupo titulo="A qué hora (Lima)">
                    <Input
                        id={ids.hora}
                        type="time"
                        value={data.hora}
                        onChange={(event) =>
                            setData('hora', event.target.value)
                        }
                        className="w-32"
                        aria-label="Hora del resumen"
                    />
                    {errors.hora && (
                        <p className="text-xs text-destructive">
                            {errors.hora}
                        </p>
                    )}
                </Grupo>
            )}

            <div className="flex flex-col gap-3 border-t pt-3 sm:flex-row sm:items-center sm:justify-between">
                <p className="text-xs text-muted-foreground">
                    {tipo.destinatarios.length === 0 ? (
                        'Hoy no le llega a nadie.'
                    ) : (
                        <>
                            Hoy le llega a:{' '}
                            {tipo.destinatarios
                                .map(
                                    (destinatario) =>
                                        destinatario.nombre +
                                        (tipo.por_whatsapp &&
                                        !destinatario.con_celular
                                            ? ' (sin celular)'
                                            : ''),
                                )
                                .join(', ')}
                            .
                        </>
                    )}
                    {isDirty && ' Guarda para ver el cambio.'}
                </p>
                <div className="flex shrink-0 gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() =>
                            router.post(
                                probar(tipo.tipo).url,
                                {},
                                { preserveScroll: true },
                            )
                        }
                        title="Te llega solo a ti, por los canales guardados"
                    >
                        <Send className="size-3.5" />
                        Probar conmigo
                    </Button>
                    <Button
                        type="submit"
                        size="sm"
                        disabled={processing || !isDirty}
                    >
                        Guardar
                    </Button>
                </div>
            </div>
        </form>
    );
}

function Grupo({
    titulo,
    ayuda,
    children,
}: {
    titulo: string;
    ayuda?: string;
    children: React.ReactNode;
}) {
    return (
        <div className="flex flex-col gap-1.5">
            <p className="text-xs font-medium text-muted-foreground">
                {titulo}
            </p>
            {children}
            {ayuda && (
                <p className="text-[11px] text-muted-foreground/80">{ayuda}</p>
            )}
        </div>
    );
}

/** Un botón que se prende y se apaga, para elegir de una lista corta. */
function Chip({
    activo,
    onClick,
    children,
}: {
    activo: boolean;
    onClick: () => void;
    children: React.ReactNode;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            aria-pressed={activo}
            className={cn(
                'rounded-full border px-2.5 py-1 text-xs transition',
                activo
                    ? 'border-primary bg-primary text-primary-foreground'
                    : 'text-muted-foreground hover:border-foreground/30 hover:text-foreground',
            )}
        >
            {children}
        </button>
    );
}

function Aviso({ children }: { children: React.ReactNode }) {
    return (
        <div className="flex items-start gap-2 rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200">
            <TriangleAlert className="mt-0.5 size-4 shrink-0" />
            <p>{children}</p>
        </div>
    );
}

/** Las últimas alertas que salieron por WhatsApp y hasta dónde llegaron. */
function EnviosRecientes({ envios }: { envios: EnvioAlerta[] }) {
    return (
        <section className="flex flex-col gap-2">
            <h2 className="flex items-center gap-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                <MessageCircle className="size-3.5" />
                Últimas alertas por WhatsApp
            </h2>
            {envios.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    Todavía no salió ninguna alerta por WhatsApp.
                </p>
            ) : (
                <ul className="divide-y rounded-xl border bg-card text-sm">
                    {envios.map((envio) => (
                        <li
                            key={envio.id}
                            className="flex items-center gap-3 px-4 py-2"
                        >
                            <EstadoEnvio
                                envio={{
                                    estado: envio.estado,
                                    estado_label: envio.estado_label,
                                    destino: envio.destino,
                                    hora: envio.fecha,
                                    error: envio.error,
                                }}
                            />
                            <span className="min-w-0 flex-1 truncate">
                                {envio.alerta}{' '}
                                <span className="text-muted-foreground">
                                    → {envio.destino}
                                </span>
                                {envio.error && (
                                    <span className="block text-xs text-destructive">
                                        {envio.error}
                                    </span>
                                )}
                            </span>
                            <span className="shrink-0 text-xs text-muted-foreground tabular-nums">
                                {formatearFechaHora(envio.fecha)}
                            </span>
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}
