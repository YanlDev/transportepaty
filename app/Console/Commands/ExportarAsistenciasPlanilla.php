<?php

namespace App\Console\Commands;

use App\Enums\EstadoAsistencia;
use App\Models\Asistencia;
use App\Models\Conductor;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;
use ZipArchive;

/**
 * La contraparte de `transpaty:importar-asistencias`: en vez de leer la
 * planilla mensual hacia la tabla `asistencias`, vuelca lo que ya está en
 * `asistencias` sobre la hoja "DIAS LABORADOS" de la planilla en Excel —el
 * mismo formato, mismas columnas fijas (nombre en H, DNI en I, grilla de
 * días desde la M)—, para no tener que marcar dos veces lo mismo.
 *
 * Empareja por DNI normalizado primero; si no matchea (visto en el corpus
 * real: typos de un dígito entre la planilla y el padrón), cae a nombre
 * completo exacto antes de darlo por «sin conductor», porque en la práctica
 * ambas fuentes se refieren a la misma persona.
 *
 * NO usa el escritor de PhpSpreadsheet para guardar: esta librería no sabe
 * leer ni escribir «external links» (fórmulas que jalan de otro libro .xlsx),
 * y los descarta en silencio en cualquier round-trip load→save —verificado
 * contra la planilla real de PATY, que trae 7 de esos vínculos (~300 KB).
 * En vez de eso, se edita a mano, dentro del .zip del .xlsx, únicamente el
 * XML de la hoja «DIAS LABORADOS»: todo lo demás del archivo queda
 * byte-por-byte igual.
 */
#[Signature('transpaty:exportar-asistencias
    {archivo : Ruta al .xlsx de la planilla mensual}
    {--desde= : Fecha (Y-m-d) del primer día de columna, ej. 2026-07-28}
    {--dry-run : Solo muestra qué se escribiría, sin guardar el archivo}')]
#[Description('Vuelca la asistencia de la tabla asistencias sobre la hoja "DIAS LABORADOS" de una planilla mensual.')]
class ExportarAsistenciasPlanilla extends Command
{
    private const HOJA = 'DIAS LABORADOS';

    private const FILA_INICIO = 5;

    private const COLUMNA_NOMBRE = 8; // H

    private const COLUMNA_DNI = 9; // I

    private const COLUMNA_PRIMER_DIA = 13; // M

