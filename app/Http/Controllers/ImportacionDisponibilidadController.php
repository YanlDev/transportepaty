<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreImportacionRequest;
use App\Models\Importacion;
use App\Models\ImportacionFila;
use App\Services\ImportadorDisponibilidad;
use App\Services\LectorExcelDisponibilidad;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * Sube el Excel de disponibilidad de un día y lo deja en previsualización: cada
 * fila resuelta contra el catálogo, con lo que no se pudo resolver marcado y a
 * la vista. Nada toca el estado canónico hasta que se confirma explícitamente.
 */
class ImportacionDisponibilidadController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', Importacion::class);

        $importaciones = Importacion::query()
            ->with('usuario:id,name')
            ->orderByDesc('id')
            ->paginate(15)
            ->through(fn (Importacion $importacion): array => [
                'id' => $importacion->id,
                'fecha' => $importacion->fecha->toDateString(),
                'archivo_original' => $importacion->archivo_original,
                'usuario' => $importacion->usuario->name,
                'confirmada' => $importacion->estaConfirmada(),
                'filas_totales' => $importacion->filas_totales,
                'filas_resueltas' => $importacion->filas_resueltas,
                'creada' => $importacion->created_at->toDateTimeString(),
            ]);

        return Inertia::render('importaciones/index', ['importaciones' => $importaciones]);
    }

    public function create(): Response
    {
        $this->authorize('create', Importacion::class);

        return Inertia::render('importaciones/create');
    }

    public function store(StoreImportacionRequest $request): RedirectResponse
    {
        $archivo = $request->file('archivo');

        // Se lee antes de crear nada: si el archivo no sirve, no debe quedar
        // una importación vacía ni un archivo guardado sin filas que mostrar.
        try {
            $hoja = (new LectorExcelDisponibilidad)->leer($archivo->getRealPath());
        } catch (RuntimeException $excepcion) {
            return back()->withErrors(['archivo' => $excepcion->getMessage()]);
        }

        $fecha = $request->date('fecha')?->toDateString() ?? $this->fechaDelNombre($archivo->getClientOriginalName());

        $importacion = Importacion::query()->create([
            'fecha' => $fecha,
            'archivo_original' => $archivo->getClientOriginalName(),
            'subido_por' => Auth::id(),
        ]);

        // `preservingOriginal()` es obligatorio: sin él, MediaLibrary borra el
        // archivo de origen al copiarlo a la librería, que en producción es un
        // temporal de PHP sin problema, pero en pruebas es el fixture real del
        // repositorio.
        $importacion->addMedia($archivo)->preservingOriginal()->toMediaCollection('archivo');

        (new ImportadorDisponibilidad)->procesar($hoja, $importacion);

        return to_route('importaciones.show', $importacion)->with('toast', [
            'type' => 'success',
            'message' => "Se leyeron {$importacion->filas_totales} filas. Revisa la previsualización antes de confirmar.",
        ]);
    }

    public function show(Importacion $importacion): Response
    {
        $this->authorize('view', $importacion);

        $importacion->load([
            'filas.tracto:id,placa',
            'filas.carreta:id,placa',
            'filas.conductor:id,nombres,apellidos',
            'filas.origen:id,nombre',
            'filas.destino:id,nombre',
            'filas.ubicacion:id,nombre',
        ]);

        return Inertia::render('importaciones/show', [
            'importacion' => [
                'id' => $importacion->id,
                'fecha' => $importacion->fecha->toDateString(),
                'archivo_original' => $importacion->archivo_original,
                'confirmada' => $importacion->estaConfirmada(),
                'filas_totales' => $importacion->filas_totales,
                'filas_resueltas' => $importacion->filas_resueltas,
            ],
            'filas' => $importacion->filas->map(fn (ImportacionFila $fila): array => [
                'id' => $fila->id,
                'numero_fila' => $fila->numero_fila,
                'crudo' => $fila->crudo,
                'tracto' => $fila->tracto?->placa,
                'carreta' => $fila->carreta?->placa,
                'conductor' => $fila->conductor?->nombre_completo,
                'tipo_carga_label' => $fila->tipo_carga?->label(),
                'origen' => $fila->origen?->nombre,
                'destino' => $fila->destino?->nombre,
                'ubicacion' => $fila->ubicacion?->nombre,
                'observaciones' => $fila->observaciones,
                'problemas' => $fila->problemas ?? [],
                'incluir' => $fila->incluir,
                'puede_aplicarse' => $fila->puedeAplicarse(),
            ]),
        ]);
    }

    public function actualizarFila(Request $request, Importacion $importacion, ImportacionFila $fila): RedirectResponse
    {
        $this->authorize('update', $importacion);

        abort_if($fila->importacion_id !== $importacion->id, 404);
        abort_if($importacion->estaConfirmada(), 403, 'Esta importación ya fue confirmada.');

        $fila->update(['incluir' => $request->boolean('incluir')]);

        return back();
    }

    public function confirmar(Importacion $importacion): RedirectResponse
    {
        $this->authorize('update', $importacion);

        abort_if($importacion->estaConfirmada(), 403, 'Esta importación ya fue confirmada.');

        $aplicadas = (new ImportadorDisponibilidad)->confirmar($importacion);

        return to_route('disponibilidad.index', ['fecha' => $importacion->fecha->toDateString()])
            ->with('toast', [
                'type' => 'success',
                'message' => "Se aplicaron {$aplicadas} unidades a la disponibilidad del {$importacion->fecha->toDateString()}.",
            ]);
    }

    public function destroy(Importacion $importacion): RedirectResponse
    {
        $this->authorize('delete', $importacion);

        abort_if($importacion->estaConfirmada(), 403, 'Una importación confirmada no se puede eliminar: forma parte del historial.');

        $importacion->delete();

        return to_route('importaciones.index')->with('toast', [
            'type' => 'success',
            'message' => 'Importación descartada.',
        ]);
    }

    /**
     * El nombre del archivo trae la fecha, ej. «Disponibilidad de unidades
     * 29-07-2026 - Transportes Paty.xlsx». Si no se puede sacar, se cae al día
     * de hoy: es una ayuda, no algo de lo que dependa la importación.
     */
    private function fechaDelNombre(string $nombreArchivo): string
    {
        if (preg_match('/(\d{2})-(\d{2})-(\d{4})/', $nombreArchivo, $partes) === 1) {
            [$completo, $dia, $mes, $anio] = $partes;

            try {
                return Carbon::createFromDate((int) $anio, (int) $mes, (int) $dia)->toDateString();
            } catch (\Exception) {
                // Cae al día de hoy más abajo.
            }
        }

        return now()->toDateString();
    }
}
