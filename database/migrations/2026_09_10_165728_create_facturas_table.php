<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La factura es la unidad de dinero, y también la que agrupa: un mismo
     * viaje físico puede traer dos GR (dos filas en `viajes`) y cobrarse una
     * sola vez, y a la inversa una factura quincenal puede juntar diez viajes
     * del mismo cliente. Quien factura elige qué filas entran; no se deriva de
     * la heurística de `Viaje::claveGrupoViaje()`, que existe para contar
     * viajes reales y no para cobrar.
     */
    public function up(): void
    {
        Schema::create('facturas', function (Blueprint $table) {
            $table->id();

            $table->string('numero')->unique();
            $table->date('fecha_emision');

            // El «valor flete facturado» tal cual sale en el documento, sin
            // desglosar IGV: es el único número que el usuario lleva hoy, y la
            // base siempre se puede derivar del total.
            $table->decimal('monto', 12, 2);
            $table->string('moneda', 3)->default('PEN');

            // Null mientras esté por cobrar: la ausencia de fecha de pago es
            // justamente lo que define el saldo pendiente.
            $table->date('fecha_pago')->nullable();

            // Por dónde entró la plata. Nullable porque se conoce recién al
            // cobrar, no al emitir.
            $table->foreignId('cuenta_bancaria_id')->nullable()
                ->constrained('cuentas_bancarias')->nullOnDelete();

            $table->text('observacion')->nullable();
            $table->timestamps();

            $table->index('fecha_emision');
            $table->index('fecha_pago');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facturas');
    }
};
