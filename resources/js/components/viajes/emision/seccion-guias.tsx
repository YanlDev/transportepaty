import { Plus } from 'lucide-react';
import { useState } from 'react';
import { guia as consultarGuia } from '@/actions/App/Http/Controllers/EmisionGreController';
import { Button } from '@/components/ui/button';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { consultar } from '@/components/viajes/emision/http';
import { Seccion, TarjetaGuia } from '@/components/viajes/emision/piezas';
import type {
    ClienteOpcion,
    GuiaRemitente,
} from '@/components/viajes/emision/tipos';

type Props = {
    clientes: ClienteOpcion[];
    sunatConfigurado: boolean;
    guias: GuiaRemitente[];
    onAgregada: (guia: GuiaRemitente) => void;
    onQuitar: (guia: GuiaRemitente) => void;
};

/**
 * Paso 1 de «Emitir GR»: se agrega cada GR-remitente por RUC, serie y número,
 * y SUNAT devuelve su contenido. La búsqueda (lo que se está escribiendo)
 * vive acá; la lista de guías agregadas es de la página.
 */
export function SeccionGuias({
    clientes,
    sunatConfigurado,
    guias,
    onAgregada,
    onQuitar,
}: Props) {
    const [ruc, setRuc] = useState('');
    const [serie, setSerie] = useState('');
    const [numero, setNumero] = useState('');
    const [buscandoGuia, setBuscandoGuia] = useState(false);
    const [errorGuia, setErrorGuia] = useState<string | null>(null);

    const agregarGuia = async (event: React.FormEvent) => {
        event.preventDefault();
        setErrorGuia(null);

        const serieNormal = serie.trim().toUpperCase();
        const numeroNormal = Number(numero);

        if (
            guias.some(
                (g) =>
                    g.ruc === ruc &&
                    g.serie === serieNormal &&
                    g.numero === numeroNormal,
            )
        ) {
            setErrorGuia('Esa GR-remitente ya está en la lista.');

            return;
        }

        setBuscandoGuia(true);

        try {
            const guia = await consultar<GuiaRemitente>(
                consultarGuia.url({
                    query: {
                        ruc: ruc.trim(),
                        serie: serieNormal,
                        numero: numero.trim(),
                    },
                }),
            );
            onAgregada(guia);

            setNumero('');
        } catch (error) {
            setErrorGuia((error as Error).message);
        } finally {
            setBuscandoGuia(false);
        }
    };

    return (
        <Seccion
            titulo="1. GR-remitente"
            descripcion="SUNAT completa remitente, destinatario, partida, llegada y peso."
        >
            <form onSubmit={agregarGuia} className="grid gap-4">
                <Field label="Cliente (remitente)" required>
                    {(id) => (
                        <div className="grid gap-2 sm:grid-cols-[1fr_10rem]">
                            <Select
                                value={
                                    clientes.some((c) => c.ruc === ruc)
                                        ? ruc
                                        : ''
                                }
                                onValueChange={setRuc}
                            >
                                <SelectTrigger id={id}>
                                    <SelectValue placeholder="Elegir cliente o escribir el RUC" />
                                </SelectTrigger>
                                <SelectContent>
                                    {clientes.map((cliente) => (
                                        <SelectItem
                                            key={cliente.ruc}
                                            value={cliente.ruc}
                                        >
                                            {cliente.alias} · {cliente.ruc}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <Input
                                aria-label="RUC del remitente"
                                inputMode="numeric"
                                maxLength={11}
                                placeholder="RUC"
                                value={ruc}
                                onChange={(e) =>
                                    setRuc(e.target.value.replace(/\D/g, ''))
                                }
                            />
                        </div>
                    )}
                </Field>

                <div className="grid grid-cols-[5.5rem_minmax(0,1fr)] items-end gap-2 sm:grid-cols-[6rem_minmax(0,1fr)_auto]">
                    <Field label="Serie" required>
                        {(id) => (
                            <Input
                                id={id}
                                maxLength={4}
                                placeholder="T007"
                                autoCapitalize="characters"
                                autoComplete="off"
                                value={serie}
                                onChange={(e) =>
                                    setSerie(e.target.value.toUpperCase())
                                }
                            />
                        )}
                    </Field>
                    <Field label="Número" required>
                        {(id) => (
                            <Input
                                id={id}
                                inputMode="numeric"
                                placeholder="10088"
                                autoComplete="off"
                                enterKeyHint="search"
                                value={numero}
                                onChange={(e) =>
                                    setNumero(e.target.value.replace(/\D/g, ''))
                                }
                            />
                        )}
                    </Field>
                    {/* En el celular va en su propia línea, a lo ancho. */}
                    <Button
                        className="col-span-2 sm:col-span-1"
                        type="submit"
                        disabled={
                            buscandoGuia ||
                            !sunatConfigurado ||
                            ruc.length !== 11 ||
                            serie.length !== 4 ||
                            numero === ''
                        }
                    >
                        {buscandoGuia ? (
                            <Spinner />
                        ) : (
                            <Plus className="size-4" />
                        )}
                        Agregar
                    </Button>
                </div>

                {errorGuia && (
                    <p className="text-sm text-destructive">{errorGuia}</p>
                )}
            </form>

            {guias.length > 0 && (
                <ul className="mt-4 grid gap-3">
                    {guias.map((guia) => (
                        <TarjetaGuia
                            key={`${guia.ruc}-${guia.serie}-${guia.numero}`}
                            guia={guia}
                            onQuitar={() => onQuitar(guia)}
                        />
                    ))}
                </ul>
            )}
        </Seccion>
    );
}