    /**
     * @var array<string, string>
     */
    private const MAPA_CODIGOS = [
        EstadoAsistencia::Asistencia->value => '1',
        EstadoAsistencia::Descanso->value => 'D',
        EstadoAsistencia::Vacaciones->value => 'VD',
        EstadoAsistencia::Falta->value => 'F',
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

        $seco = (bool) $this->option('dry-run');

        // Solo para leer: nunca se llama a un writer sobre este objeto. Ver
        // el aviso de la clase sobre por qué no se guarda con PhpSpreadsheet.
        $hoja = IOFactory::load($ruta)->getSheetByName(self::HOJA);

        if (! $hoja instanceof Worksheet) {
            $this->error('El archivo no tiene una hoja "'.self::HOJA.'".');

            return self::FAILURE;
        }

        $columnas = $this->columnasDelCiclo($hoja, $desde);

        $conductoresPorDni = Conductor::query()->get(['id', 'nombres', 'apellidos', 'documento'])
            ->keyBy(fn (Conductor $conductor): string => $this->normalizarDni($conductor->documento));

        $conductoresPorNombre = Conductor::query()->get(['id', 'nombres', 'apellidos'])
            ->keyBy(fn (Conductor $conductor): string => $this->normalizarNombre("{$conductor->apellidos} {$conductor->nombres}"));

        $sinConductor = 0;
        $emparejadosPorNombre = [];

        // Celdas a escribir: coordenada tipo "M5" => código ('1'/'D'/'VD'/'F').
        $celdas = [];

        for ($fila = self::FILA_INICIO; ; $fila++) {
            $dni = trim((string) $this->celda($hoja, self::COLUMNA_DNI, $fila)->getValue());
            $nombre = trim((string) $this->celda($hoja, self::COLUMNA_NOMBRE, $fila)->getValue());

            if ($dni === '' && $nombre === '') {
                break;
            }

            $conductor = $conductoresPorDni->get($this->normalizarDni($dni));

            if ($conductor === null) {
                $conductor = $conductoresPorNombre->get($this->normalizarNombre($nombre));

                if ($conductor !== null) {
                    $emparejadosPorNombre[] = "{$nombre} (DNI planilla {$dni} vs padrón sin ese DNI)";
                }
            }

            if ($conductor === null) {
                $this->warn("  sin conductor: DNI {$dni} ({$nombre})");
                $sinConductor++;

                continue;
            }

            $asistenciasDelMes = Asistencia::query()
                ->where('conductor_id', $conductor->id)
                ->whereIn('fecha', array_map(fn (Carbon $fecha): string => $fecha->toDateString(), $columnas))
                ->get()
                ->keyBy(fn (Asistencia $asistencia): string => $asistencia->fecha->toDateString());

            foreach ($columnas as $columna => $fecha) {
                $asistencia = $asistenciasDelMes->get($fecha->toDateString());

                if ($asistencia === null) {
                    continue;
                }

                $coordenada = Coordinate::stringFromColumnIndex($columna).$fila;
                $celdas[$coordenada] = self::MAPA_CODIGOS[$asistencia->estado->value];
            }
        }

        if (! $seco) {
            $this->escribirCeldas($ruta, self::HOJA, $celdas);
        }

        $this->newLine();
        $this->info(
            ($seco ? '[dry-run] ' : '')
            .'Celdas escritas: '.count($celdas)." · sin conductor: {$sinConductor}"
        );

        if ($emparejadosPorNombre !== []) {
            $this->warn('Emparejados por nombre (el DNI de la planilla no coincide con el del padrón):');

            foreach ($emparejadosPorNombre as $linea) {
                $this->line("  {$linea}");
            }
        }

        return self::SUCCESS;
    }

    private function normalizarDni(string $dni): string
    {
        $soloDigitos = preg_replace('/\D/', '', $dni) ?? '';

        return ltrim($soloDigitos, '0') ?: $soloDigitos;
    }

