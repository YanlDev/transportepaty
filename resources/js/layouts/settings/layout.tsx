import { Link } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import roles from '@/actions/App/Http/Controllers/RolController';
import usuarios from '@/actions/App/Http/Controllers/UserController';
import whatsapp from '@/actions/App/Http/Controllers/WhatsappController';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { usePermisos } from '@/hooks/use-permisos';
import { cn, toUrl } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { edit } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import type { NavItem } from '@/types';

/** Lo de cada uno: cualquier usuario lo ve. */
const cuentaNavItems: NavItem[] = [
    { title: 'Perfil', href: edit(), icon: null },
    { title: 'Seguridad', href: editSecurity(), icon: null },
    { title: 'Apariencia', href: editAppearance(), icon: null },
];

/**
 * Configuración del sistema entero, no de la cuenta propia. Vive acá y no en
 * el menú lateral porque no es trabajo del día: se toca de vez en cuando.
 */
const usuariosNavItem: NavItem = {
    title: 'Usuarios',
    href: usuarios.index(),
    icon: null,
};

const rolesNavItem: NavItem = {
    title: 'Roles y permisos',
    href: roles.index(),
    icon: null,
};

const whatsappNavItem: NavItem = {
    title: 'WhatsApp',
    href: whatsapp.index(),
    icon: null,
};

export default function SettingsLayout({ children }: PropsWithChildren) {
    const { isCurrentOrParentUrl } = useCurrentUrl();
    const { puede, esAdmin } = usePermisos();

    const sistemaNavItems = [
        ...(esAdmin ? [usuariosNavItem, rolesNavItem] : []),
        ...(puede('whatsapp.administrar') ? [whatsappNavItem] : []),
    ];

    const grupos = [
        { titulo: 'Tu cuenta', items: cuentaNavItems },
        { titulo: 'Sistema', items: sistemaNavItems },
    ].filter((grupo) => grupo.items.length > 0);

    // Las pantallas del sistema son tablas y matrices: usan todo el ancho.
    // Las de la cuenta son formularios cortos y quedan en una columna.
    const enSistema = sistemaNavItems.some((item) =>
        isCurrentOrParentUrl(item.href),
    );

    return (
        <div className="px-4 py-6">
            <Heading
                title="Configuración"
                description="Tu cuenta y los ajustes del sistema"
            />

            <div className="flex flex-col lg:flex-row lg:space-x-12">
                <aside className="w-full max-w-xl lg:w-48 lg:shrink-0">
                    <nav
                        className="flex flex-col gap-5"
                        aria-label="Configuración"
                    >
                        {grupos.map((grupo) => (
                            <div
                                key={grupo.titulo}
                                className="flex flex-col space-y-1"
                            >
                                <p className="px-3 pb-1 text-xs font-medium text-muted-foreground">
                                    {grupo.titulo}
                                </p>
                                {grupo.items.map((item, index) => (
                                    <Button
                                        key={`${toUrl(item.href)}-${index}`}
                                        size="sm"
                                        variant="ghost"
                                        asChild
                                        className={cn('w-full justify-start', {
                                            'bg-muted': isCurrentOrParentUrl(
                                                item.href,
                                            ),
                                        })}
                                    >
                                        <Link href={item.href}>
                                            {item.icon && (
                                                <item.icon className="h-4 w-4" />
                                            )}
                                            {item.title}
                                        </Link>
                                    </Button>
                                ))}
                            </div>
                        ))}
                    </nav>
                </aside>

                <Separator className="my-6 lg:hidden" />

                {enSistema ? (
                    <div className="min-w-0 flex-1">{children}</div>
                ) : (
                    <div className="flex-1 md:max-w-2xl">
                        <section className="max-w-xl space-y-12">
                            {children}
                        </section>
                    </div>
                )}
            </div>
        </div>
    );
}
