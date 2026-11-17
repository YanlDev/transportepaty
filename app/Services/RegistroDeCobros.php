<?php

namespace App\Services;

use App\Models\Cobro;
use App\Models\Factura;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Registrar la plata que entra y repartirla entre las facturas que paga.
 *
 * El cobro es lo que manda. Cada vez que cambia lo repartido, la factura
 * vuelve a calcular lo que lleva cobrado y —si ya entró todo su neto— toma
 * la fecha, la cuenta y la operación del último cobro: de ahí leen la tabla,
 * el resumen y el Excel si está pagada.
 */
class RegistroDeCobros
{
    /** Diferencias de redondeo que no cuentan como exceso. */
    private const TOLERANCIA = 0.005;

    /**
     * Registra un depósito y lo reparte. Lo que no se reparta queda como
     * saldo a favor del cliente.
     *
     * @param  array{fecha: string, cuenta_bancaria_id?: int|null, numero_operacion?: string|null, monto: float, moneda: string, cliente_id?: int|null, observacion?: string|null}  $datos
     * @param  array<int, float>  $aplicaciones  factura_id => monto
     */
    public function registrar(array $datos, array $aplicaciones, ?User $usuario): Cobro
    {
        return DB::transaction(function () use ($datos, $aplicaciones, $usuario): Cobro {
            $cobro = Cobro::query()->create([...$datos, 'user_id' => $usuario?->id]);

            $this->repartir($cobro, $aplicaciones);

            return $cobro;
        });
    }

    /**
     * Aplica el saldo a favor de un cobro a más facturas.
     *
     * @param  array<int, float>  $aplicaciones  factura_id => monto
     */
    public function aplicar(Cobro $cobro, array $aplicaciones): void
    {
        DB::transaction(fn () => $this->repartir($cobro, $aplicaciones));
    }

    /**
     * Anula el cobro: sus facturas vuelven a deber lo que este les pagaba.
     */
    public function anular(Cobro $cobro, string $motivo, User $usuario): void
    {
        DB::transaction(function () use ($cobro, $motivo, $usuario): void {
            $facturas = $cobro->facturas()->get();

            $cobro->forceFill([
                'anulado_at' => now(),
                'anulado_por' => $usuario->id,
                'motivo_anulacion' => $motivo,
            ])->save();

            $facturas->each(fn (Factura $factura) => $this->sincronizar($factura));
        });
    }

    /**
     * El cobro escrito en la fila de la factura (fecha de pago, cuenta, N°
     * de operación): la forma rápida de cobrar una factura entera con un
     * depósito propio. Con la fecha crea el cobro por lo que falta; después,
     * esas celdas corrigen ese cobro, y quitar la fecha lo anula. Si la
     * factura se cobró junto con otras, o en varias partes, no se toca desde
     * acá.
     *
     * @param  array{fecha_pago?: string|null, cuenta_bancaria_id?: int|null, numero_operacion?: string|null}  $celdas
     */
    public function desdeLaFactura(Factura $factura, array $celdas, User $usuario): void
    {
        DB::transaction(function () use ($factura, $celdas, $usuario): void {
            $cobros = $factura->cobros()->withCount('facturas')->get();

            if ($cobros->isEmpty()) {
                if (blank($celdas['fecha_pago'] ?? null)) {
                    if (array_key_exists('fecha_pago', $celdas)) {
                        // Sin cobros no hay nada que anular: la factura queda por cobrar.
                        $this->sincronizar($factura);

                        return;
                    }

                    throw ValidationException::withMessages([
                        array_key_first($celdas) => 'Pon primero la fecha de pago: con ella se registra el cobro.',
                    ]);
                }

                $saldo = $factura->saldo();

                if ($saldo <= 0) {
                    throw ValidationException::withMessages(['fecha_pago' => 'La factura no tiene monto por cobrar.']);
                }

                $this->registrar([
                    'fecha' => $celdas['fecha_pago'],
                    'cuenta_bancaria_id' => $celdas['cuenta_bancaria_id'] ?? null,
                    'numero_operacion' => $celdas['numero_operacion'] ?? null,
                    'monto' => $saldo,
                    'moneda' => $factura->moneda->value,
                    'cliente_id' => $factura->clienteDelPadron?->id,
                ], [$factura->id => $saldo], $usuario);

                return;
            }

            $cobro = $cobros->first();

            if ($cobros->count() > 1 || $cobro->facturas_count > 1) {
                throw ValidationException::withMessages([
                    array_key_first($celdas) => $cobros->count() > 1
                        ? "La factura {$factura->numero} tiene varios cobros: corrígelos en Cobros."
                        : "La factura {$factura->numero} se cobró con un depósito que paga varias facturas: corrígelo en Cobros.",
                ]);
            }

            if (array_key_exists('fecha_pago', $celdas) && blank($celdas['fecha_pago'])) {
                $this->anular($cobro, 'Se quitó la fecha de pago de la factura.', $usuario);

                return;
            }

            $cobro->update([
                ...(filled($celdas['fecha_pago'] ?? null) ? ['fecha' => $celdas['fecha_pago']] : []),
                ...(array_key_exists('cuenta_bancaria_id', $celdas) ? ['cuenta_bancaria_id' => $celdas['cuenta_bancaria_id']] : []),
                ...(array_key_exists('numero_operacion', $celdas) ? ['numero_operacion' => $celdas['numero_operacion']] : []),
            ]);

            $this->sincronizar($factura);
        });
    }

