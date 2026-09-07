<?php

namespace App\Console\Commands;

use App\Models\Viaje;
use App\Services\ConstructorGuiaTransportista;
use App\Services\FirmadorGuiaTransportista;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('gre:probar
    {viaje : Número de GR del viaje (ej. EG03-00012342) o su id}
    {--correlativo=1 : Correlativo a usar dentro de la serie configurada}
    {--firmar : Firma el XML con el certificado digital además de generarlo}
    {--guardar= : Ruta donde escribir el XML resultante}')]
#[Description('Genera la GRE-T de un viaje y valida su estructura sin enviar nada a SUNAT. Sirve para verificar que los datos alcanzan antes de emitir de verdad.')]
class ProbarGuiaTransportista extends Command
{
    public function __construct(
        private readonly ConstructorGuiaTransportista $constructor,
        private readonly FirmadorGuiaTransportista $firmador,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $viaje = $this->buscarViaje((string) $this->argument('viaje'));

        if ($viaje === null) {
            $this->error('No se encontró el viaje.');

            return self::FAILURE;
        }

        $this->line("Viaje <info>{$viaje->numero_gr}</info> — {$viaje->cliente}");

        $faltantes = $this->constructor->faltantes($viaje);

        if ($faltantes !== []) {
            $this->newLine();
            $this->error('No se puede emitir todavía. Falta:');

            foreach ($faltantes as $falta) {
                $this->line("  • {$falta}");
            }

            return self::FAILURE;
        }

        try {
            $guia = $this->constructor->construir($viaje, (string) $this->option('correlativo'));

            $xml = $this->option('firmar')
                ? $this->firmador->firmar($guia)
                : $this->firmador->generar($guia);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        return $this->informar($xml, $this->firmador->nombreArchivo($guia));
    }

    private function informar(string $xml, string $nombre): int
    {
        if (! $this->esXmlValido($xml)) {
            $this->error('El XML generado no está bien formado.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('XML generado y bien formado.');
        $this->line("  archivo SUNAT : {$nombre}.xml");
        $this->line('  tamaño        : '.strlen($xml).' bytes');
        $this->line('  firmado       : '.($this->option('firmar') ? 'sí' : 'no'));

        $destino = $this->option('guardar');

        if ($destino !== null) {
            file_put_contents($destino, $xml);
            $this->line("  guardado en   : {$destino}");
        }

        $this->newLine();
        $this->comment('No se envió nada a SUNAT.');

        return self::SUCCESS;
    }

    private function esXmlValido(string $xml): bool
    {
        $previo = libxml_use_internal_errors(true);
        $documento = simplexml_load_string($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($previo);

        return $documento !== false;
    }

    private function buscarViaje(string $referencia): ?Viaje
    {
        return Viaje::query()
            ->when(
                ctype_digit($referencia),
                fn ($query) => $query->where('id', $referencia)->orWhere('numero_gr', $referencia),
                fn ($query) => $query->where('numero_gr', $referencia),
            )
            ->with(['tracto', 'carreta', 'conductor', 'puntoPartida', 'puntoLlegada'])
            ->first();
    }
}
