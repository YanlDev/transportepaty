<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El almacén del taller, valorizado al costo promedio ponderado.
     *
     * - `articulos`: el catálogo. Guarda su saldo (stock, costo promedio y
     *   valor) como copia del último movimiento del kardex, para listar el
     *   stock sin recorrer el historial; `Kardex` lo recalcula cada vez.
     * - `proveedores`: a quién se le compra.
     * - `ingresos_almacen`: lo que entra, con su comprobante (una compra) o
     *   lo que vuelve de un vale (una devolución).
     * - `salidas_almacen`: el vale de salida, siempre con destino: una orden
     *   de trabajo, una unidad o el consumo general del taller.
     * - `inventarios_almacen`: los conteos físicos. El primero es el saldo
     *   inicial; los siguientes, ajustes.
     * - `movimientos_almacen`: el kardex. Una fila por artículo y documento,
     *   con el saldo después de cada movimiento.
     *
     * Ningún documento se borra: se anula, sus movimientos salen del kardex
     * y los saldos se recalculan.
     */
    public function up(): void
    {
        Schema::create('categorias_articulo', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->timestamps();
        });

        Schema::create('articulos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 30)->unique();
            $table->string('nombre');
            $table->string('numero_parte')->nullable();
            $table->string('marca')->nullable();
            $table->foreignId('categoria_articulo_id')->nullable()->constrained('categorias_articulo')->nullOnDelete();
            $table->string('unidad', 10);
            // Cómo se compra, si no es como se despacha: el aceite viene en
            // cilindro de 208 litros y sale por litros.
            $table->string('unidad_compra', 10)->nullable();
            $table->decimal('factor_compra', 12, 3)->default(1);
            $table->decimal('stock_minimo', 14, 3)->nullable();
            $table->string('ubicacion')->nullable();
            $table->json('marcas_compatibles')->nullable();
            $table->text('observacion')->nullable();
            $table->boolean('activo')->default(true);
            $table->decimal('stock', 14, 3)->default(0);
            $table->decimal('costo_promedio', 14, 4)->default(0);
            $table->decimal('valor', 14, 2)->default(0);
            $table->timestamps();

            $table->index('nombre');
        });

        Schema::create('proveedores', function (Blueprint $table) {
            $table->id();
            $table->string('ruc', 11)->nullable()->unique();
            $table->string('razon_social');
            $table->string('nombre_comercial')->nullable();
            $table->string('contacto')->nullable();
            $table->string('telefono')->nullable();
            $table->string('email')->nullable();
            $table->string('direccion')->nullable();
            $table->string('condicion_pago')->nullable();
            $table->text('notas')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('salidas_almacen', function (Blueprint $table) {
            $table->id();
            $table->date('fecha');
            $table->string('destino', 20);
            $table->foreignId('vehiculo_id')->nullable()->constrained('vehiculos')->nullOnDelete();
            $table->foreignId('orden_trabajo_id')->nullable()->constrained('ordenes_trabajo')->nullOnDelete();
            $table->string('entregado_a')->nullable();
            $table->text('observacion')->nullable();
            $table->decimal('costo_total', 14, 2)->default(0);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('anulado_at')->nullable();
            $table->foreignId('anulado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('motivo_anulacion', 500)->nullable();
            $table->timestamps();

            $table->index('fecha');
        });

        Schema::create('detalles_salida', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salida_almacen_id')->constrained('salidas_almacen')->cascadeOnDelete();
            $table->foreignId('articulo_id')->constrained('articulos')->restrictOnDelete();
            $table->decimal('cantidad', 14, 3);
            $table->decimal('costo_unitario', 14, 4)->default(0);
            $table->decimal('costo_total', 14, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('ingresos_almacen', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 20);
            $table->date('fecha');
            $table->foreignId('proveedor_id')->nullable()->constrained('proveedores')->nullOnDelete();
            $table->string('tipo_comprobante', 20)->nullable();
            $table->string('serie_numero', 30)->nullable();
            $table->string('moneda', 3)->default('PEN');
            $table->decimal('tipo_cambio', 8, 4)->nullable();
            $table->boolean('precios_con_igv')->default(false);
            // Lo que dice el comprobante, en su moneda.
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('igv', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            // Lo que entra al kardex: en soles y, con factura, sin IGV.
            $table->decimal('costo_total', 14, 2)->default(0);
            $table->foreignId('salida_almacen_id')->nullable()->constrained('salidas_almacen')->nullOnDelete();
            $table->text('observacion')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('anulado_at')->nullable();
            $table->foreignId('anulado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('motivo_anulacion', 500)->nullable();
            $table->timestamps();

            $table->index('fecha');
            $table->index(['proveedor_id', 'serie_numero']);
        });

        Schema::create('detalles_ingreso', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ingreso_almacen_id')->constrained('ingresos_almacen')->cascadeOnDelete();
            $table->foreignId('articulo_id')->constrained('articulos')->restrictOnDelete();
            // De qué línea del vale vuelve, en una devolución.
            $table->foreignId('detalle_salida_id')->nullable()->constrained('detalles_salida')->nullOnDelete();
            // Tal como viene en el comprobante: en la unidad de compra.
            $table->decimal('cantidad', 14, 3);
            $table->decimal('precio_unitario', 14, 4)->default(0);
            // Ya pasado a la unidad del almacén, en soles y sin IGV.
            $table->decimal('factor', 12, 3)->default(1);
            $table->decimal('cantidad_base', 14, 3);
            $table->decimal('costo_unitario', 14, 4)->default(0);
            $table->decimal('costo_total', 14, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('inventarios_almacen', function (Blueprint $table) {
            $table->id();
            $table->date('fecha');
            $table->string('estado', 20);
            $table->text('observacion')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('cerrado_at')->nullable();
            $table->foreignId('cerrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('anulado_at')->nullable();
            $table->foreignId('anulado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('motivo_anulacion', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('detalles_inventario', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventario_almacen_id')->constrained('inventarios_almacen')->cascadeOnDelete();
            $table->foreignId('articulo_id')->constrained('articulos')->restrictOnDelete();
            $table->decimal('cantidad_contada', 14, 3);
            // Para lo que sobra: el costo al que entra. Vacío, el promedio.
            $table->decimal('costo_unitario', 14, 4)->nullable();
            $table->boolean('costo_estimado')->default(false);
            // Se fijan al cerrar el conteo.
            $table->decimal('stock_sistema', 14, 3)->nullable();
            $table->decimal('diferencia', 14, 3)->nullable();
            $table->string('observacion', 500)->nullable();
            $table->timestamps();

            $table->unique(['inventario_almacen_id', 'articulo_id']);
        });

        Schema::create('movimientos_almacen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('articulo_id')->constrained('articulos')->restrictOnDelete();
            $table->date('fecha');
            $table->string('tipo', 20);
            // La línea del documento que lo origina.
            $table->morphs('origen');
            $table->string('documento', 60);
            $table->decimal('entrada', 14, 3)->default(0);
            $table->decimal('salida', 14, 3)->default(0);
            $table->decimal('costo_unitario', 14, 4)->default(0);
            $table->decimal('costo_total', 14, 2)->default(0);
            $table->decimal('saldo_cantidad', 14, 3)->default(0);
            $table->decimal('saldo_costo_unitario', 14, 4)->default(0);
            $table->decimal('saldo_valor', 14, 2)->default(0);
            // Copias para los reportes: a qué unidad, OT o proveedor toca.
            $table->foreignId('vehiculo_id')->nullable()->constrained('vehiculos')->nullOnDelete();
            $table->foreignId('orden_trabajo_id')->nullable()->constrained('ordenes_trabajo')->nullOnDelete();
            $table->foreignId('proveedor_id')->nullable()->constrained('proveedores')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['articulo_id', 'fecha', 'id']);
            $table->index('fecha');
        });

        $ahora = now();

        DB::table('categorias_articulo')->insert(array_map(
            fn (string $nombre): array => ['nombre' => $nombre, 'created_at' => $ahora, 'updated_at' => $ahora],
            [
                'Filtros',
                'Lubricantes',
                'Frenos',
                'Eléctrico',
                'Motor',
                'Transmisión',
                'Suspensión y dirección',
                'Llantas',
                'Carrocería',
                'Ferretería',
                'Herramientas',
                'Limpieza',
                'Seguridad',
                'Otros',
            ],
        ));
    }

    public function down(): void
    {
        Schema::dropIfExists('movimientos_almacen');
        Schema::dropIfExists('detalles_inventario');
        Schema::dropIfExists('inventarios_almacen');
        Schema::dropIfExists('detalles_ingreso');
        Schema::dropIfExists('ingresos_almacen');
        Schema::dropIfExists('detalles_salida');
        Schema::dropIfExists('salidas_almacen');
        Schema::dropIfExists('proveedores');
        Schema::dropIfExists('articulos');
        Schema::dropIfExists('categorias_articulo');
    }
};
