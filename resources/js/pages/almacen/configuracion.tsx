import { Head, router, setLayoutProps, useForm } from '@inertiajs/react';
import {
    Check,
    FolderTree,
    MapPin,
    Pencil,
    Plus,
    Settings,
    Trash2,
    TriangleAlert,
    X,
} from 'lucide-react';
import { useState } from 'react';
import {
    crearCategoria,
    crearUbicacion,
    editarUbicacion,
    guardarMinimos,
    index as configuracionAlmacen,
    quitarCategoria,
    quitarUbicacion,
    renombrarCategoria,
} from '@/actions/App/Http/Controllers/Almacen/ConfiguracionAlmacenController';
import { migasAlmacen } from '@/components/almacen/almacen-nav';
import { EncabezadoPantalla } from '@/components/encabezado-pantalla';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialogo-responsivo';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { formatearCantidad } from '@/lib/almacen';
import { cn } from '@/lib/utils';

type Grupo = {
    id: number;
    nombre: string;
    descripcion?: string | null;
    articulos: number;
};

type ArticuloMinimo = {
    id: number;
    codigo: string;
    nombre: string;
    categoria_id: number | null;
    ubicacion_id: number | null;
    unidad: string;
    stock: number;
    stock_minimo: number | null;
};

type Props = {
    categorias: Grupo[];
    ubicaciones: Grupo[];
    articulos: ArticuloMinimo[];
};

type Seccion = 'categorias' | 'ubicaciones' | 'minimos';

/**
 * Lo que ordena el almacén: categorías, estantes y mínimos. Lo que se quita
 * no deja artículos huérfanos sin avisar: se elige a dónde pasan.
 */
export default function ConfiguracionAlmacen({
    categorias,
    ubicaciones,
    articulos,
}: Props) {
    const [seccion, setSeccion] = useState<Seccion>('categorias');

    setLayoutProps({
        breadcrumbs: migasAlmacen({
            title: 'Configuración',
            href: configuracionAlmacen().url,
        }),
    });

    return (
        <div className="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-6 p-4 md:p-6">
            <Head title="Configuración del almacén" />

            <EncabezadoPantalla
                titulo="Configuración del almacén"
                descripcion="Las categorías de los artículos, los estantes donde se guardan y los mínimos de stock."
                icono={Settings}
            />

            <ToggleGroup
                type="single"
                variant="outline"
                value={seccion}
                onValueChange={(valor) => valor && setSeccion(valor as Seccion)}
                className="w-full justify-start overflow-x-auto"
            >
                <ToggleGroupItem value="categorias" className="px-4">
                    <FolderTree className="size-4" />
                    Categorías ({categorias.length})
                </ToggleGroupItem>
                <ToggleGroupItem value="ubicaciones" className="px-4">
                    <MapPin className="size-4" />
                    Ubicaciones ({ubicaciones.length})
                </ToggleGroupItem>
                <ToggleGroupItem value="minimos" className="px-4">
                    <TriangleAlert className="size-4" />
                    Mínimos
                </ToggleGroupItem>
            </ToggleGroup>

            {seccion === 'categorias' && (
                <ListaGrupos
                    tipo="categoría"
                    grupos={categorias}
                    crearUrl={crearCategoria().url}
                    editarUrl={(id) => renombrarCategoria(id).url}
                    quitarUrl={(id) => quitarCategoria(id).url}
                    placeholder="Nueva categoría: Embrague, Aire acondicionado…"
                />
            )}

            {seccion === 'ubicaciones' && (
                <ListaGrupos
                    tipo="ubicación"
                    grupos={ubicaciones}
                    crearUrl={crearUbicacion().url}
                    editarUrl={(id) => editarUbicacion(id).url}
                    quitarUrl={(id) => quitarUbicacion(id).url}
                    placeholder="Nuevo estante o zona: A-1, Patio de llantas…"
                    conDescripcion
                />
            )}

            {seccion === 'minimos' && (
                <Minimos
                    articulos={articulos}
                    categorias={categorias}
                    ubicaciones={ubicaciones}
                />
            )}
        </div>
    );
}

/**
 * Una lista de categorías o de ubicaciones: agregar, renombrar y quitar.
 */
