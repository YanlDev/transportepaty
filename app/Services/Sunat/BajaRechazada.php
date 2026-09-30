<?php

namespace App\Services\Sunat;

use RuntimeException;

/**
 * SUNAT validó el pedido de baja y no la hizo: la GR sigue vigente en SUNAT.
 */
class BajaRechazada extends RuntimeException {}
