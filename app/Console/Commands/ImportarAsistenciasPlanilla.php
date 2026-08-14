<?php

namespace App\Console\Commands;

use App\Enums\EstadoAsistencia;
use App\Models\Asistencia;
use App\Models\Conductor;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Importa la hoja "DIAS LABORADOS" de la planilla mensual en Excel (una fila
 * por conductor, una columna por día) a la tabla `asistencias`. Reemplaza esa
 * planilla, así que solo entienden los 4 códigos que de verdad se usan en los
 * seis meses ya llenados (Ene-Jun 2026): el resto de la "Leyenda" formal del
 * Excel (SA01-13, EMO, LIC. SIND, etc.) nunca aparece en los datos reales de
 * conductores, así que no hace falta ampliar el enum para soportarlos — un
 * código que no está en el mapa se reporta en vez de adivinarse.
 */
#[Signature('transpaty:importar-asistencias
    {archivo : Ruta al .xlsx de la planilla mensual}
    {--desde= : Fecha (Y-m-d) del primer día de columna, ej. 2026-05-28}
    {--dry-run : Solo muestra qué se crearía, sin escribir nada}')]
#[Description('Importa la hoja "DIAS LABORADOS" de una planilla mensual de asistencia a la tabla asistencias.')]
class ImportarAsistenciasPlanilla extends Command
{
    private const HOJA = 'DIAS LABORADOS';

    private const FILA_INICIO = 5;

    private const COLUMNA_DNI = 9; // I

    private const COLUMNA_PRIMER_DIA = 13; // M

    /**
     * @var array<string, EstadoAsistencia>
     */
    private const MAPA_CODIGOS = [
        '1' => EstadoAsistencia::Asistencia,
        'D' => EstadoAsistencia::Descanso,
        'VD' => EstadoAsistencia::Vacaciones,
        'F' => EstadoAsistencia::Falta,
    ];

    public function handle(): int
    {
        $ruta = (string) $this->argument('archivo');

        if (! is_file($ruta)) {
            $this->error("El archivo no existe: {$ruta}");

            return self::FAILURE;
        }

        $desde = $this->fechaDesde();

        if ($desde === null) {
            $this->error('--desde es obligatorio y debe ser una fecha válida (Y-m-d).');

            return self::FAILURE;
        }

        $hoja = IOFactory::load($ruta)->getSheetByName(self::HOJA);

        if (! $hoja instanceof Worksheet) {
            $this->error('El archivo no tiene una hoja "'.self::HOJA.'".');

            return self::FAILURE;
        }

        $seco = (bool) $this->option('dry-run');
        $columnas = $this->columnasDelCiclo($hoja, $desde);

        // Por DNI normalizado (solo dígitos, sin ceros a la izquierda): el
        // Excel trae inconsistencias de formato frente a lo que hay en la
        // tabla `conductores` (con o sin cero inicial, y en un puñado de
        // filas el código de credencial —con letra— quedó pegado en la
        // celda de DNI en vez del número solo). Comparar así evita perder
        // esos matches sin arriesgarse a adivinar entre DNIs que de verdad
        // son distintos.
        $conductoresPorDni = Conductor::query()->get(['id', 'documento'])
            ->keyBy(fn (Conductor $conductor): string => $this->normalizarDni($conductor->documento));

        $creadas = $yaExistian = $sinConductor = 0;
        $codigosDesconocidos = [];

        DB::transaction(function () use ($hoja, $columnas, $conductoresPorDni, $seco, &$creadas, &$yaExistian, &$sinConductor, &$codigosDesconocidos): void {
            for ($fila = self::FILA_INICIO; ; $fila++) {
                $dni = trim((string) $this->celda($hoja, self::COLUMNA_DNI, $fila)->getValue());

                if ($dni === '') {
                    break;
                }

                $conductor = $conductoresPorDni->get($this->normalizarDni($dni));

                if ($conductor === null) {
                    $nombre = trim((string) $this->celda($hoja, 8, $fila)->getValue());
                    $this->warn("  sin conductor: DNI {$dni} ({$nombre})");
                    $sinConductor++;

                    continue;
                }

                foreach ($columnas as $columna => $fecha) {
                    $valor = strtoupper(trim((string) $this->celda($hoja, $columna, $fila)->getFormattedValue()));

                    if ($valor === '') {
                        continue;
                    }

                    $estado = self::MAPA_CODIGOS[$valor] ?? null;

                    if ($estado === null) {
                        $codigosDesconocidos[$valor] = ($codigosDesconocidos[$valor] ?? 0) + 1;

                        continue;
                    }

                    $existe = Asistencia::query()
                        ->where('conductor_id', $conductor->id)
                        ->where('fecha', $fecha->toDateString())
                        ->exists();

                    if ($existe) {
                        $yaExistian++;

                        continue;
                    }

                    $creadas++;

                    if ($seco) {
                        continue;
                    }

                    Asistencia::query()->create([
                        'conductor_id' => $conductor->id,
                        'fecha' => $fecha->toDateString(),
                        'estado' => $estado,
                    ]);
                }
            }
        });

        $this->newLine();
        $this->info(
            ($seco ? '[dry-run] ' : '')
            ."Filas creadas: {$creadas} · ya existían: {$yaExistian} · sin conductor: {$sinConductor}"
        );

        if ($codigosDesconocidos !== []) {
            $this->warn('Códigos no reconocidos (no se importaron):');

            foreach ($codigosDesconocidos as $codigo => $veces) {
                $this->line("  {$codigo}: {$veces}");
            }
        }

        return self::SUCCESS;
    }

    private function normalizarDni(string $dni): string
    {
        $soloDigitos = preg_replace('/\D/', '', $dni) ?? '';

        return ltrim($soloDigitos, '0') ?: $soloDigitos;
    }

    private function fechaDesde(): ?Carbon
    {
        $valor = $this->option('desde');

        if (! is_string($valor) || $valor === '') {
            return null;
        }

        try {
            return Carbon::parse($valor)->startOfDay();
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Columnas de la grilla de días, de la M en adelante. Después del último
     * día vienen columnas resumen ("Dias Trabajados", "Compensación por
     * feriado", etc.) que sí traen un título en la fila 2 — a diferencia de
     * cada columna de día, que solo lo trae la primera (el label del mes).
     * Cortar ahí es más confiable que mirar la fila 3 (día de semana): un
     * valor numérico corto en una columna resumen («1», «2») también mide
     * un carácter como string y pasaría el corte si solo se mirara esa fila.
     *
     * @return array<int, Carbon>
     */
    private function columnasDelCiclo(Worksheet $hoja, Carbon $desde): array
    {
        $columnas = [];
        $columna = self::COLUMNA_PRIMER_DIA;
        $offset = 0;

        while (true) {
            // La columna M (offset 0) trae el label del mes en la fila 2
            // ("ENERO", o una fecha con formato mmm-yy) — no es señal de fin
            // de grilla ahí, solo en las columnas que le siguen.
            $tituloResumen = $offset > 0 ? trim((string) $this->celda($hoja, $columna, 2)->getValue()) : '';
            $diaSemana = trim((string) $this->celda($hoja, $columna, 3)->getValue());

            if ($tituloResumen !== '' || mb_strlen($diaSemana) !== 1) {
                break;
            }

            $columnas[$columna] = $desde->copy()->addDays($offset);
            $columna++;
            $offset++;
        }

        return $columnas;
    }

    private function celda(Worksheet $hoja, int $columna, int $fila): Cell
    {
        return $hoja->getCell(Coordinate::stringFromColumnIndex($columna).$fila);
    }
}
