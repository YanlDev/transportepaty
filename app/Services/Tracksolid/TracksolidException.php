<?php

namespace App\Services\Tracksolid;

use RuntimeException;

class TracksolidException extends RuntimeException
{
    /**
     * @param  int  $apiCode  Código de error devuelto por la API JIMI/Tracksolid.
     */
    public function __construct(
        string $message,
        public readonly int $apiCode = -1,
    ) {
        parent::__construct($message, $apiCode);
    }
}
