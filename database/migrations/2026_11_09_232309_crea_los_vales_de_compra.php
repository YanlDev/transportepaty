<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El circuito de compra: solicitud → aprobación → vale firmado → compra →
     * ingreso al almacén ligado al vale.
     *
     * - `vales_compra`: la solicitud y, una vez aprobada, el vale. Guarda
     *   quién pidió y quién aprobó, hasta cuándo vale, y la firma del
     *   servidor sobre su contenido. `codigo_publico` va en el QR;
     *   `codigo_verificacion` es el que se lee y se escribe a mano.
     * - `detalles_vale_compra`: lo que se autoriza a comprar. Una línea sin
     *   artículo es un servicio de un tercero: no entra al almacén, se
     *   confirma como atendido y suma al costo de su orden de trabajo.
     * - `consultas_vale_compra`: cada vez que alguien verificó el vale.
     * - `ingresos_almacen.vale_compra_id`: con qué vale entró la compra.
     */
    public function up(): void
    {
        Schema::create('vales_compra', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 20);
            $table->string('estado', 20);
            $table->string('codigo_publico', 40)->unique();
            $table->string('codigo_verificacion', 20)->unique();
            $table->foreignId('proveedor_id')->constrained('proveedores')->restrictOnDelete();
            $table->string('moneda', 3)->default('PEN');
            $table->decimal('total', 14, 2)->default(0);
            $table->text('motivo');
            // Cómo se verificó el precio cuando no hay cotización adjunta.
            $table->text('sustento_precio')->nullable();
            $table->foreignId('orden_trabajo_id')->nullable()->constrained('ordenes_trabajo')->restrictOnDelete();
            $table->foreignId('vehiculo_id')->nullable()->constrained('vehiculos')->nullOnDelete();
            $table->boolean('permite_parciales')->default(false);
            $table->foreignId('solicitado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('aprobado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('aprobado_at')->nullable();
            $table->date('vence_el')->nullable();
            $table->string('huella', 64)->nullable();
            $table->text('firma')->nullable();
            $table->foreignId('rechazado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rechazado_at')->nullable();
            $table->string('motivo_rechazo', 500)->nullable();
            $table->timestamp('anulado_at')->nullable();
            $table->foreignId('anulado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('motivo_anulacion', 500)->nullable();
            $table->timestamps();

            $table->index(['estado', 'vence_el']);
        });

        Schema::create('detalles_vale_compra', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vale_compra_id')->constrained('vales_compra')->cascadeOnDelete();
            $table->foreignId('articulo_id')->nullable()->constrained('articulos')->restrictOnDelete();
            $table->string('descripcion');
            $table->boolean('es_servicio')->default(false);
            $table->decimal('cantidad', 14, 3);
            // Lo que se paga por unidad, con IGV si lo lleva.
            $table->decimal('precio_unitario', 14, 4);
            $table->decimal('total', 14, 2);
            // Solo para servicios: cuándo se confirmó que el tercero lo hizo.
            $table->timestamp('atendido_at')->nullable();
            $table->foreignId('atendido_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('consultas_vale_compra', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vale_compra_id')->constrained('vales_compra')->cascadeOnDelete();
            $table->string('ip', 45)->nullable();
            $table->string('navegador', 255)->nullable();
            $table->string('situacion', 20);
            $table->timestamp('created_at')->nullable();

            $table->index(['vale_compra_id', 'created_at']);
        });

        Schema::table('ingresos_almacen', function (Blueprint $table) {
            $table->foreignId('vale_compra_id')->nullable()->after('proveedor_id')
                ->constrained('vales_compra')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ingresos_almacen', function (Blueprint $table) {
            $table->dropConstrainedForeignId('vale_compra_id');
        });

        Schema::dropIfExists('consultas_vale_compra');
        Schema::dropIfExists('detalles_vale_compra');
        Schema::dropIfExists('vales_compra');
    }
};