    /**
     * Mismo criterio de comparación en ambos lados: mayúsculas y espacios
     * colapsados, para no fallar por diferencias de tipeo triviales.
     */
    private function normalizarNombre(string $nombre): string
    {
        return trim(preg_replace('/\s+/', ' ', mb_strtoupper($nombre)) ?? '');
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
     * Misma lógica que `ImportarAsistenciasPlanilla::columnasDelCiclo`: la
     * grilla de días va de la M en adelante hasta la primera columna con
     * título de resumen en la fila 2 ("Días Trabajados", etc.).
     *
     * @return array<int, Carbon>
     */
    private function columnasDelCiclo(Worksheet $hoja, Carbon $desde): array
    {
        $columnas = [];
        $columna = self::COLUMNA_PRIMER_DIA;
        $offset = 0;

        while (true) {
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

    /**
     * Reemplaza, dentro del .zip del .xlsx, únicamente el XML de la hoja
     * pedida —todo lo demás del archivo (otras hojas, external links,
     * estilos, calcChain) se copia tal cual, sin pasar por PhpSpreadsheet.
     *
     * @param  array<string, string>  $celdas  Coordenada ("M5") => código.
     */
    private function escribirCeldas(string $ruta, string $nombreHoja, array $celdas): void
    {
        if ($celdas === []) {
            return;
        }

        $archivoHoja = $this->resolverArchivoDeHoja($ruta, $nombreHoja);

        $zip = new ZipArchive;

        if ($zip->open($ruta) !== true) {
            throw new RuntimeException("No se pudo abrir {$ruta} como .zip.");
        }

        $xml = $zip->getFromName($archivoHoja);

        if ($xml === false) {
            $zip->close();

            throw new RuntimeException("No se encontró {$archivoHoja} dentro del .xlsx.");
        }

        $zip->addFromString($archivoHoja, $this->aplicarCeldas($xml, $celdas));

        if (! $zip->close()) {
            throw new RuntimeException('No se pudo guardar el .xlsx modificado.');
        }
    }

    /**
     * `xl/workbook.xml` nombra las hojas y las liga a un r:id; ese r:id se
     * resuelve a un archivo físico (`worksheets/sheetN.xml`) en
     * `xl/_rels/workbook.xml.rels`. El nombre de hoja NO determina el
     * número del archivo —Excel los va asignando según el historial de
     * edición del libro, no por el orden visible de pestañas.
     */
    private function resolverArchivoDeHoja(string $ruta, string $nombreHoja): string
    {
        $zip = new ZipArchive;

        if ($zip->open($ruta) !== true) {
            throw new RuntimeException("No se pudo abrir {$ruta} como .zip.");
        }

        $workbookXml = $zip->getFromName('xl/workbook.xml');
        $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
        $zip->close();

        if ($workbookXml === false || $relsXml === false) {
            throw new RuntimeException('El .xlsx no tiene xl/workbook.xml o su .rels.');
        }

        $workbook = new \SimpleXMLElement($workbookXml);
        $workbook->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $workbook->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');

        $rId = null;

        foreach ($workbook->xpath('//m:sheet') as $sheet) {
            $atributos = $sheet->attributes();
            $atributosR = $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');

            if ((string) $atributos['name'] === $nombreHoja) {
                $rId = (string) $atributosR['id'];

                break;
            }
        }

        if ($rId === null) {
            throw new RuntimeException("No se encontró la hoja \"{$nombreHoja}\" en xl/workbook.xml.");
        }

        $rels = new \SimpleXMLElement($relsXml);
        $rels->registerXPathNamespace('p', 'http://schemas.openxmlformats.org/package/2006/relationships');

        foreach ($rels->xpath('//p:Relationship') as $relacion) {
            $atributos = $relacion->attributes();

            if ((string) $atributos['Id'] === $rId) {
                return 'xl/'.(string) $atributos['Target'];
            }
        }

        throw new RuntimeException("No se pudo resolver el r:id \"{$rId}\" a un archivo.");
    }

    /**
     * Edita el DOM del XML de la hoja: por cada celda pedida, busca el nodo
     * `<c r="...">` existente (la grilla de días ya trae la celda vacía,
     * autoclosing, con su estilo) y le pone el valor —número plano para «1»
     * (Asistencia), `inlineStr` para los códigos de letra («D», «F», «VD»),
     * que no requiere tocar `sharedStrings.xml`. Si la celda ya trae una
     * fórmula (`<f>`), se deja intacta y se avisa en vez de pisarla: no
     * debería pasar en la grilla de días, pero mejor no arriesgar una
     * columna de resumen mal identificada.
     *
     * @param  array<string, string>  $celdas
     */
    private function aplicarCeldas(string $xml, array $celdas): string
    {
        $dom = new \DOMDocument;
        $dom->preserveWhiteSpace = true;
        $dom->formatOutput = false;

        if (! $dom->loadXML($xml)) {
            throw new RuntimeException('El XML de la hoja no se pudo parsear.');
        }

        $xpath = new \DOMXPath($dom);
        $ns = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
        $xpath->registerNamespace('m', $ns);

        foreach ($celdas as $coordenada => $codigo) {
            $nodos = $xpath->query("//m:c[@r='{$coordenada}']");

            if ($nodos === false || $nodos->length === 0) {
                $this->warn("  celda {$coordenada} no existe en la hoja, se saltó.");

                continue;
            }

            /** @var \DOMElement $celda */
            $celda = $nodos->item(0);

            if ($xpath->query('m:f', $celda)->length > 0) {
                $this->warn("  celda {$coordenada} tiene una fórmula, se saltó para no pisarla.");

                continue;
            }

            foreach (iterator_to_array($celda->childNodes) as $hijo) {
                $celda->removeChild($hijo);
            }

            if ($codigo === '1') {
                $celda->removeAttribute('t');
                $valor = $dom->createElementNS($ns, 'v', '1');
                $celda->appendChild($valor);

                continue;
            }

            $celda->setAttribute('t', 'inlineStr');
            $is = $dom->createElementNS($ns, 'is');
            $t = $dom->createElementNS($ns, 't', htmlspecialchars($codigo, ENT_XML1));
            $is->appendChild($t);
            $celda->appendChild($is);
        }

        return $dom->saveXML();
    }
}
