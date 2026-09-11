<?php

namespace App\Console\Commands;

use App\Enums\TipoDocumentoConductor;
use App\Models\ConductorDocumento;
use App\Services\LectorMrz;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

#[Signature('transpaty:leer-dni
    {--sobrescribir : Reemplaza también las caducidades ya cargadas}
    {--dry-run : Muestra lo que leería sin guardar nada}')]
#[Description('Lee la fecha de caducidad del DNI desde la banda legible por máquina del dorso escaneado.')]
class LeerCaducidadDni extends Command
{
    /**
     * A cuántos puntos por pulgada se rasteriza cada imagen del PDF. La banda
     * del MRZ es tipografía chica: por debajo de 300 el OCR empieza a comerse
     * caracteres, y por encima de 400 tarda el doble sin leer mejor.
     */
    private const RESOLUCION = 400;

    /**
     * El dorso puede venir escaneado al revés. Se prueban las cuatro
     * orientaciones porque rotar es barato comparado con perder el dato.
     *
     * @var list<int>
     */
    private const ROTACIONES = [0, 180, 90, 270];

    /**
     * Cómo se le pide a Tesseract que segmente la imagen.
     *
     * El 6 —un bloque uniforme de texto— acierta cuando la imagen es la
     * tarjeta sola. El 11 —texto disperso— rescata los escaneos donde la
     * tarjeta ocupa una esquina de una hoja A4 y la banda queda suelta en
     * medio del blanco. Se prueban en ese orden porque el 6 es más rápido y
     * resuelve la mayoría.
     *
     * @var list<int>
     */
    private const SEGMENTACIONES = [6, 11];

    public function __construct(private readonly LectorMrz $lector)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        // `convert` solo hace falta para reintentar el dorso rotado, pero se
        // exige igual: sin él se perderían en silencio los escaneos al revés,
        // que es justo el caso que uno no revisa.
        foreach (['pdfimages', 'tesseract', 'convert'] as $binario) {
            if (! $this->disponible($binario)) {
                $this->error("Falta {$binario}. Instálalo con: sudo apt install poppler-utils tesseract-ocr imagemagick");

                return self::FAILURE;
            }
        }

        $seco = (bool) $this->option('dry-run');
        $leidos = $sinBanda = $yaCargados = 0;

        $documentos = ConductorDocumento::query()
            ->with(['media', 'conductor'])
            ->where('tipo', TipoDocumentoConductor::Dni)
            ->get();

        foreach ($documentos as $documento) {
            if ($documento->fecha_vencimiento !== null && ! $this->option('sobrescribir')) {
                $yaCargados++;

                continue;
            }

            $media = $documento->getFirstMedia('archivo');

            if ($media === null || ! file_exists($media->getPath())) {
                continue;
            }

            $caducidad = $this->caducidadDe($media->getPath());

            if ($caducidad === null) {
                // Lo más común no es que el OCR falle, sino que el escaneo
                // traiga solo el anverso: la banda está al dorso, y si no está
                // en el archivo no hay nada que leer.
                $sinBanda++;
                $this->line("  <fg=gray>{$documento->conductor->nombre_completo}: sin banda legible</>");

                continue;
            }

            $this->line(sprintf(
                '  %s → caduca %s',
                $documento->conductor->nombre_completo,
                $caducidad->toDateString(),
            ));

            if (! $seco) {
                $documento->update(['fecha_vencimiento' => $caducidad->toDateString()]);
            }

            $leidos++;
        }

        $this->newLine();
        $this->info(($seco ? '[dry-run] ' : '')."Leídos: {$leidos} · sin banda legible: {$sinBanda} · ya cargados: {$yaCargados}");

        if ($sinBanda > 0) {
            $this->comment('Los que no tienen banda suelen ser escaneos de una sola cara. Hay que volver a escanear el dorso.');
        }

        return self::SUCCESS;
    }

    /**
     * Recorre las imágenes que trae el PDF hasta encontrar una banda que
     * valide. Se corta en la primera que cuadra: el dorso es una sola.
     */
    private function caducidadDe(string $pdf): ?CarbonImmutable
    {
        $carpeta = $this->carpetaTemporal();

        try {
            $imagenes = $this->extraerImagenes($pdf, $carpeta);

            foreach ($imagenes as $imagen) {
                foreach (self::ROTACIONES as $grados) {
                    foreach (self::SEGMENTACIONES as $segmentacion) {
                        $caducidad = $this->lector->caducidad($this->ocr($imagen, $grados, $segmentacion));

                        if ($caducidad !== null) {
                            return $caducidad;
                        }
                    }
                }
            }

            return null;
        } finally {
            File::deleteDirectory($carpeta);
        }
    }

    /**
     * @return list<string>
     */
    private function extraerImagenes(string $pdf, string $carpeta): array
    {
        // Las imágenes incrustadas y no la página rasterizada: el dorso suele
        // ser una imagen aparte dentro de la misma página, y sacarla suelta
        // evita rasterizar una hoja A4 casi vacía alrededor. Sale a su
        // resolución original, que es la del escaneo.
        //
        // Ojo: `pdfimages` no acepta `-r`; esa opción es de `pdftoppm`, y
        // pasársela lo hace salir con error sin extraer nada.
        shell_exec(sprintf(
            'pdfimages -png %s %s 2>/dev/null',
            escapeshellarg($pdf),
            escapeshellarg($carpeta.'/img'),
        ));

        $imagenes = array_values(array_map(
            fn (\SplFileInfo $archivo): string => $archivo->getPathname(),
            File::files($carpeta),
        ));

        // Si el PDF no trae imágenes sueltas —hay escaneos que vienen como una
        // sola página vectorizada— se rasteriza la página entera como plan B.
        if ($imagenes === []) {
            shell_exec(sprintf(
                'pdftoppm -png -r %d %s %s 2>/dev/null',
                self::RESOLUCION,
                escapeshellarg($pdf),
                escapeshellarg($carpeta.'/pag'),
            ));

            $imagenes = array_values(array_map(
                fn (\SplFileInfo $archivo): string => $archivo->getPathname(),
                File::files($carpeta),
            ));
        }

        return $imagenes;
    }

    /**
     * El OCR acotado a lo que puede aparecer en un MRZ. Restringir el alfabeto
     * es lo que más sube la precisión acá: sin eso el reconocedor propone
     * letras acentuadas y signos que en esta banda no existen, y confunde el
     * cero con la O.
     */
    private function ocr(string $imagen, int $rotacion, int $segmentacion): string
    {
        $entrada = $imagen;

        if ($rotacion !== 0) {
            $rotada = $imagen.".{$rotacion}.png";

            shell_exec(sprintf(
                'convert %s -rotate %d %s 2>/dev/null',
                escapeshellarg($imagen),
                $rotacion,
                escapeshellarg($rotada),
            ));

            if (! file_exists($rotada)) {
                return '';
            }

            $entrada = $rotada;
        }

        return (string) shell_exec(sprintf(
            'tesseract %s - --psm %d -c tessedit_char_whitelist=ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789\< 2>/dev/null',
            escapeshellarg($entrada),
            $segmentacion,
        ));
    }

    private function carpetaTemporal(): string
    {
        $carpeta = storage_path('app/mrz-'.bin2hex(random_bytes(6)));

        File::ensureDirectoryExists($carpeta);

        return $carpeta;
    }

    private function disponible(string $binario): bool
    {
        return trim((string) shell_exec('command -v '.escapeshellarg($binario))) !== '';
    }
}
