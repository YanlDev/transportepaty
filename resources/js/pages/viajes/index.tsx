import { Head, Link } from '@inertiajs/react';
import { FilePenLine, Route as RouteIcon } from 'lucide-react';
import { useState } from 'react';
import viajes, { create } from '@/actions/App/Http/Controllers/ViajeController';
import { EmptyState } from '@/components/empty-state';
import { FiltroSelect } from '@/components/filtro-select';
import { FiltrosBarra } from '@/components/filtros-barra';
import { Button } from '@/components/ui/button';
import { Paginacion } from '@/components/ui/paginacion';
import {
    ReintentarCoincidencias,
    SubirGuias,
} from '@/components/viajes/acciones-guias';
import { TablaViajes } from '@/components/viajes/tabla-viajes';
import { ViajeDetalleDialog } from '@/components/viajes/viaje-detalle-dialog';
import { ViajeTarjetaMovil } from '@/components/viajes/viaje-tarjeta-movil';
import { usePermisos } from '@/hooks/use-permisos';
import type { FiltrosViaje } from '@/hooks/use-viaje-filtros';
import { useViajeFiltros } from '@/hooks/use-viaje-filtros';
import { agruparViajes } from '@/lib/agrupar-viajes';
import type { EnumOption, Paginator, ViajeListItem } from '@/types/fleet';

type Props = {
    viajes: Paginator<ViajeListItem>;
    filtros: FiltrosViaje;
    /** Viajes sin tracto, conductor, o carreta resueltos contra el padrón. */
    pendientes: number;
    tiposCarga: EnumOption[];
    clientes: EnumOption[];
    ciudadesDestino: EnumOption[];
};

export default function ViajesIndex({
    viajes: paginador,
    filtros,
    pendientes,
    tiposCarga,
    clientes,
    ciudadesDestino,
}: Props) {
    const { puedeEditar } = usePermisos();
    const { buscar, setBuscar, aplicar } = useViajeFiltros(filtros);
    const filtrosActivos = [
        filtros.cliente,
        filtros.destino_ciudad,
        filtros.tipo_carga,
    ].filter(Boolean).length;
    const [viajeSeleccionado, setViajeSeleccionado] =
        useState<ViajeListItem | null>(null);
    const filas = agruparViajes(paginador.data);

    return (
        <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
            <Head title="Viajes" />

            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p className="text-sm text-muted-foreground">
                        {paginador.total}{' '}
                        {paginador.total === 1
                            ? 'viaje registrado a partir de las GR emitidas'
                            : 'viajes registrados a partir de las GR emitidas'}
                    </p>
                </div>

                {puedeEditar && (
                    <div className="flex flex-wrap items-center gap-2">
                        {pendientes > 0 && (
                            <ReintentarCoincidencias pendientes={pendientes} />
                        )}
                        <Button asChild variant="outline">
                            <Link href={create()}>
                                <FilePenLine className="size-4" />
                                Agregar manualmente
                            </Link>
                        </Button>
                        <SubirGuias />
                    </div>
                )}
            </div>

            <FiltrosBarra
                buscar={buscar}
                onBuscar={setBuscar}
                placeholder="Buscar por placa, cliente, conductor, destino o N° de GR..."
                etiquetaBusqueda="Buscar viajes"
                activos={filtrosActivos}
                onLimpiar={() =>
                    aplicar({
                        cliente: null,
                        destino_ciudad: null,
                        tipo_carga: null,
                    })
                }
            >
                <FiltroSelect
                    valor={filtros.cliente}
                    onCambio={(cliente) => aplicar({ cliente })}
                    todos="Todos los clientes"
                    etiqueta="Cliente"
                    opciones={clientes}
                />
                <FiltroSelect
                    valor={filtros.destino_ciudad}
                    onCambio={(destino_ciudad) => aplicar({ destino_ciudad })}
                    todos="Todos los destinos"
                    etiqueta="Destino"
                    opciones={ciudadesDestino}
                />
                <FiltroSelect
                    valor={filtros.tipo_carga}
                    onCambio={(tipo_carga) => aplicar({ tipo_carga })}
                    todos="Todos los tipos de carga"
                    etiqueta="Carga"
                    opciones={tiposCarga}
                />
            </FiltrosBarra>

            {paginador.data.length === 0 ? (
                <EmptyState
                    icono={<RouteIcon className="size-7" />}
                    titulo="No se encontraron viajes"
                    descripcion={
                        filtros.buscar
                            ? 'Ajusta la búsqueda.'
                            : puedeEditar
                              ? 'Sube tus primeras GR para empezar el historial.'
                              : 'Todavía no se ha subido ninguna GR.'
                    }
                />
            ) : (
                <>
                    <div className="flex flex-col gap-2 sm:hidden">
                        {filas.map(({ viaje, colorGrupo }) => (
                            <ViajeTarjetaMovil
                                key={viaje.id}
                                viaje={viaje}
                                tiposCarga={tiposCarga}
                                puedeEditar={puedeEditar}
                                colorGrupo={colorGrupo}
                                onVerDetalle={() => setViajeSeleccionado(viaje)}
                            />
                        ))}
                    </div>

                    <TablaViajes
                        filas={filas}
                        tiposCarga={tiposCarga}
                        puedeEditar={puedeEditar}
                        onVerDetalle={setViajeSeleccionado}
                    />

                    <Paginacion paginador={paginador} />
                </>
            )}

            <ViajeDetalleDialog
                viaje={viajeSeleccionado}
                onOpenChange={(abierto) => {
                    if (!abierto) {
                        setViajeSeleccionado(null);
                    }
                }}
            />
        </div>
    );
}

/**
 * La GR no trae qué tipo de carga es —eso solo lo sabe quien clasifica el
 * archivo a mano—, así que se corrige acá mismo con un desplegable en vez de
 * mandar a un formulario aparte. El badge queda neutro (no el color del
 * cliente) para no competir con el chip de `ClienteChip`, que es la señal
 * principal de la fila.
 */

ViajesIndex.layout = {
    breadcrumbs: [{ title: 'Viajes', href: viajes.index().url }],
};
