<?php

namespace App\Services;

use App\Enums\Cliente;
use App\Enums\OrigenDato;
use App\Enums\TipoCarga;
use App\Enums\TipoVehiculo;
use App\Models\Conductor;
use App\Models\EstadoUnidad;
use App\Models\Importacion;
use App\Models\ImportacionFila;
use App\Models\Ubicacion;
use App\Models\Vehiculo;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Convierte una hoja ya leída en filas de previsualización, resolviendo cada
 * celda contra el catálogo: tractos y carretas por placa, conductores por
 * nombre, ubicaciones por el catálogo con sus alias, y la carga contra el
 * enum. Lo que no se resuelve se guarda igual, con el texto crudo y el motivo,
 * para que la fila se pueda corregir a mano antes de confirmar.
 *
 * No escribe nada en el estado canónico hasta que `confirmar()` se llama
 * explícitamente: mientras tanto todo es previsualización.
 */
final class ImportadorDisponibilidad
{
    /**
     * Columnas del reporte, por su etiqueta de cabecera tal como aparecen en el
     * Excel real.
     */
    private const COL_CODIGO = 'CODIGO';

    private const COL_CARRETA = 'CARRETA';

    private const COL_CONDUCTOR = 'CONDUCTOR';

    private const COL_TIPO_CARGA = 'Tipo Carga';

    private const COL_CLIENTE = 'Estado Unidad';

    private const COL_RUTA = 'Ruta';

    private const COL_UBICACION = 'Ubicación (09:30 am)';

    private const COL_OBSERVACIONES = 'Observaciones';

    /**
     * @var Collection<int, Vehiculo>
     */
    private Collection $vehiculos;

    /**
     * @var Collection<int, Conductor>
     */
    private Collection $conductores;

    public function __construct()
    {
        $this->vehiculos = Vehiculo::query()->get(['id', 'placa', 'tipo']);
        $this->conductores = Conductor::query()->where('activo', true)->get(['id', 'nombres', 'apellidos']);
    }

    /**
     * Procesa la hoja y guarda una fila de previsualización por cada fila de
     * datos del Excel. No toca el estado canónico.
     */
    public function procesar(HojaExcelLeida $hoja, Importacion $importacion): void
    {
        $numeros = $hoja->numerosDeFilaConDatos();

        foreach ($numeros as $numero) {
            $this->procesarFila($hoja, $importacion, $numero);
        }

        // No son campos de formulario: se asignan directo porque no están en la
        // lista de fillable, que solo cubre lo que llega de fuera.
        $importacion->filas_totales = count($numeros);
        $importacion->filas_resueltas = $importacion->filas()->whereNotNull('tracto_id')->count();
        $importacion->save();
    }

