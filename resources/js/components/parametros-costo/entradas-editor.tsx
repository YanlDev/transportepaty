import { Plus, Trash2 } from 'lucide-react';
import { useId } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type {
    EntradasComponente,
    FilaEntrada,
    ValorEntrada,
} from '@/types/fleet';

type TipoCampo = 'numero' | 'porcentaje' | 'booleano' | 'texto';

type Campo = {
    clave: string;
    label: string;
    tipo: TipoCampo;
    step?: string;
    ayuda?: string;
};

type Filas = {
    clave: string;
    titulo: string;
    agregar: string;
    columnas: Campo[];
    nueva: FilaEntrada;
};

/**
 * Qué campos pide cada método. Se describe como datos y no como siete bloques
 * de JSX porque la diferencia entre un método y otro es justamente la lista de
 * campos: la forma de editarlos es siempre la misma.
 */
const FORMULARIOS: Record<string, { campos: Campo[]; filas?: Filas }> = {
    manual: {
        campos: [
            { clave: 'tasa', label: 'Tasa', tipo: 'numero', step: '0.0001' },
        ],
    },
    prorrateo_anual: {
        campos: [
            { clave: 'monto_anual', label: 'Monto anual (S/)', tipo: 'numero' },
            {
                clave: 'dedicacion_pct',
                label: 'Dedicación a la operación (%)',
                tipo: 'porcentaje',
                ayuda: 'Qué parte de ese gasto atiende al transporte y no a otras actividades.',
            },
        ],
    },
    planilla_conductor: {
        campos: [
            {
                clave: 'sueldo_base',
                label: 'Sueldo base mensual (S/)',
                tipo: 'numero',
            },
            {
                clave: 'seguro_vida_ley',
                label: 'Seguro vida ley (S/)',
                tipo: 'numero',
            },
            { clave: 'sctr', label: 'SCTR (S/)', tipo: 'numero' },
            {
                clave: 'asignacion_familiar',
                label: 'Asignación familiar (S/)',
                tipo: 'numero',
            },
            { clave: 'otros', label: 'Otros (S/)', tipo: 'numero' },
            {
                clave: 'remuneraciones_anuales',
                label: 'Remuneraciones al año',
                tipo: 'numero',
                step: '0.5',
            },
            {
                clave: 'meses_vacaciones',
                label: 'Meses de vacaciones',
                tipo: 'numero',
                step: '0.5',
            },
            {
                clave: 'gratificaciones',
                label: 'Gratificaciones',
                tipo: 'numero',
                step: '0.5',
            },
            {
                clave: 'cts_meses',
                label: 'CTS (en sueldos)',
                tipo: 'numero',
                step: '0.0001',
            },
            { clave: 'essalud_pct', label: 'EsSalud (%)', tipo: 'porcentaje' },
            {
                clave: 'dias_feriados',
                label: 'Feriados al año',
                tipo: 'numero',
            },
            {
                clave: 'valor_dia_feriado',
                label: 'Valor del día feriado (S/)',
                tipo: 'numero',
            },
            {
                clave: 'choferes_por_camion',
                label: 'Conductores por camión',
                tipo: 'numero',
                step: '0.01',
                ayuda: 'La unidad rueda más días de los que una sola persona puede manejar: hay relevos.',
            },
        ],
    },
    activo: {
        campos: [
            {
                clave: 'valor_tracto',
                label: 'Valor del tracto (S/)',
                tipo: 'numero',
            },
            {
                clave: 'valor_carreta',
                label: 'Valor de la carreta (S/)',
                tipo: 'numero',
            },
            {
                clave: 'valor_residual_pct',
                label: 'Valor residual (%)',
                tipo: 'porcentaje',
            },
            {
                clave: 'vida_util_anios',
                label: 'Vida útil (años)',
                tipo: 'numero',
                step: '0.5',
            },
            {
                clave: 'tasa_anual',
                label: 'Tasa anual del capital (%)',
                tipo: 'porcentaje',
                ayuda: 'Lo que rendiría ese capital puesto en otra cosa, o lo que cuesta el préstamo que lo financia.',
            },
        ],
    },
    combustible: {
        campos: [
            {
                clave: 'rendimiento_km_galon',
                label: 'Rendimiento (km por galón)',
                tipo: 'numero',
                step: '0.01',
            },
            {
                clave: 'aditivo_precio_galon',
                label: 'Aditivo: precio por galón (S/)',
                tipo: 'numero',
            },
            {
                clave: 'aditivo_rendimiento_km_galon',
                label: 'Aditivo: km por galón',
                tipo: 'numero',
            },
            {
                clave: 'precio_incluye_igv',
                label: 'Los precios incluyen IGV',
                tipo: 'booleano',
                ayuda: 'El IGV se recupera como crédito fiscal, así que se descuenta antes de costear.',
            },
        ],
        filas: {
            clave: 'localidades',
            titulo: 'Dónde se abastece la flota',
            agregar: 'Agregar localidad',
            columnas: [
                { clave: 'nombre', label: 'Localidad', tipo: 'texto' },
                {
                    clave: 'participacion',
                    label: 'Participación (%)',
                    tipo: 'porcentaje',
                },
                {
                    clave: 'precio_galon',
                    label: 'Precio por galón (S/)',
                    tipo: 'numero',
                },
            ],
            nueva: { nombre: '', participacion: 0, precio_galon: 0 },
        },
    },
    ciclo_vida: {
        campos: [],
        filas: {
            clave: 'vidas',
            titulo: 'Vidas sucesivas del mismo juego',
            agregar: 'Agregar vida',
            columnas: [
                { clave: 'concepto', label: 'Concepto', tipo: 'texto' },
                { clave: 'costo', label: 'Costo (S/)', tipo: 'numero' },
                { clave: 'km', label: 'Kilómetros que cubre', tipo: 'numero' },
            ],
            nueva: { concepto: '', costo: 0, km: 0 },
        },
    },
    frecuencia_km: {
        campos: [],
        filas: {
            clave: 'servicios',
            titulo: 'Servicios y cada cuánto se repiten',
            agregar: 'Agregar servicio',
            columnas: [
                { clave: 'concepto', label: 'Concepto', tipo: 'texto' },
                { clave: 'costo', label: 'Costo (S/)', tipo: 'numero' },
                { clave: 'cada_km', label: 'Cada cuántos km', tipo: 'numero' },
            ],
            nueva: { concepto: '', costo: 0, cada_km: 0 },
        },
    },
};

