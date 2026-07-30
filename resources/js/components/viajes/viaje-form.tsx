import { useForm } from '@inertiajs/react';
import viajes from '@/actions/App/Http/Controllers/ViajeController';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import type { EnumOption, ViajeEditable, ViajeOpciones } from '@/types/fleet';

/** Radix Select no admite items con value vacío. */
const SIN_VALOR = 'sin_valor';

type Props = {
    /** Null para registrar un viaje nuevo. */
    viaje?: ViajeEditable | null;
    opciones: ViajeOpciones;
};

type Campos = {
    tracto_id: string;
    carreta_id: string;
    conductor_id: string;
    tipo_carga: string;
    origen_id: string;
    destino_id: string;
    fecha_salida: string;
    fecha_llegada: string;
    numero_guia_remitente: string;
    numero_guia_transportista: string;
    observaciones: string;
};

const desdeViaje = (viaje: ViajeEditable | null | undefined): Campos => ({
    tracto_id: viaje ? String(viaje.tracto_id) : '',
    carreta_id: viaje?.carreta_id ? String(viaje.carreta_id) : '',
    conductor_id: viaje?.conductor_id ? String(viaje.conductor_id) : '',
    tipo_carga: viaje?.tipo_carga ?? '',
    origen_id: viaje ? String(viaje.origen_id) : '',
    destino_id: viaje ? String(viaje.destino_id) : '',
    fecha_salida: viaje?.fecha_salida ?? new Date().toISOString().slice(0, 10),
    fecha_llegada: viaje?.fecha_llegada ?? '',
    numero_guia_remitente: viaje?.numero_guia_remitente ?? '',
    numero_guia_transportista: viaje?.numero_guia_transportista ?? '',
    observaciones: viaje?.observaciones ?? '',
});

/**
 * Alta y corrección de un viaje. La fase no se pide: la determina la carga, y
 * ofrecerla aparte solo abriría la puerta a que se contradigan.
 *
 * Dejar la llegada en blanco es lo que mantiene el viaje en curso.
 */
export function ViajeForm({ viaje, opciones }: Props) {
    const editando = Boolean(viaje);

    const { data, setData, submit, processing, errors } = useForm<Campos>(
        desdeViaje(viaje),
    );

    const enviar = (evento: React.FormEvent) => {
        evento.preventDefault();

        submit(editando ? viajes.update(viaje!.id) : viajes.store(), {
            preserveScroll: true,
        });
    };

    const campoSelect = (
        etiqueta: string,
        campo: keyof Campos,
        items: EnumOption[],
        placeholder: string,
    ) => (
        <div className="grid gap-2">
            <Label htmlFor={campo}>{etiqueta}</Label>
            <Select
                value={data[campo] || SIN_VALOR}
                onValueChange={(value) =>
                    setData(campo, value === SIN_VALOR ? '' : value)
                }
            >
                <SelectTrigger id={campo} aria-label={etiqueta}>
                    <SelectValue placeholder={placeholder} />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value={SIN_VALOR}>{placeholder}</SelectItem>
                    {items.map((item) => (
                        <SelectItem key={item.value} value={item.value}>
                            {item.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            {errors[campo] && (
                <p className="text-sm text-destructive">{errors[campo]}</p>
            )}
        </div>
    );

    const campoTexto = (
        etiqueta: string,
        campo: keyof Campos,
        tipo: 'text' | 'date',
        ayuda?: string,
    ) => (
        <div className="grid gap-2">
            <Label htmlFor={campo}>{etiqueta}</Label>
            <Input
                id={campo}
                type={tipo}
                value={data[campo]}
                onChange={(evento) => setData(campo, evento.target.value)}
            />
            {ayuda && <p className="text-xs text-muted-foreground">{ayuda}</p>}
            {errors[campo] && (
                <p className="text-sm text-destructive">{errors[campo]}</p>
            )}
        </div>
    );

    return (
        <form onSubmit={enviar} className="grid max-w-3xl gap-6">
            <div className="grid gap-4 sm:grid-cols-2">
                {campoSelect(
                    'Tracto',
                    'tracto_id',
                    opciones.tractos,
                    'Elige la unidad',
                )}
                {campoSelect(
                    'Carreta',
                    'carreta_id',
                    opciones.carretas,
                    'Sin carreta',
                )}
                {campoSelect(
                    'Conductor',
                    'conductor_id',
                    opciones.conductores,
                    'Sin conductor',
                )}
                {campoSelect(
                    'Tipo de carga',
                    'tipo_carga',
                    opciones.cargas,
                    'Elige la carga',
                )}
                {campoSelect(
                    'Origen',
                    'origen_id',
                    opciones.ubicaciones,
                    'Elige el origen',
                )}
                {campoSelect(
                    'Destino',
                    'destino_id',
                    opciones.ubicaciones,
                    'Elige el destino',
                )}
                {campoTexto('Salida', 'fecha_salida', 'date')}
                {campoTexto(
                    'Llegada',
                    'fecha_llegada',
                    'date',
                    'Déjala en blanco mientras el viaje siga en curso.',
                )}
                {campoTexto(
                    'Guía del remitente (GRR)',
                    'numero_guia_remitente',
                    'text',
                )}
                {campoTexto(
                    'Guía del transportista (GRT)',
                    'numero_guia_transportista',
                    'text',
                )}
            </div>

            <div className="grid gap-2">
                <Label htmlFor="observaciones">Observaciones</Label>
                <Textarea
                    id="observaciones"
                    value={data.observaciones}
                    onChange={(evento) =>
                        setData('observaciones', evento.target.value)
                    }
                    rows={3}
                />
            </div>

            <div>
                <Button type="submit" disabled={processing}>
                    {editando ? 'Guardar cambios' : 'Registrar viaje'}
                </Button>
            </div>
        </form>
    );
}