    private function procesarFila(HojaExcelLeida $hoja, Importacion $importacion, int $numero): void
    {
        $crudo = $this->crudoDeLaFila($hoja, $numero);
        $problemas = [];

        $tracto = $this->resolverVehiculo($crudo[self::COL_CODIGO] ?? null, TipoVehiculo::Tracto);
        if (($crudo[self::COL_CODIGO] ?? '') !== '' && $tracto === null) {
            $problemas[] = "Tracto «{$crudo[self::COL_CODIGO]}» no está en la flota registrada.";
        }

        $carreta = $this->resolverVehiculo($crudo[self::COL_CARRETA] ?? null, TipoVehiculo::Carreta);
        if (($crudo[self::COL_CARRETA] ?? '') !== '' && $carreta === null) {
            $problemas[] = "Carreta «{$crudo[self::COL_CARRETA]}» no está en la flota registrada.";
        }

        $conductorTexto = trim((string) ($crudo[self::COL_CONDUCTOR] ?? ''));
        $conductor = null;

        if ($conductorTexto !== '' && $conductorTexto !== '0') {
            $conductor = $this->resolverConductor($conductorTexto);

            if ($conductor === null) {
                $problemas[] = "Conductor «{$conductorTexto}» no se reconoce entre los conductores activos.";
            }
        }

        $tipoCargaTexto = trim((string) ($crudo[self::COL_TIPO_CARGA] ?? ''));
        $tipoCarga = $this->resolverTipoCarga($tipoCargaTexto);

        if ($tipoCargaTexto !== '' && $tipoCarga === null) {
            if (str_contains($tipoCargaTexto, '=>')) {
                $problemas[] = "La columna de carga trae una ruta («{$tipoCargaTexto}»): parece una columna corrida.";
            } else {
                $problemas[] = "«{$tipoCargaTexto}» no es un tipo de carga reconocido.";
            }
        }

        $this->detectarClienteEnColumnaCorrida($crudo[self::COL_CLIENTE] ?? null, $problemas);

        [$origen, $destino] = $this->resolverRuta($crudo[self::COL_RUTA] ?? null, $problemas);

        $ubicacionTexto = trim((string) ($crudo[self::COL_UBICACION] ?? ''));
        $ubicacion = null;

        if ($ubicacionTexto !== '' && ! Ubicacion::esTextoSinUbicacion($ubicacionTexto)) {
            $ubicacion = Ubicacion::buscarPorNombre($ubicacionTexto);

            if ($ubicacion === null) {
                $problemas[] = "Ubicación «{$ubicacionTexto}» no está en el catálogo.";
            }
        } elseif (Ubicacion::esTextoSinUbicacion($ubicacionTexto)) {
            $problemas[] = "«{$ubicacionTexto}» no es una ubicación: revisar si corresponde registrar una novedad.";
        }

        ImportacionFila::query()->create([
            'importacion_id' => $importacion->id,
            'numero_fila' => $numero,
            'crudo' => $crudo,
            'tracto_id' => $tracto?->id,
            'carreta_id' => $carreta?->id,
            'conductor_id' => $conductor?->id,
            'tipo_carga' => $tipoCarga,
            'origen_id' => $origen?->id,
            'destino_id' => $destino?->id,
            'ubicacion_id' => $ubicacion?->id,
            'observaciones' => trim((string) ($crudo[self::COL_OBSERVACIONES] ?? '')) ?: null,
            'problemas' => $problemas,
            // Sin tracto no hay a quién aplicarle la fila: se destilda para que
            // el usuario decida, en vez de que confirmar falle a medias.
            'incluir' => $tracto !== null,
        ]);
    }

    /**
     * Aplica las filas incluidas al estado canónico del día de la importación.
     * Respeta lo que ya estaba confirmado a mano: una reimportación nunca pisa
     * una corrección humana.
     *
     * @return int Cuántas filas se aplicaron.
     */
    public function confirmar(Importacion $importacion): int
    {
        $aplicadas = 0;

        DB::transaction(function () use ($importacion, &$aplicadas): void {
            foreach ($importacion->filas as $fila) {
                if (! $fila->puedeAplicarse()) {
                    continue;
                }

                $estado = $this->aplicarFila($fila, $importacion->fecha->toDateString());
                $fila->update(['estado_unidad_id' => $estado->id]);
                $aplicadas++;
            }

            $importacion->marcarConfirmada();
        });

        return $aplicadas;
    }

    private function aplicarFila(ImportacionFila $fila, string $fecha): EstadoUnidad
    {
        $estado = EstadoUnidad::query()->firstOrNew([
            'tracto_id' => $fila->tracto_id,
            'fecha' => $fecha,
        ]);

        $valores = [
            'carreta_id' => $fila->carreta_id,
            'conductor_id' => $fila->conductor_id,
            'tipo_carga' => $fila->tipo_carga,
            'origen_id' => $fila->origen_id,
            'destino_id' => $fila->destino_id,
            'ubicacion_id' => $fila->ubicacion_id,
            'observaciones' => $fila->observaciones,
        ];

        foreach ($valores as $campo => $valor) {
            if ($valor === null || ! $estado->admiteSobrescritura($campo)) {
                continue;
            }

            $estado->setAttribute($campo, $valor);
            $estado->marcarOrigen($campo, OrigenDato::Importado);
        }

        $estado->save();

        return $estado;
    }