    /**
     * Recalcula en la factura lo que lleva cobrado y, si ya entró todo el
     * neto, la fecha, la cuenta y la operación del último cobro. Mientras
     * falte algo, la factura sigue por cobrar.
     */
    public function sincronizar(Factura $factura): void
    {
        $cobros = $factura->cobros()->orderBy('fecha')->orderBy('cobros.id')->get();
        $cobrado = round((float) $cobros->sum(fn (Cobro $cobro): float => $cobro->montoEnLaFactura()), 2);
        $ultimo = $cobros->last();
        $completa = $ultimo !== null && $factura->neto !== null && $cobrado >= (float) $factura->neto - self::TOLERANCIA;

        $factura->forceFill([
            'cobrado' => $cobrado,
            'fecha_pago' => $completa ? $ultimo->fecha : null,
            'cuenta_bancaria_id' => $completa ? $ultimo->cuenta_bancaria_id : null,
            'numero_operacion' => $completa ? $ultimo->numero_operacion : null,
            // Sin eventos: son columnas calculadas, y el historial del cobro
            // ya dice qué pasó.
        ])->saveQuietly();
    }

    /**
     * @param  array<int, float>  $aplicaciones  factura_id => monto
     */
    private function repartir(Cobro $cobro, array $aplicaciones): void
    {
        $aplicaciones = array_filter($aplicaciones, fn (float $monto): bool => $monto > 0);

        if ($aplicaciones === []) {
            return;
        }

        $cobro->load('facturas');
        $facturas = Factura::query()->whereKey(array_keys($aplicaciones))->lockForUpdate()->get()->keyBy('id');
        $errores = [];

        foreach ($aplicaciones as $facturaId => $monto) {
            $factura = $facturas->get($facturaId);

            if ($factura === null) {
                $errores["aplicaciones.{$facturaId}"] = 'Esa factura no existe o está anulada.';
            } elseif ($factura->moneda !== $cobro->moneda) {
                $errores["aplicaciones.{$facturaId}"] = "La factura {$factura->numero} es en {$factura->moneda->label()} y el cobro no.";
            } elseif ($monto > $factura->saldo() + self::TOLERANCIA) {
                $errores["aplicaciones.{$facturaId}"] = "La factura {$factura->numero} solo debe {$factura->moneda->simbolo()} ".number_format($factura->saldo(), 2).'.';
            }
        }

        if (round(array_sum($aplicaciones), 2) > $cobro->saldoAFavor() + self::TOLERANCIA) {
            $errores['monto'] = 'Lo repartido entre las facturas pasa del monto del cobro.';
        }

        if ($errores !== []) {
            throw ValidationException::withMessages($errores);
        }

        foreach ($aplicaciones as $facturaId => $monto) {
            $yaTenia = $cobro->facturas->firstWhere('id', $facturaId);

            if ($yaTenia !== null) {
                $cobro->facturas()->updateExistingPivot($facturaId, ['monto' => round($yaTenia->montoDelCobro() + $monto, 2)]);
            } else {
                $cobro->facturas()->attach($facturaId, ['monto' => round($monto, 2)]);
            }

            $this->sincronizar($facturas->get($facturaId));
        }

        $cobro->unsetRelation('facturas');
    }
}