export function EntradasEditor({
    metodo,
    entradas,
    onChange,
    errors,
}: {
    metodo: string;
    entradas: EntradasComponente;
    onChange: (entradas: EntradasComponente) => void;
    errors: Record<string, string>;
}) {
    const formulario = FORMULARIOS[metodo];

    if (!formulario) {
        return null;
    }

    const cambiar = (clave: string, valor: ValorEntrada) =>
        onChange({ ...entradas, [clave]: valor });

    const filas = formulario.filas;
    const valoresFilas = filas
        ? ((entradas[filas.clave] as FilaEntrada[]) ?? [])
        : [];

    const cambiarFila = (
        indice: number,
        clave: string,
        valor: string | number | boolean,
    ) => {
        if (!filas) {
            return;
        }

        onChange({
            ...entradas,
            [filas.clave]: valoresFilas.map((fila, i) =>
                i === indice ? { ...fila, [clave]: valor } : fila,
            ),
        });
    };

    return (
        <div className="flex flex-col gap-4">
            {formulario.campos.length > 0 && (
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    {formulario.campos.map((campo) => (
                        <CampoEntrada
                            key={campo.clave}
                            campo={campo}
                            valor={entradas[campo.clave]}
                            error={errors[`entradas.${campo.clave}`]}
                            onChange={(valor) => cambiar(campo.clave, valor)}
                        />
                    ))}
                </div>
            )}

            {filas && (
                <div className="flex flex-col gap-2">
                    <p className="text-xs font-medium text-foreground">
                        {filas.titulo}
                    </p>

                    {valoresFilas.map((fila, indice) => (
                        <div
                            // Las filas no traen id y se reordenan solo al
                            // borrar: el índice alcanza como clave.
                            key={indice}
                            className="flex items-end gap-2 rounded-lg border border-border bg-background p-2"
                        >
                            {filas.columnas.map((columna) => (
                                <div key={columna.clave} className="flex-1">
                                    <CampoEntrada
                                        campo={columna}
                                        valor={fila[columna.clave]}
                                        error={
                                            errors[
                                                `entradas.${filas.clave}.${indice}.${columna.clave}`
                                            ]
                                        }
                                        onChange={(valor) =>
                                            cambiarFila(
                                                indice,
                                                columna.clave,
                                                valor,
                                            )
                                        }
                                    />
                                </div>
                            ))}
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                aria-label="Quitar fila"
                                disabled={valoresFilas.length <= 1}
                                onClick={() =>
                                    onChange({
                                        ...entradas,
                                        [filas.clave]: valoresFilas.filter(
                                            (_, i) => i !== indice,
                                        ),
                                    })
                                }
                            >
                                <Trash2 className="size-4" />
                            </Button>
                        </div>
                    ))}

                    <div>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() =>
                                onChange({
                                    ...entradas,
                                    [filas.clave]: [
                                        ...valoresFilas,
                                        { ...filas.nueva },
                                    ],
                                })
                            }
                        >
                            <Plus className="size-4" />
                            {filas.agregar}
                        </Button>
                    </div>

                    <InputError message={errors[`entradas.${filas.clave}`]} />
                </div>
            )}
        </div>
    );
}

