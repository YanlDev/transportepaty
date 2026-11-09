<?php

namespace App\Services\Archivos;

/**
 * Qué pasó al intentar comprimir un PDF.
 */
final readonly class ResultadoCompresion
{
    public const COMPRIMIDO = 'comprimido';

    public const SIN_MEJORA = 'sin_mejora';

    public const SIN_GHOSTSCRIPT = 'sin_ghostscript';

    public const FALLO = 'fallo';

    public function __construct(
        public string $estado,
        public int $bytesAntes,
        public int $bytesDespues,
        public ?string $detalle = null,
    ) {}

    public function comprimido(): bool
    {
        return $this->estado === self::COMPRIMIDO;
    }

    /**
     * Ghostscript corrió y dio un veredicto (mejoró o no): no hace falta
     * volver a intentarlo con este archivo.
     */
    public function concluyente(): bool
    {
        return in_array($this->estado, [self::COMPRIMIDO, self::SIN_MEJORA], true);
    }

    public function bytesAhorrados(): int
    {
        return $this->comprimido() ? $this->bytesAntes - $this->bytesDespues : 0;
    }
}
