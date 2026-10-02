<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La tarifa del tarifario es la sugerencia; lo que se le cobra al cliente
 * puede ser otro número redondo («9,135 → 9,000»). Se guardan los dos: la
 * proforma lleva solo el precio final, y la rebaja queda para la casa.
 *
 * El precio se expresa como cantidad × precio unitario, igual que en la
 * proforma en papel: 30 TN a S/ 435, o 1 viaje a S/ 9,000.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cotizaciones', function (Blueprint $table): void {
            $table->string('cliente_direccion')->nullable()->after('cliente_ruc');
            $table->string('referencia')->nullable()->after('material');
            $table->decimal('tarifa_calculada', 12, 2)->nullable()->after('margen');
            $table->decimal('cantidad', 10, 2)->default(1)->after('tarifa_calculada');
            $table->string('unidad', 10)->default('VIAJE')->after('cantidad');
            $table->decimal('precio_unitario', 12, 2)->nullable()->after('unidad');
        });

        // Las ya emitidas se cobraron por viaje y al precio calculado.
        DB::table('cotizaciones')->update([
            'tarifa_calculada' => DB::raw('subtotal'),
            'precio_unitario' => DB::raw('subtotal'),
        ]);
    }

    public function down(): void
    {
        Schema::table('cotizaciones', function (Blueprint $table): void {
            $table->dropColumn([
                'cliente_direccion',
                'referencia',
                'tarifa_calculada',
                'cantidad',
                'unidad',
                'precio_unitario',
            ]);
        });
    }
};
