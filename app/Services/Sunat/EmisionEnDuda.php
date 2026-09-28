<?php

namespace App\Services\Sunat;

use RuntimeException;

/**
 * El pedido de emisión salió pero no hubo respuesta clara: la GR pudo haberse
 * emitido. Nunca se reintenta sola; hay que revisar «Consulta de GRE» en SOL
 * antes de volver a emitir, para no duplicarla.
 */
class EmisionEnDuda extends RuntimeException {}
