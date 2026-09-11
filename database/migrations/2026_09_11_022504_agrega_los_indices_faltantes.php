<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Los índices que faltaban sobre las columnas por las que realmente se filtra
 * y se agrupa.
 *
 * El de `cliente_id` es el más caro de los cuatro: el listado de clientes hace
 * `withCount('viajes')` y `withMax('viajes', ...)`, y sin índice el plan hacía
 * un seq scan de `viajes` por cada cliente de la página —26 recorridos de la
 * tabla entera para dibujar 25 filas.
 *
 * Va compuesto con `fecha_traslado` y no suelto para que sirva a las dos
 * mitades de esa consulta: el prefijo `cliente_id` resuelve el conteo, y la
 * fecha en segunda posición evita que el máximo tenga que recorrer el índice
 * de fechas filtrando por cliente.
 *
 * `viajes.cliente` (el texto crudo de la GR, no la FK) se lee en cada carga de
 * `/viajes` y `/contabilidad` para armar las opciones del filtro, con un
 * `DISTINCT ... ORDER BY` sobre toda la tabla.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('viajes', function (Blueprint $table): void {
            $table->index(['cliente_id', 'fecha_traslado']);
            $table->index('conductor_id');
            $table->index('carreta_id');
            $table->index('cliente');
        });

        Schema::table('facturas', function (Blueprint $table): void {
            $table->index('cuenta_bancaria_id');
        });
    }

    public function down(): void
    {
        Schema::table('viajes', function (Blueprint $table): void {
            $table->dropIndex(['cliente_id', 'fecha_traslado']);
            $table->dropIndex(['conductor_id']);
            $table->dropIndex(['carreta_id']);
            $table->dropIndex(['cliente']);
        });

        Schema::table('facturas', function (Blueprint $table): void {
            $table->dropIndex(['cuenta_bancaria_id']);
        });
    }
};
