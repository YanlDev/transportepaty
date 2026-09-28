<?php

namespace App\Services\Sunat;

use RuntimeException;

/**
 * SUNAT validó la GR y no la emitió. No quedó nada en SUNAT: se puede
 * corregir el dato y volver a intentar.
 */
class EmisionRechazada extends RuntimeException {}
