import { Head, Link } from '@inertiajs/react';
import { Landmark, Receipt } from 'lucide-react';
import { useState } from 'react';
import contabilidad from '@/actions/App/Http/Controllers/ContabilidadController';
import cuentas from '@/actions/App/Http/Controllers/CuentaBancariaController';
import { BarraSeleccion } from '@/components/contabilidad/barra-seleccion';
import { ResumenCobranza } from '@/components/contabilidad/resumen-cobranza';
import { TablaCobranza } from '@/components/contabilidad/tabla-cobranza';
import { EmptyState } from '@/components/empty-state';
import { FiltroSelect } from '@/components/filtro-select';
import { FiltrosBarra } from '@/components/filtros-barra';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Paginacion } from '@/components/ui/paginacion';
import { useContabilidadFiltros } from '@/hooks/use-contabilidad-filtros';
import { usePermisos } from '@/hooks/use-permisos';
import { agruparViajes } from '@/lib/agrupar-viajes';
import type {
    CuentaOpcion,
    FiltrosContabilidad,
    ResumenCobranza as Resumen,
    ViajeContable,
} from '@/types/contabilidad';
import type { EnumOption, Paginator } from '@/types/fleet';

type Props = {
    viajes: Paginator<ViajeContable>;
    filtros: FiltrosContabilidad;
    resumen: Resumen;
    estados: EnumOption[];
    monedas: EnumOption[];
    clientes: EnumOption[];
    meses: EnumOption[];
    cuentas: CuentaOpcion[];
};

export default function ContabilidadIndex({
    viajes: paginador,
    filtros,
    resumen,
    estados,
    monedas,
    clientes,
    meses,
    cuentas: cuentasBancarias,
}: Props) {
    const { puedeFacturar } = usePermisos();
    const { buscar, setBuscar, aplicar } = useContabilidadFiltros(filtros);

    // Los ids elegidos, no los viajes: la fila se re-renderiza en cada visita
    // de Inertia y guardar el objeto dejaría copias viejas en la selección.
    const [seleccion, setSeleccion] = useState<number[]>([]);

    const filas = agruparViajes(paginador.data);

    // Solo lo que todavía no se facturó entra en una selección: elegir un
    // viaje ya cobrado para facturarlo de nuevo es siempre un error.
    const facturables = paginador.data.filter(
        (viaje) => viaje.factura === null,
    );

    const filtrosActivos = [
        filtros.cliente,
        filtros.estado,
        filtros.mes,
        filtros.desde,
        filtros.hasta,
    ].filter(Boolean).length;

    return (
        <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
            <Head title="Contabilidad" />

            <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <ResumenCobranza
                    resumen={resumen}
                    onMes={(mes) => aplicar({ mes, desde: null, hasta: null })}
                />

                <Button asChild variant="outline">
                    <Link href={cuentas.index()}>
                        <Landmark className="size-4" />
                        Cuentas
                    </Link>
                </Button>
            </div>

            <FiltrosBarra
                buscar={buscar}
                onBuscar={setBuscar}
                placeholder="Buscar por N° de GR, N° de factura, cliente o placa..."
                etiquetaBusqueda="Buscar en la cobranza"
                activos={filtrosActivos}
                onLimpiar={() =>
                    aplicar({
                        cliente: null,
                        estado: null,
                        mes: null,
                        desde: null,
                        hasta: null,
                    })
                }
            >
                <FiltroSelect
                    valor={filtros.estado}
                    onCambio={(estado) => aplicar({ estado })}
                    todos="Todos los estados"
                    etiqueta="Estado"
                    opciones={estados}
                />
                <FiltroSelect
                    valor={filtros.cliente}
                    onCambio={(cliente) => aplicar({ cliente })}
                    todos="Todos los clientes"
                    etiqueta="Cliente"
                    opciones={clientes}
                />
                {/* El mes y el rango dicen lo mismo de dos formas, así que se
                    excluyen: elegir uno limpia el otro en vez de intersectarse
                    y devolver un resultado que nadie pidió. */}
                <FiltroSelect
                    valor={filtros.mes}
                    onCambio={(mes) =>
                        aplicar({ mes, desde: null, hasta: null })
                    }
                    todos="Todos los meses"
                    etiqueta="Mes"
                    opciones={meses}
                />
                <Input
                    type="date"
                    className="h-9 w-full sm:w-auto"
                    aria-label="Desde"
                    value={filtros.desde ?? ''}
                    onChange={(evento) =>
                        aplicar({
                            desde: evento.target.value || null,
                            mes: null,
                        })
                    }
                />
                <Input
                    type="date"
                    className="h-9 w-full sm:w-auto"
                    aria-label="Hasta"
                    value={filtros.hasta ?? ''}
                    onChange={(evento) =>
                        aplicar({
                            hasta: evento.target.value || null,
                            mes: null,
                        })
                    }
                />
            </FiltrosBarra>

            {seleccion.length > 0 && puedeFacturar && (
                <BarraSeleccion
                    viajeIds={seleccion}
                    onListo={() => setSeleccion([])}
                />
            )}

            {paginador.data.length === 0 ? (
                <EmptyState
                    icono={<Receipt className="size-7" />}
                    titulo="No hay viajes para mostrar"
                    descripcion="Ajusta los filtros o la búsqueda."
                />
            ) : (
                <>
                    <TablaCobranza
                        filas={filas}
                        facturables={facturables}
                        cuentas={cuentasBancarias}
                        monedas={monedas}
                        puedeFacturar={puedeFacturar}
                        seleccion={seleccion}
                        onSeleccion={setSeleccion}
                    />

                    <Paginacion paginador={paginador} />
                </>
            )}
        </div>
    );
}

ContabilidadIndex.layout = {
    breadcrumbs: [{ title: 'Contabilidad', href: contabilidad.index().url }],
};
