import { router } from '@inertiajs/react';
import { AlertTriangle, CheckCircle2 } from 'lucide-react';
import { useId, useState } from 'react';
import { actualizarGre } from '@/actions/App/Http/Controllers/ViajeController';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { StatusBadge } from '@/components/ui/status-badge';
import type { EnumOption, ViajeListItem } from '@/types/fleet';

type Props = {
    viaje: ViajeListItem;
    /** Catálogo de puntos activos, ya con su ubigeo en la etiqueta. */
    puntos: EnumOption[];
    motivosTraslado: EnumOption[];
    puedeEditar: boolean;
};

/** Valor centinela: los `Select` de Radix no aceptan opciones con valor vacío. */
const SIN_PUNTO = 'sin-punto';

/**
 * Los datos que la guía electrónica exige y el PDF importado no trae.
 *
 * Se edita desde el detalle del viaje y no en una pantalla aparte porque es
 * acá donde alguien nota que falta algo: al mirar la guía, no al buscarla.
 */
export function ViajeGrePanel({
    viaje,
    puntos,
    motivosTraslado,
    puedeEditar,
}: Props) {
    const partidaId = useId();
    const llegadaId = useId();
    const motivoId = useId();

    const [guardando, setGuardando] = useState(false);

    const emitible = viaje.gre_faltantes.length === 0;

    const guardar = (cambio: Partial<Record<string, string | null>>) => {
        setGuardando(true);

        router.patch(
            actualizarGre(viaje.id).url,
            {
                punto_partida_id: viaje.punto_partida_id,
                punto_llegada_id: viaje.punto_llegada_id,
                motivo_traslado: viaje.motivo_traslado,
                ...cambio,
            },
            {
                preserveScroll: true,
                preserveState: true,
                onFinish: () => setGuardando(false),
            },
        );
    };

    const valorPunto = (id: number | null) =>
        id === null ? SIN_PUNTO : String(id);

    const puntoElegido = (valor: string) =>
        valor === SIN_PUNTO ? null : valor;

    return (
        <div>
            <div className="mb-2 flex items-center justify-between gap-2">
                <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                    Guía electrónica
                </p>
                {emitible ? (
                    <StatusBadge label="Lista para emitir" tone="success" />
                ) : (
                    <StatusBadge
                        label={`Faltan ${viaje.gre_faltantes.length} datos`}
                        tone="warning"
                    />
                )}
            </div>

            <div className="rounded-lg border p-3">
                {puntos.length === 0 ? (
                    <p className="text-xs text-muted-foreground">
                        Todavía no hay puntos en el catálogo. Cárgalos en{' '}
                        <span className="font-medium">Puntos de traslado</span>{' '}
                        para poder declarar el ubigeo de partida y llegada.
                    </p>
                ) : (
                    <div className="grid gap-3 sm:grid-cols-2">
                        <div className="grid gap-1.5">
                            <Label htmlFor={partidaId} className="text-xs">
                                Punto de partida
                            </Label>
                            <Select
                                value={valorPunto(viaje.punto_partida_id)}
                                onValueChange={(valor) =>
                                    guardar({
                                        punto_partida_id: puntoElegido(valor),
                                    })
                                }
                                disabled={!puedeEditar || guardando}
                            >
                                <SelectTrigger id={partidaId} className="h-9">
                                    <SelectValue placeholder="Sin definir" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={SIN_PUNTO}>
                                        Sin definir
                                    </SelectItem>
                                    {puntos.map((punto) => (
                                        <SelectItem
                                            key={punto.value}
                                            value={punto.value}
                                        >
                                            {punto.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="grid gap-1.5">
                            <Label htmlFor={llegadaId} className="text-xs">
                                Punto de llegada
                            </Label>
                            <Select
                                value={valorPunto(viaje.punto_llegada_id)}
                                onValueChange={(valor) =>
                                    guardar({
                                        punto_llegada_id: puntoElegido(valor),
                                    })
                                }
                                disabled={!puedeEditar || guardando}
                            >
                                <SelectTrigger id={llegadaId} className="h-9">
                                    <SelectValue placeholder="Sin definir" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={SIN_PUNTO}>
                                        Sin definir
                                    </SelectItem>
                                    {puntos.map((punto) => (
                                        <SelectItem
                                            key={punto.value}
                                            value={punto.value}
                                        >
                                            {punto.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="grid gap-1.5 sm:col-span-2">
                            <Label htmlFor={motivoId} className="text-xs">
                                Motivo del traslado
                            </Label>
                            <Select
                                value={viaje.motivo_traslado}
                                onValueChange={(valor) =>
                                    guardar({ motivo_traslado: valor })
                                }
                                disabled={!puedeEditar || guardando}
                            >
                                <SelectTrigger id={motivoId} className="h-9">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {motivosTraslado.map((motivo) => (
                                        <SelectItem
                                            key={motivo.value}
                                            value={motivo.value}
                                        >
                                            {motivo.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                )}

                {emitible ? (
                    <p className="mt-3 flex items-start gap-1.5 text-xs text-muted-foreground">
                        <CheckCircle2 className="mt-px size-3.5 shrink-0 text-emerald-600" />
                        Tiene todos los datos que SUNAT exige.
                    </p>
                ) : (
                    <ul className="mt-3 space-y-1">
                        {viaje.gre_faltantes.map((falta) => (
                            <li
                                key={falta}
                                className="flex items-start gap-1.5 text-xs text-muted-foreground"
                            >
                                <AlertTriangle className="mt-px size-3.5 shrink-0 text-amber-500" />
                                {falta}
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </div>
    );
}