function ListaGrupos({
    tipo,
    grupos,
    crearUrl,
    editarUrl,
    quitarUrl,
    placeholder,
    conDescripcion = false,
}: {
    tipo: 'categoría' | 'ubicación';
    grupos: Grupo[];
    crearUrl: string;
    editarUrl: (id: number) => string;
    quitarUrl: (id: number) => string;
    placeholder: string;
    conDescripcion?: boolean;
}) {
    const nuevo = useForm({ nombre: '', descripcion: '' });

    return (
        <section className="flex flex-col gap-4">
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    nuevo.post(crearUrl, {
                        preserveScroll: true,
                        onSuccess: () => nuevo.reset(),
                    });
                }}
                className="flex flex-col gap-2 rounded-xl border bg-card p-4 sm:flex-row sm:items-start"
            >
                <div className="flex-1">
                    <Input
                        value={nuevo.data.nombre}
                        onChange={(e) =>
                            nuevo.setData('nombre', e.target.value)
                        }
                        placeholder={placeholder}
                        aria-label={`Nombre de la ${tipo}`}
                    />
                    <InputError message={nuevo.errors.nombre} />
                </div>
                {conDescripcion && (
                    <Input
                        value={nuevo.data.descripcion}
                        onChange={(e) =>
                            nuevo.setData('descripcion', e.target.value)
                        }
                        placeholder="Descripción (opcional)"
                        className="sm:max-w-xs"
                    />
                )}
                <Button
                    type="submit"
                    disabled={nuevo.processing || !nuevo.data.nombre.trim()}
                >
                    {nuevo.processing ? (
                        <Spinner />
                    ) : (
                        <Plus className="size-4" />
                    )}
                    Agregar
                </Button>
            </form>

            {grupos.length === 0 ? (
                <p className="rounded-xl border border-dashed p-6 text-center text-sm text-muted-foreground">
                    Todavía no hay ninguna. Agrégalas arriba o al crear un
                    artículo.
                </p>
            ) : (
                <ul className="flex flex-col divide-y rounded-xl border bg-card">
                    {grupos.map((grupo) => (
                        <FilaGrupo
                            key={grupo.id}
                            tipo={tipo}
                            grupo={grupo}
                            otros={grupos.filter((g) => g.id !== grupo.id)}
                            editarUrl={editarUrl(grupo.id)}
                            quitarUrl={quitarUrl(grupo.id)}
                            conDescripcion={conDescripcion}
                        />
                    ))}
                </ul>
            )}
        </section>
    );
}

function FilaGrupo({
    tipo,
    grupo,
    otros,
    editarUrl,
    quitarUrl,
    conDescripcion,
}: {
    tipo: 'categoría' | 'ubicación';
    grupo: Grupo;
    otros: Grupo[];
    editarUrl: string;
    quitarUrl: string;
    conDescripcion: boolean;
}) {
    const [editando, setEditando] = useState(false);
    const form = useForm({
        nombre: grupo.nombre,
        descripcion: grupo.descripcion ?? '',
    });

    if (editando) {
        return (
            <li>
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.put(editarUrl, {
                            preserveScroll: true,
                            onSuccess: () => setEditando(false),
                        });
                    }}
                    className="flex flex-col gap-2 p-3 sm:flex-row sm:items-start"
                >
                    <div className="flex-1">
                        <Input
                            autoFocus
                            value={form.data.nombre}
                            onChange={(e) =>
                                form.setData('nombre', e.target.value)
                            }
                            aria-label="Nombre"
                        />
                        <InputError message={form.errors.nombre} />
                    </div>
                    {conDescripcion && (
                        <Input
                            value={form.data.descripcion}
                            onChange={(e) =>
                                form.setData('descripcion', e.target.value)
                            }
                            placeholder="Descripción"
                            className="sm:max-w-xs"
                        />
                    )}
                    <div className="flex gap-1">
                        <Button
                            type="submit"
                            size="icon"
                            aria-label="Guardar"
                            disabled={form.processing}
                        >
                            <Check className="size-4" />
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            aria-label="Cancelar"
                            onClick={() => {
                                form.reset();
                                form.clearErrors();
                                setEditando(false);
                            }}
                        >
                            <X className="size-4" />
                        </Button>
                    </div>
                </form>
            </li>
        );
    }

    return (
        <li className="flex items-center gap-3 p-3">
            <div className="min-w-0 flex-1">
                <p className="font-medium">{grupo.nombre}</p>
                <p className="text-xs text-muted-foreground">
                    {grupo.articulos === 0
                        ? 'Sin artículos'
                        : grupo.articulos === 1
                          ? '1 artículo'
                          : `${grupo.articulos} artículos`}
                    {grupo.descripcion && ` · ${grupo.descripcion}`}
                </p>
            </div>
            <Button
                variant="ghost"
                size="icon"
                aria-label={`Renombrar ${grupo.nombre}`}
                onClick={() => setEditando(true)}
            >
                <Pencil className="size-4" />
            </Button>
            <QuitarGrupoDialog
                tipo={tipo}
                grupo={grupo}
                otros={otros}
                url={quitarUrl}
            />
        </li>
    );
}