    /**
     * @return array<string, string>
     */
    private function crudoDeLaFila(HojaExcelLeida $hoja, int $numero): array
    {
        $crudo = [];

        foreach ($hoja->columnas as $letra => $etiqueta) {
            $valor = $hoja->filas[$numero][$letra] ?? null;

            if ($valor !== null && $valor !== '') {
                $crudo[$etiqueta] = $valor;
            }
        }

        return $crudo;
    }

    private function resolverVehiculo(?string $placa, TipoVehiculo $tipo): ?Vehiculo
    {
        if ($placa === null || $placa === '' || $placa === '0') {
            return null;
        }

        $normalizada = $this->normalizarPlaca($placa);

        return $this->vehiculos->first(
            fn (Vehiculo $vehiculo): bool => $vehiculo->tipo === $tipo
                && $this->normalizarPlaca($vehiculo->placa) === $normalizada,
        );
    }

    private function normalizarPlaca(string $placa): string
    {
        return mb_strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $placa) ?? '');
    }

    /**
     * Los reportes escriben «Apellidos Nombres», así que se prueban las dos
     * combinaciones contra cada conductor activo antes de darlo por no
     * reconocido.
     */
    private function resolverConductor(string $texto): ?Conductor
    {
        $normalizado = Ubicacion::normalizar($texto);

        return $this->conductores->first(function (Conductor $conductor) use ($normalizado): bool {
            $apellidosNombres = Ubicacion::normalizar("{$conductor->apellidos} {$conductor->nombres}");
            $nombresApellidos = Ubicacion::normalizar("{$conductor->nombres} {$conductor->apellidos}");

            return $normalizado === $apellidosNombres || $normalizado === $nombresApellidos;
        });
    }

    private function resolverTipoCarga(string $texto): ?TipoCarga
    {
        if ($texto === '') {
            return null;
        }

        $normalizado = Ubicacion::normalizar($texto);

        foreach (TipoCarga::cases() as $carga) {
            if (Ubicacion::normalizar($carga->label()) === $normalizado) {
                return $carga;
            }
        }

        return null;
    }

    /**
     * La columna de cliente a veces trae una ubicación en vez de Minsur o
     * Particular: es el otro corrimiento de columnas que aparece en los
     * reportes reales. Se detecta y se avisa, aunque el cliente termine
     * saliendo igual del tipo de carga.
     *
     * @param  list<string>  $problemas
     */
    private function detectarClienteEnColumnaCorrida(?string $texto, array &$problemas): void
    {
        if ($texto === null || $texto === '') {
            return;
        }

        $normalizado = Ubicacion::normalizar($texto);
        $esCliente = collect(Cliente::cases())
            ->contains(fn (Cliente $cliente): bool => Ubicacion::normalizar($cliente->label()) === $normalizado);

        if (! $esCliente && ! Ubicacion::esTextoSinUbicacion($texto) && Ubicacion::buscarPorNombre($texto) !== null) {
            $problemas[] = "La columna de cliente trae una ubicación («{$texto}»): parece una columna corrida.";
        }
    }

    /**
     * @param  list<string>  $problemas
     * @return array{0: ?Ubicacion, 1: ?Ubicacion}
     */
    private function resolverRuta(?string $texto, array &$problemas): array
    {
        $texto = trim((string) $texto);

        if ($texto === '' || $texto === '0') {
            return [null, null];
        }

        $partes = array_map('trim', explode('=>', $texto));

        if (count($partes) !== 2) {
            $problemas[] = "Ruta «{$texto}» no tiene el formato «origen => destino».";

            return [null, null];
        }

        [$origenTexto, $destinoTexto] = $partes;
        $origen = Ubicacion::buscarPorNombre($origenTexto);
        $destino = Ubicacion::buscarPorNombre($destinoTexto);

        if ($origen === null) {
            $problemas[] = "Origen de ruta «{$origenTexto}» no está en el catálogo.";
        }

        if ($destino === null) {
            $problemas[] = "Destino de ruta «{$destinoTexto}» no está en el catálogo.";
        }

        return [$origen, $destino];
    }
}
