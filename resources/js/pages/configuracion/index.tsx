import { Head, Link, setLayoutProps } from '@inertiajs/react';
import {
    Calculator,
    ChevronRight,
    CircleGauge,
    Fuel,
    History,
    KeyRound,
    MessageCircle,
    Palette,
    Route,
    ShieldCheck,
    User,
    Users,
    Wrench,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import configuracionAbastecimiento from '@/actions/App/Http/Controllers/ConfiguracionAbastecimientoController';
import configuracion from '@/actions/App/Http/Controllers/ConfiguracionController';
import configuracionProgramacion from '@/actions/App/Http/Controllers/ConfiguracionProgramacionController';
import equipamiento from '@/actions/App/Http/Controllers/EquipamientoCarretaController';
import historial from '@/actions/App/Http/Controllers/HistorialController';
import llantas from '@/actions/App/Http/Controllers/LlantaController';
import parametrosCosto from '@/actions/App/Http/Controllers/ParametroCostoController';
import { edit as editarPlan } from '@/actions/App/Http/Controllers/PlanMantenimientoController';
import roles from '@/actions/App/Http/Controllers/RolController';
import usuarios from '@/actions/App/Http/Controllers/UserController';
import whatsapp from '@/actions/App/Http/Controllers/WhatsappController';
import { usePermisos } from '@/hooks/use-permisos';
import { edit as editAppearance } from '@/routes/appearance';
import { edit as editProfile } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';

type Ajuste = {
    titulo: string;
    descripcion: string;
    href: string;
    icono: LucideIcon;
    /** Accesos directos dentro del ajuste (un plan por marca). */
    atajos?: { titulo: string; href: string }[];
};

type Grupo = { titulo: string; ajustes: Ajuste[] };

function Tarjeta({ ajuste }: { ajuste: Ajuste }) {
    const Icono = ajuste.icono;

    return (
        <div className="flex flex-col gap-2 rounded-xl border bg-card p-4 transition hover:border-foreground/30">
            <Link href={ajuste.href} className="group flex items-start gap-3">
                <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-muted text-muted-foreground group-hover:text-foreground">
                    <Icono className="size-4" />
                </span>
                <span className="min-w-0 flex-1">
                    <span className="flex items-center gap-1 text-sm font-medium">
                        {ajuste.titulo}
                        <ChevronRight className="size-3.5 text-muted-foreground transition group-hover:translate-x-0.5" />
                    </span>
                    <span className="block text-xs text-muted-foreground">
                        {ajuste.descripcion}
                    </span>
                </span>
            </Link>
            {ajuste.atajos && ajuste.atajos.length > 0 && (
                <div className="flex flex-wrap gap-1.5 pl-12">
                    {ajuste.atajos.map((atajo) => (
                        <Link
                            key={atajo.href}
                            href={atajo.href}
                            className="rounded-full border px-2.5 py-0.5 text-xs text-muted-foreground hover:border-foreground/30 hover:text-foreground"
                        >
                            {atajo.titulo}
                        </Link>
                    ))}
                </div>
            )}
        </div>
    );
}

/**
 * La puerta única de la configuración. Cada ajuste sigue viviendo en su
 * pantalla (el ⚙️ de Programación o de Abastecimiento lleva al mismo lugar);
 * acá están todos juntos, por área, y cada quien ve solo lo que puede tocar.
 */
export default function Configuracion({
    planes,
}: {
    planes: { id: number; marca: string; nombre: string }[];
}) {
    const { puede, esAdmin } = usePermisos();

    setLayoutProps({
        breadcrumbs: [
            { title: 'Configuración', href: configuracion.index().url },
        ],
    });

    const grupos: Grupo[] = [
        {
            titulo: 'Operación',
            ajustes: [
                ...(puede('programacion.configurar')
                    ? [
                          {
                              titulo: 'Rutas y tramos',
                              descripcion:
                                  'Las opciones de ida y vuelta al programar, los destinos particulares y las palabras de cada zona.',
                              href: configuracionProgramacion.index().url,
                              icono: Route,
                          },
                      ]
                    : []),
                ...(puede('abastecimiento.configurar')
                    ? [
                          {
                              titulo: 'Pizarra de abastecimiento',
                              descripcion:
                                  'Los galones y viáticos de cada ruta, los puntos (grifos) y el tanque lleno.',
                              href: configuracionAbastecimiento.index().url,
                              icono: Fuel,
                          },
                      ]
                    : []),
            ],
        },
        {
            titulo: 'Flota',
            ajustes: puede('mantenimiento.configurar')
                ? [
                      {
                          titulo: 'Planes de mantenimiento',
                          descripcion:
                              'Los servicios X/S/M/L de cada marca: cada cuántos km y meses.',
                          href: planes[0] ? editarPlan(planes[0].id).url : '#',
                          icono: Wrench,
                          atajos: planes.map((plan) => ({
                              titulo: plan.marca,
                              href: editarPlan(plan.id).url,
                          })),
                      },
                      {
                          titulo: 'Llantas',
                          descripcion:
                              'Los cortes de profundidad para revisar y cambiar («Ajustar cortes» en la pantalla).',
                          href: llantas.index().url,
                          icono: CircleGauge,
                      },
                      {
                          titulo: 'Equipamiento de carretas',
                          descripcion:
                              'Las fajas y los mínimos por carreta («Ítems y mínimos» en la pantalla).',
                          href: equipamiento.index().url,
                          icono: ShieldCheck,
                      },
                  ]
                : [],
        },
        {
            titulo: 'Comercial',
            ajustes: puede('costos.editar')
                ? [
                      {
                          titulo: 'Costos de la flota',
                          descripcion:
                              'Los parámetros con que se cotiza: combustible, peajes, póliza, depreciación.',
                          href: parametrosCosto.edit().url,
                          icono: Calculator,
                      },
                  ]
                : [],
        },
        {
            titulo: 'Sistema',
            ajustes: [
                ...(esAdmin
                    ? [
                          {
                              titulo: 'Usuarios',
                              descripcion:
                                  'Quién entra al sistema y con qué rol.',
                              href: usuarios.index().url,
                              icono: Users,
                          },
                          {
                              titulo: 'Roles y permisos',
                              descripcion:
                                  'Qué puede ver y hacer cada rol, por área.',
                              href: roles.index().url,
                              icono: KeyRound,
                          },
                      ]
                    : []),
                ...(puede('whatsapp.administrar')
                    ? [
                          {
                              titulo: 'WhatsApp',
                              descripcion:
                                  'El número de la empresa, las áreas que reciben avisos y el recordatorio diario.',
                              href: whatsapp.index().url,
                              icono: MessageCircle,
                          },
                      ]
                    : []),
                ...(puede('historial.ver')
                    ? [
                          {
                              titulo: 'Historial de cambios',
                              descripcion:
                                  'Quién cambió qué y cuándo, en todo el sistema.',
                              href: historial.index().url,
                              icono: History,
                          },
                      ]
                    : []),
            ],
        },
        {
            titulo: 'Tu cuenta',
            ajustes: [
                {
                    titulo: 'Perfil',
                    descripcion: 'Tu nombre y tu usuario.',
                    href: editProfile().url,
                    icono: User,
                },
                {
                    titulo: 'Seguridad',
                    descripcion:
                        'Tu contraseña y la verificación en dos pasos.',
                    href: editSecurity().url,
                    icono: ShieldCheck,
                },
                {
                    titulo: 'Apariencia',
                    descripcion: 'Claro u oscuro.',
                    href: editAppearance().url,
                    icono: Palette,
                },
            ],
        },
    ].filter((grupo) => grupo.ajustes.length > 0);

    return (
        <div className="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-8 p-4 md:p-6">
            <Head title="Configuración" />

            <div>
                <h1 className="text-lg font-semibold">Configuración</h1>
                <p className="text-sm text-muted-foreground">
                    Todos los ajustes del sistema en un solo lugar. Solo ves los
                    que puedes cambiar.
                </p>
            </div>

            {grupos.map((grupo) => (
                <section key={grupo.titulo} className="flex flex-col gap-3">
                    <h2 className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                        {grupo.titulo}
                    </h2>
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        {grupo.ajustes.map((ajuste) => (
                            <Tarjeta key={ajuste.titulo} ajuste={ajuste} />
                        ))}
                    </div>
                </section>
            ))}
        </div>
    );
}
