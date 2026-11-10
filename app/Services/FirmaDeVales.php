<?php

namespace App\Services;

use App\Models\DetalleValeCompra;
use App\Models\ValeCompra;
use Illuminate\Validation\ValidationException;
use SodiumException;

/**
 * La firma del servidor sobre un vale de compra aprobado (Ed25519).
 *
 * Se firma una huella (SHA-256) de lo que el vale autoriza: su número, a qué
 * proveedor, cada línea con su cantidad y su precio, el total, quién lo pidió,
 * quién lo aprobó, cuándo y hasta cuándo vale. Al verificar se vuelve a
 * calcular la huella con lo que hay hoy en la base: si alguien cambió un
 * monto o una cantidad después de la aprobación —desde la pantalla o directo
 * en la base—, la huella ya no es la firmada y el vale no valida.
 *
 * Lo que la firma no puede saber: si quien aprobó debía aprobar, ni si la
 * clave privada salió del servidor. Por eso la clave vive solo en el `.env`.
 */
class FirmaDeVales
{
    /**
     * Firma el vale con lo que tiene en este momento. Se llama al aprobarlo,
     * con el aprobador, la fecha y el vencimiento ya puestos.
     *
     * @throws ValidationException si el servidor no tiene clave de firma
     */
    public function firmar(ValeCompra $vale): void
    {
        $clave = $this->clavePrivada();

        if ($clave === null) {
            throw ValidationException::withMessages([
                'firma' => 'El servidor no tiene clave para firmar vales. Genera una con «php artisan vales:generar-clave» y ponla en el .env.',
            ]);
        }

        $huella = $this->huella($vale);

        $vale->forceFill([
            'huella' => $huella,
            'firma' => base64_encode(sodium_crypto_sign_detached($huella, $clave)),
        ])->save();
    }

    /**
     * Si el vale es el que se firmó: su contenido no cambió y la firma es de
     * este servidor.
     */
    public function esAutentico(ValeCompra $vale): bool
    {
        $clave = $this->clavePrivada();
        $firma = base64_decode((string) $vale->firma, true);

        if ($clave === null || $vale->huella === null || $firma === false || strlen($firma) !== SODIUM_CRYPTO_SIGN_BYTES) {
            return false;
        }

        return hash_equals($vale->huella, $this->huella($vale))
            && sodium_crypto_sign_verify_detached($firma, $vale->huella, sodium_crypto_sign_publickey_from_secretkey($clave));
    }

    /**
     * La huella de lo que el vale autoriza, tal como está hoy en la base.
     */
    public function huella(ValeCompra $vale): string
    {
        $vale->load('detalles');

        $contenido = [
            'numero' => $vale->numero(),
            'codigo' => $vale->codigo_verificacion,
            'tipo' => $vale->tipo->value,
            'proveedor' => $vale->proveedor_id,
            'moneda' => $vale->moneda->value,
            'total' => number_format((float) $vale->total, 2, '.', ''),
            'orden' => $vale->orden_trabajo_id,
            'unidad' => $vale->vehiculo_id,
            'pidio' => $vale->solicitado_por,
            'aprobo' => $vale->aprobado_por,
            'aprobado' => $vale->aprobado_at?->toIso8601String(),
            'vence' => $vale->vence_el?->toDateString(),
            'lineas' => $vale->detalles
                ->sortBy('id')
                ->map(fn (DetalleValeCompra $detalle): array => [
                    'articulo' => $detalle->articulo_id,
                    'descripcion' => $detalle->descripcion,
                    'servicio' => $detalle->es_servicio,
                    'cantidad' => number_format((float) $detalle->cantidad, 3, '.', ''),
                    'precio' => number_format((float) $detalle->precio_unitario, 4, '.', ''),
                    'total' => number_format((float) $detalle->total, 2, '.', ''),
                ])
                ->values()
                ->all(),
        ];

        return hash('sha256', (string) json_encode($contenido, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    /**
     * Un par de claves nuevo, en base64: la privada va al `.env`.
     *
     * @return array{privada: string, publica: string}
     */
    public function generarClaves(): array
    {
        $par = sodium_crypto_sign_keypair();

        return [
            'privada' => base64_encode(sodium_crypto_sign_secretkey($par)),
            'publica' => base64_encode(sodium_crypto_sign_publickey($par)),
        ];
    }

    public function tieneClave(): bool
    {
        return $this->clavePrivada() !== null;
    }

    /**
     * @return non-empty-string|null
     */
    private function clavePrivada(): ?string
    {
        $clave = base64_decode((string) config('transpaty.compras.clave_firma'), true);

        try {
            return $clave !== false && strlen($clave) === SODIUM_CRYPTO_SIGN_SECRETKEYBYTES ? $clave : null;
        } catch (SodiumException) {
            return null;
        }
    }
}