/**
 * Quitar una categoría o ubicación. Si tiene artículos, se elige a cuál pasan
 * (es la forma de fusionar dos) o se dejan sin asignar.
 */
function QuitarGrupoDialog({
    tipo,
    grupo,
    otros,
    url,
}: {
    tipo: 'categoría' | 'ubicación';
    grupo: Grupo;
    otros: Grupo[];
    url: string;
}) {
    const [abierto, setAbierto] = useState(false);
    const [destino, setDestino] = useState('');
    const [procesando, setProcesando] = useState(false);

    const quitar = () =>
        router.delete(url, {
            data: { mover_a: destino === '' ? null : Number(destino) },
            preserveScroll: true,
            onStart: () => setProcesando(true),
            onFinish: () => setProcesando(false),
            onSuccess: () => setAbierto(false),
        });

    return (
        <Dialog open={abierto} onOpenChange={setAbierto}>
            <DialogTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    aria-label={`Quitar ${grupo.nombre}`}
                    className="text-destructive"
                >
                    <Trash2 className="size-4" />
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Quitar «{grupo.nombre}»</DialogTitle>
                    <DialogDescription>
                        {grupo.articulos === 0
                            ? `No tiene artículos: se quita sin más.`
                            : `Tiene ${grupo.articulos === 1 ? '1 artículo' : `${grupo.articulos} artículos`}. Elige a qué ${tipo} pasan; así se fusionan las dos.`}
                    </DialogDescription>
                </DialogHeader>
                {grupo.articulos > 0 && (
                    <div className="grid gap-1.5 py-4">
                        <Label htmlFor={`destino-${grupo.id}`}>
                            Pasarlos a
                        </Label>
                        <select
                            id={`destino-${grupo.id}`}
                            value={destino}
                            onChange={(e) => setDestino(e.target.value)}
                            className="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                        >
                            <option value="">Dejarlos sin {tipo}</option>
                            {otros.map((otro) => (
                                <option key={otro.id} value={otro.id}>
                                    {otro.nombre}
                                </option>
                            ))}
                        </select>
                    </div>
                )}
                <DialogFooter>
                    <Button
                        variant="destructive"
                        onClick={quitar}
                        disabled={procesando}
                    >
                        {procesando && <Spinner />}
                        Quitar
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

/**
 * Los mínimos en bloque: se filtra por categoría o estante, se escriben los
 * que hagan falta y se guardan todos juntos. Vacío quita el mínimo.
 */
function Minimos({
    articulos,
    categorias,
    ubicaciones,
}: {
    articulos: ArticuloMinimo[];
    categorias: Grupo[];
    ubicaciones: Grupo[];
}) {
    const [categoria, setCategoria] = useState('');
    const [ubicacion, setUbicacion] = useState('');
    const [buscar, setBuscar] = useState('');
    const [cambios, setCambios] = useState<Record<number, string>>({});
    const [procesando, setProcesando] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const texto = buscar.trim().toLowerCase();
    const visibles = articulos.filter(
        (a) =>
            (categoria === '' ||
                (categoria === 'sin'
                    ? a.categoria_id === null
                    : a.categoria_id === Number(categoria))) &&
            (ubicacion === '' ||
                (ubicacion === 'sin'
                    ? a.ubicacion_id === null
                    : a.ubicacion_id === Number(ubicacion))) &&
            (texto === '' ||
                `${a.nombre} ${a.codigo}`.toLowerCase().includes(texto)),
    );
    const pendientes = Object.keys(cambios).length;

    const guardar = () =>
        router.put(
            guardarMinimos().url,
            {
                minimos: Object.entries(cambios).map(([id, valor]) => ({
                    id: Number(id),
                    stock_minimo: valor.trim() === '' ? null : valor,
                })),
            },
            {
                preserveScroll: true,
                onStart: () => {
                    setProcesando(true);
                    setError(null);
                },
                onFinish: () => setProcesando(false),
                onSuccess: () => setCambios({}),
                onError: () =>
                    setError('Revisa los mínimos: solo números desde 0.'),
            },
        );

    return (
        <section className="flex flex-col gap-4">
            <div className="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
                <Input
                    value={buscar}
                    onChange={(e) => setBuscar(e.target.value)}
                    placeholder="Buscar artículo..."
                    className="sm:max-w-xs"
                />
                <select
                    aria-label="Categoría"
                    value={categoria}
                    onChange={(e) => setCategoria(e.target.value)}
                    className="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                >
                    <option value="">Todas las categorías</option>
                    {categorias.map((c) => (
                        <option key={c.id} value={c.id}>
                            {c.nombre}
                        </option>
                    ))}
                    <option value="sin">Sin categoría</option>
                </select>
                {ubicaciones.length > 0 && (
                    <select
                        aria-label="Ubicación"
                        value={ubicacion}
                        onChange={(e) => setUbicacion(e.target.value)}
                        className="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                    >
                        <option value="">Todas las ubicaciones</option>
                        {ubicaciones.map((u) => (
                            <option key={u.id} value={u.id}>
                                {u.nombre}
                            </option>
                        ))}
                        <option value="sin">Sin ubicación</option>
                    </select>
                )}
                <Button
                    className="sm:ml-auto"
                    onClick={guardar}
                    disabled={pendientes === 0 || procesando}
                >
                    {procesando && <Spinner />}
                    {pendientes === 0
                        ? 'Sin cambios'
                        : `Guardar ${pendientes} ${pendientes === 1 ? 'mínimo' : 'mínimos'}`}
                </Button>
            </div>
            <InputError message={error ?? undefined} />

            {visibles.length === 0 ? (
                <p className="rounded-xl border border-dashed p-6 text-center text-sm text-muted-foreground">
                    No hay artículos con ese filtro.
                </p>
            ) : (
                <div className="overflow-x-auto rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Artículo</TableHead>
                                <TableHead className="text-right">
                                    Stock
                                </TableHead>
                                <TableHead className="w-36 text-right">
                                    Mínimo
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {visibles.map((articulo) => {
                                const valor =
                                    cambios[articulo.id] ??
                                    (articulo.stock_minimo === null
                                        ? ''
                                        : String(articulo.stock_minimo));
                                const minimo =
                                    valor.trim() === '' ? null : Number(valor);
                                const bajo =
                                    minimo !== null && articulo.stock <= minimo;

                                return (
                                    <TableRow key={articulo.id}>
                                        <TableCell>
                                            <p className="font-medium">
                                                {articulo.nombre}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {articulo.codigo}
                                            </p>
                                        </TableCell>
                                        <TableCell
                                            className={cn(
                                                'text-right tabular-nums',
                                                bajo &&
                                                    'font-semibold text-amber-700 dark:text-amber-400',
                                            )}
                                        >
                                            {formatearCantidad(
                                                articulo.stock,
                                                articulo.unidad,
                                            )}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <Input
                                                inputMode="decimal"
                                                value={valor}
                                                onChange={(e) =>
                                                    setCambios({
                                                        ...cambios,
                                                        [articulo.id]:
                                                            e.target.value,
                                                    })
                                                }
                                                placeholder="Sin mínimo"
                                                aria-label={`Mínimo de ${articulo.nombre}`}
                                                className={cn(
                                                    'ml-auto h-8 w-28 text-right',
                                                    cambios[articulo.id] !==
                                                        undefined &&
                                                        'border-primary',
                                                )}
                                            />
                                        </TableCell>
                                    </TableRow>
                                );
                            })}
                        </TableBody>
                    </Table>
                </div>
            )}
        </section>
    );
}
