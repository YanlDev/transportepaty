<?php

namespace App\Console\Commands;

use App\Enums\TipoDocumento;
use App\Models\Vehiculo;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Smalot\PdfParser\Parser;
use Symfony\Component\Finder\Finder;
use Throwable;

#[Signature('transpaty:importar-bonificaciones
    {ruta : Carpeta con los PDF de las resoluciones de PROVIAS (ej. storage/app/private/bonificaciones/2026)}
    {--dry-run : Muestra lo que haría sin escribir nada}')]
#[Description('Adjunta a cada vehículo su permiso de bonificación de PROVIAS, leyendo la placa desde el texto de cada resolución.')]
class ImportarBonificaciones extends Command
{
    /**
     * Carpeta pública de PROVIAS donde vive cada resolución, por año.
     */
    private const URL_PROVIAS = 'http://gis.proviasnac.gob.pe/FilesPdfs/pesaje/bonificaciones/';

    private const MESES = [
        'enero' => 1, 'febrero' => 2, 'marzo' => 3, 'abril' => 4, 'mayo' => 5, 'junio' => 6,
        'julio' => 7, 'agosto' => 8, 'setiembre' => 9, 'septiembre' => 9, 'octubre' => 10,
        'noviembre' => 11, 'diciembre' => 12,
    ];

    public function handle(): int
    {
        $ruta = rtrim((string) $this->argument('ruta'), '/');

        if (! is_dir($ruta)) {
            $this->error("La ruta no existe: {$ruta}");

            return self::FAILURE;
        }

        $seco = (bool) $this->option('dry-run');
        $cargados = $sinCambios = $fueraDeFlota = 0;

        foreach ($this->porPlaca($ruta) as $placa => $resoluciones) {
            // Una placa puede tener más de un permiso; gana el más reciente.
            usort($resoluciones, fn (array $a, array $b): int => strcmp($a['permiso'], $b['permiso']));
            $elegida = array_pop($resoluciones);

            foreach ($resoluciones as $descartada) {
                $this->line("  ~ {$placa}: se descarta {$descartada['permiso']} ({$descartada['archivo']}), hay uno más reciente");
            }

            // Hay placas cargadas sin guion (VGB985), así que se compara sin él.
            $vehiculo = Vehiculo::whereRaw("replace(placa, '-', '') = ?", [str_replace('-', '', $placa)])->first();

            if ($vehiculo === null) {
                $this->warn("  ! {$placa}: tiene bonificación ({$elegida['permiso']}) pero no está en la flota");
                $fueraDeFlota++;

                continue;
            }

            $documento = $vehiculo->documentos()->firstOrNew(['tipo' => TipoDocumento::Bonificacion]);

            if ($documento->exists && $documento->numero === $elegida['permiso'] && $documento->hasMedia('archivo')) {
                $sinCambios++;

                continue;
            }

            $this->line("  + {$placa}: {$elegida['permiso']} · {$elegida['resolucion']}");
            $cargados++;

            if ($seco) {
                continue;
            }

            $documento->fill([
                'nombre' => $elegida['resolucion'],
                'numero' => $elegida['permiso'],
                'fecha_emision' => $elegida['fecha'],
                'fecha_vencimiento' => null,
                'observaciones' => 'Vigencia indefinida. Copia en PROVIAS: '.$elegida['url'],
            ])->save();

            $documento->addMedia($elegida['ruta'])
                ->preservingOriginal()
                ->usingFileName("bonificacion_{$placa}.pdf")
                ->toMediaCollection('archivo');
        }

        $sinBonificacion = Vehiculo::query()
            ->whereDoesntHave('documentos', fn ($consulta) => $consulta->where('tipo', TipoDocumento::Bonificacion))
            ->orderBy('tipo')
            ->orderBy('placa')
            ->get(['placa', 'tipo']);

        if (! $seco && $sinBonificacion->isNotEmpty()) {
            $this->newLine();
            $this->warn("Vehículos sin permiso de bonificación ({$sinBonificacion->count()}):");

            foreach ($sinBonificacion as $vehiculo) {
                $this->line("  - {$vehiculo->placa} ({$vehiculo->tipo->label()})");
            }
        }

        $this->newLine();
        $this->info(($seco ? '[dry-run] ' : '')."Cargados: {$cargados} · ya estaban: {$sinCambios} · fuera de la flota: {$fueraDeFlota}");

        return self::SUCCESS;
    }

    /**
     * Lee cada PDF de la carpeta y agrupa sus datos por la placa que figura en
     * el texto de la resolución, no por el nombre del archivo: PROVIAS repite
     * números de resolución entre lotes y el nombre no es confiable.
     *
     * @return array<string, list<array{permiso: string, resolucion: string, fecha: Carbon|null, url: string, ruta: string, archivo: string}>>
     */
    private function porPlaca(string $ruta): array
    {
        $parser = new Parser;
        $agrupados = [];

        foreach (Finder::create()->files()->in($ruta)->depth(0)->name('*.pdf')->sortByName() as $archivo) {
            try {
                $texto = (string) preg_replace('/\s+/u', ' ', $parser->parseFile((string) $archivo->getRealPath())->getText());
            } catch (Throwable $e) {
                $this->warn("  ! No se pudo leer {$archivo->getFilename()}: {$e->getMessage()}");

                continue;
            }

            $datos = $this->datosDeResolucion($texto);

            if ($datos === null) {
                $this->warn("  ! {$archivo->getFilename()} no parece una resolución de bonificación, se omite");

                continue;
            }

            $agrupados[$datos['placa']][] = [
                'permiso' => $datos['permiso'],
                'resolucion' => $datos['resolucion'],
                'fecha' => $datos['fecha'],
                'url' => self::URL_PROVIAS.$datos['anio'].'/'.rawurlencode($archivo->getFilename()),
                'ruta' => (string) $archivo->getRealPath(),
                'archivo' => $archivo->getFilename(),
            ];
        }

        return $agrupados;
    }

    /**
     * @return array{placa: string, permiso: string, resolucion: string, anio: string, fecha: Carbon|null}|null
     */
    private function datosDeResolucion(string $texto): ?array
    {
        if (! preg_match('/Rodaje N\S*\s*([A-Z0-9]{3})-?(\d{3})/u', $texto, $placa)
            || ! preg_match('/PBM\d+/', $texto, $permiso)
            || ! preg_match('/N° (\d+)\s*[–-]\s*(\d{4})\s*[–-]\s*MTC\/20\.13\.2/u', $texto, $resolucion)) {
            return null;
        }

        $fecha = null;

        if (preg_match('/Lima, (\d{1,2}) de (\p{L}+) del? (\d{4})/u', $texto, $partes)
            && isset(self::MESES[mb_strtolower($partes[2])])) {
            $fecha = Carbon::create((int) $partes[3], self::MESES[mb_strtolower($partes[2])], (int) $partes[1]);
        }

        return [
            'placa' => "{$placa[1]}-{$placa[2]}",
            'permiso' => $permiso[0],
            'resolucion' => "RSD N° {$resolucion[1]}-{$resolucion[2]}-MTC/20.13.2",
            'anio' => $resolucion[2],
            'fecha' => $fecha,
        ];
    }
}