function CampoEntrada({
    campo,
    valor,
    error,
    onChange,
}: {
    campo: Campo;
    valor: ValorEntrada | string | number | undefined;
    error?: string;
    onChange: (valor: string | number | boolean) => void;
}) {
    const id = useId();

    if (campo.tipo === 'booleano') {
        return (
            <div className="grid gap-1.5">
                <Label htmlFor={id}>{campo.label}</Label>
                <label className="flex items-center gap-2 text-sm">
                    <input
                        id={id}
                        type="checkbox"
                        className="size-4 rounded border-input"
                        checked={Boolean(valor)}
                        onChange={(e) => onChange(e.target.checked)}
                    />
                    <span className="text-muted-foreground">Sí</span>
                </label>
                {campo.ayuda && (
                    <p className="text-xs text-muted-foreground">
                        {campo.ayuda}
                    </p>
                )}
                <InputError message={error} />
            </div>
        );
    }

    if (campo.tipo === 'texto') {
        return (
            <div className="grid gap-1.5">
                <Label htmlFor={id}>{campo.label}</Label>
                <Input
                    id={id}
                    value={typeof valor === 'string' ? valor : ''}
                    onChange={(e) => onChange(e.target.value)}
                />
                <InputError message={error} />
            </div>
        );
    }

    // Los porcentajes se guardan en tanto por uno y se editan en tanto por
    // ciento, que es como se hablan: nadie dice «dedicación 0.75».
    const esPorcentaje = campo.tipo === 'porcentaje';
    const mostrado = esPorcentaje
        ? redondear(Number(valor ?? 0) * 100)
        : Number(valor ?? 0);

    return (
        <div className="grid gap-1.5">
            <Label htmlFor={id}>{campo.label}</Label>
            <Input
                id={id}
                type="number"
                step={campo.step ?? (esPorcentaje ? '0.01' : '0.01')}
                min={0}
                value={Number.isFinite(mostrado) ? mostrado : ''}
                onChange={(e) => {
                    const numero = Number(e.target.value);

                    onChange(esPorcentaje ? numero / 100 : numero);
                }}
            />
            {campo.ayuda && (
                <p className="text-xs text-muted-foreground">{campo.ayuda}</p>
            )}
            <InputError message={error} />
        </div>
    );
}

/**
 * Multiplicar por 100 en coma flotante deja restos como 74.99999999999999, que
 * en un input se ven como un error de carga.
 */
function redondear(valor: number): number {
    return Math.round(valor * 1e6) / 1e6;
}
