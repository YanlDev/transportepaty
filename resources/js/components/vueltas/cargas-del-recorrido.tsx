import { Check, Lock, Pencil, Plus, X } from 'lucide-react';
import { CampoSelector } from '@/components/selector-en-dialogo';
import type { OpcionBuscable } from '@/components/selector-en-dialogo';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { ParadaForm } from '@/components/vueltas/programar-vuelta-dialog';
import { cn } from '@/lib/utils';

type Sentido = 'ida' | 'regreso';

type Opcion = { value: string; label: string };

/** Cómo se lee cada parada en el recorrido, en el mismo orden. */
type Texto = { desde: string; hasta: string; carga: string };

const NOMBRE_TRAMO: Record<Sentido, string> = { ida: 'Ida', regreso: 'Vuelta' };

function SelectOpciones({
    valor,
    opciones,
    etiqueta,
    invalido = false,
    onCambiar,
}: {
    valor: string;
    opciones: Opcion[];
    etiqueta: string;
    invalido?: boolean;
    onCambiar: (valor: string) => void;
}) {
    return (
        <Select value={valor || undefined} onValueChange={onCambiar}>
            <SelectTrigger
                className="w-full"
                aria-label={etiqueta}
                aria-invalid={invalido}
            >
                <SelectValue placeholder="Elegir…" />
            </SelectTrigger>
            <SelectContent>
                {opciones.map((opcion) => (
                    <SelectItem key={opcion.value} value={opcion.value}>
                        {opcion.label}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}

/**
 * Las cargas del ticket, una por fila, en el orden en que se recorren. Lo
 * que ya tiene GR queda fijo; los vacíos que unen dos cargas (o cierran la
 * vuelta en Juliaca) los pone el formulario y no se escriben. Para lo que
 * no sale de una ruta de la tabla se agrega una carga con tres datos: dónde
 * carga, a dónde va y de quién es.
 */
export function CargasDelRecorrido({
    paradas,
    textos,
    zonas,
    cargas,
    clientes,
    errores,
    editando,
    onEditar,
    onCambiar,
    onQuitar,
    onAgregar,
    onElegirCliente,
}: {
    paradas: ParadaForm[];
    textos: Texto[];
    zonas: Opcion[];
    cargas: Opcion[];
    clientes: OpcionBuscable[];
    errores: Record<string, string | undefined>;
    /** La clave de la carga que se está editando. */
    editando: string | null;
    onEditar: (clave: string | null) => void;
    onCambiar: (clave: string, cambios: Partial<ParadaForm>) => void;
    onQuitar: (clave: string) => void;
    onAgregar: (sentido: Sentido) => void;
    /** Abre el buscador del padrón para esa carga. */
    onElegirCliente: (clave: string) => void;
}) {
    const fila = (parada: ParadaForm, indice: number) => {
        const texto = textos[indice];
        const trayecto = `${texto.desde} → ${texto.hasta}`;

        if (parada.bloqueada) {
            return (
                <li
                    key={parada.clave}
                    className="flex items-center gap-2 rounded-md border bg-muted/40 px-3 py-2 text-xs text-muted-foreground"
                >
                    <Lock className="size-3.5 shrink-0" />
                    <span>
                        {trayecto} · {texto.carga}
                    </span>
                    <span className="ml-auto">con GR</span>
                </li>
            );
        }

        if (parada.auto) {
            return (
                <li
                    key={parada.clave}
                    className="rounded-md border border-dashed px-3 py-2 text-xs text-muted-foreground italic"
                >
                    {texto.desde} ⇢ {texto.hasta} · vacía (se calcula sola)
                </li>
            );
        }

        const cliente =
            clientes.find((opcion) => opcion.valor === parada.cliente_id)
                ?.etiqueta ?? parada.cliente;
        const esVacio = parada.tipo_carga === 'vacio';

        if (editando !== parada.clave) {
            return (
                <li
                    key={parada.clave}
                    className="flex items-center gap-2 rounded-md border px-3 py-1.5 text-sm"
                >
                    <span className="min-w-0 flex-1 truncate">
                        {trayecto}
                        <span className="text-muted-foreground">
                            {' '}
                            · {esVacio ? 'vacía' : texto.carga}
                            {cliente && ` · ${cliente}`}
                        </span>
                    </span>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={() => {
                            // Un vacío se abre para decir qué carga consiguió.
                            if (esVacio) {
                                onCambiar(parada.clave, {
                                    tipo_carga: 'particular',
                                });
                            }

                            onEditar(parada.clave);
                        }}
                    >
                        <Pencil className="size-3.5" />
                        {esVacio ? 'Consiguió carga' : 'Cambiar'}
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        aria-label={`Quitar ${trayecto}`}
                        onClick={() => onQuitar(parada.clave)}
                    >
                        <X className="size-4" />
                    </Button>
                </li>
            );
        }

        return (
            <li
                key={parada.clave}
                className="flex flex-col gap-3 rounded-lg border border-primary/40 bg-primary/5 p-3"
            >
                <div className="grid gap-3 sm:grid-cols-2">
                    <div className="flex flex-col gap-1.5">
                        <span className="text-xs font-medium">
                            ¿Dónde carga?
                        </span>
                        <SelectOpciones
                            valor={parada.zona_origen}
                            opciones={zonas}
                            etiqueta="Zona donde carga"
                            invalido={Boolean(
                                errores[`paradas.${indice}.zona_origen`],
                            )}
                            onCambiar={(valor) =>
                                onCambiar(parada.clave, {
                                    zona_origen: valor,
                                    lugar_origen: null,
                                })
                            }
                        />
                        <Input
                            value={parada.lugar_origen ?? ''}
                            maxLength={100}
                            aria-label="Lugar exacto donde carga"
                            placeholder="Lugar exacto (opcional): Uraca, Ilo…"
                            onChange={(evento) =>
                                onCambiar(parada.clave, {
                                    lugar_origen: evento.target.value || null,
                                })
                            }
                        />
                    </div>
                    <div className="flex flex-col gap-1.5">
                        <span className="text-xs font-medium">
                            ¿A dónde va?
                        </span>
                        <SelectOpciones
                            valor={parada.zona_destino}
                            opciones={zonas}
                            etiqueta="Zona a donde va"
                            invalido={Boolean(
                                errores[`paradas.${indice}.zona_destino`],
                            )}
                            onCambiar={(valor) =>
                                onCambiar(parada.clave, {
                                    zona_destino: valor,
                                    lugar_destino: null,
                                })
                            }
                        />
                        <Input
                            value={parada.lugar_destino ?? ''}
                            maxLength={100}
                            aria-label="Lugar exacto a donde va"
                            placeholder="Lugar exacto (opcional)"
                            onChange={(evento) =>
                                onCambiar(parada.clave, {
                                    lugar_destino: evento.target.value || null,
                                })
                            }
                        />
                    </div>
                    <div className="flex flex-col gap-1.5">
                        <span className="text-xs font-medium">¿Qué lleva?</span>
                        <SelectOpciones
                            valor={parada.tipo_carga}
                            opciones={cargas}
                            etiqueta="Carga"
                            onCambiar={(valor) =>
                                onCambiar(
                                    parada.clave,
                                    valor === 'vacio'
                                        ? {
                                              tipo_carga: valor,
                                              cliente_id: null,
                                              cliente: null,
                                          }
                                        : { tipo_carga: valor },
                                )
                            }
                        />
                    </div>
                    {!esVacio && (
                        <div className="flex flex-col gap-1.5">
                            <span className="text-xs font-medium">
                                ¿De quién es?
                            </span>
                            <CampoSelector
                                etiqueta="Cliente del padrón (opcional)"
                                elegida={clientes.find(
                                    (opcion) =>
                                        opcion.valor === parada.cliente_id,
                                )}
                                onAbrir={() => onElegirCliente(parada.clave)}
                            />
                            {parada.cliente_id === null && (
                                <Input
                                    value={parada.cliente ?? ''}
                                    maxLength={150}
                                    aria-label="Cliente que no está en la lista"
                                    placeholder="O escríbelo si no está en la lista"
                                    onChange={(evento) =>
                                        onCambiar(parada.clave, {
                                            cliente:
                                                evento.target.value || null,
                                        })
                                    }
                                />
                            )}
                        </div>
                    )}
                </div>
                <div className="flex justify-end gap-2">
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={() => {
                            onEditar(null);
                            onQuitar(parada.clave);
                        }}
                    >
                        Quitar
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        onClick={() => onEditar(null)}
                    >
                        <Check className="size-3.5" />
                        Listo
                    </Button>
                </div>
            </li>
        );
    };

    return (
        <div className="grid gap-4 md:grid-cols-2">
            {(['ida', 'regreso'] as const).map((sentido) => (
                <section key={sentido} className="flex flex-col gap-2">
                    <h4
                        className={cn(
                            'text-xs font-semibold',
                            sentido === 'ida'
                                ? 'text-amber-700 dark:text-amber-400'
                                : 'text-violet-700 dark:text-violet-400',
                        )}
                    >
                        {NOMBRE_TRAMO[sentido]}
                    </h4>
                    <ul className="flex flex-col gap-1.5">
                        {paradas.map((parada, indice) =>
                            parada.sentido === sentido
                                ? fila(parada, indice)
                                : null,
                        )}
                    </ul>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        className="self-start"
                        onClick={() => onAgregar(sentido)}
                    >
                        <Plus className="size-4" />
                        Agregar carga a la {NOMBRE_TRAMO[sentido].toLowerCase()}
                    </Button>
                </section>
            ))}
        </div>
    );
}
