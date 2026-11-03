import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { Bell, Settings } from 'lucide-react';
import {
    index,
    leerTodas,
} from '@/actions/App/Http/Controllers/AlertaController';
import { index as configuracionAlertas } from '@/actions/App/Http/Controllers/ConfiguracionAlertaController';
import { FilaAlerta } from '@/components/campanita';
import { EmptyState } from '@/components/empty-state';
import { EncabezadoPantalla } from '@/components/encabezado-pantalla';
import { Button } from '@/components/ui/button';
import { Paginacion } from '@/components/ui/paginacion';
import { usePermisos } from '@/hooks/use-permisos';
import type { Alerta } from '@/types/alertas';
import type { Paginator } from '@/types/fleet';

/**
 * Todas las alertas de quien entra, de la más nueva a la más vieja. La
 * campanita muestra las últimas; acá están todas.
 */
export default function AlertasIndex({
    alertas,
    sinLeer,
}: {
    alertas: Paginator<Alerta>;
    sinLeer: number;
}) {
    const { puede } = usePermisos();

    setLayoutProps({
        breadcrumbs: [{ title: 'Alertas', href: index().url }],
    });

    return (
        <div className="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-6 p-4 md:p-6">
            <Head title="Alertas" />

            <EncabezadoPantalla
                titulo="Alertas"
                descripcion={
                    sinLeer > 0
                        ? `Tienes ${sinLeer} sin leer. Toca una para ir a donde se resuelve.`
                        : 'Lo que te avisó el sistema. Estás al día.'
                }
                icono={Bell}
                acciones={
                    <div className="flex flex-wrap gap-2">
                        {sinLeer > 0 && (
                            <Button variant="outline" size="sm" asChild>
                                <Link
                                    href={leerTodas()}
                                    as="button"
                                    preserveScroll
                                >
                                    Marcar todas como leídas
                                </Link>
                            </Button>
                        )}
                        {puede('alertas.configurar') && (
                            <Button variant="ghost" size="sm" asChild>
                                <Link href={configuracionAlertas()}>
                                    <Settings className="size-4" />
                                    Configurar
                                </Link>
                            </Button>
                        )}
                    </div>
                }
            />

            {alertas.data.length === 0 ? (
                <EmptyState
                    icono={<Bell />}
                    titulo="No tienes alertas"
                    descripcion="Cuando pase algo que te toca —un ticket por revisar, uno listo para abastecer— te va a aparecer acá."
                />
            ) : (
                <ul className="divide-y overflow-hidden rounded-xl border bg-card">
                    {alertas.data.map((alerta) => (
                        <li key={alerta.id}>
                            <FilaAlerta alerta={alerta} />
                        </li>
                    ))}
                </ul>
            )}

            <Paginacion paginador={alertas} />
        </div>
    );
}
