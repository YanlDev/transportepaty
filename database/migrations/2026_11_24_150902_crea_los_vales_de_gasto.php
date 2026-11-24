<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Los vales de gasto: cualquier área pide plata para algo que no pasa por
     * el almacén (un peaje, un trámite, útiles), gerencia lo aprueba y recién
     * entonces se gasta.
     *
     * - `categorias_gasto`: en qué se va la plata. Las administra gerencia;
     *   una que ya se usó no se borra, se desactiva.
     * - `vales_gasto`: quién pide, de qué área, cuánto, a quién se le paga y
     *   para qué. Si el gasto es de una unidad, dice cuál; sin unidad es un
     *   gasto general del área.
     */
    public function up(): void
    {
        Schema::create('categorias_gasto', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 80)->unique();
            $table->boolean('activa')->default(true);
            $table->timestamps();
        });

        Schema::create('vales_gasto', function (Blueprint $table) {
            $table->id();
            $table->string('estado', 20);
            $table->string('area', 20);
            $table->foreignId('categoria_gasto_id')->constrained('categorias_gasto')->restrictOnDelete();
            $table->string('concepto');
            $table->text('motivo');
            $table->decimal('monto', 12, 2);
            $table->string('beneficiario');
            $table->foreignId('vehiculo_id')->nullable()->constrained('vehiculos')->nullOnDelete();
            $table->foreignId('solicitado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('aprobado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('aprobado_at')->nullable();
            $table->foreignId('rechazado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rechazado_at')->nullable();
            $table->string('motivo_rechazo', 500)->nullable();
            $table->timestamp('anulado_at')->nullable();
            $table->foreignId('anulado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('motivo_anulacion', 500)->nullable();
            $table->timestamps();

            $table->index(['estado', 'area']);
            $table->index('created_at');
        });

        $ahora = now();

        DB::table('categorias_gasto')->insert(array_map(
            fn (string $nombre): array => ['nombre' => $nombre, 'activa' => true, 'created_at' => $ahora, 'updated_at' => $ahora],
            ['Peajes', 'Movilidad', 'Útiles de oficina', 'Servicios', 'Trámites y multas', 'Alquileres', 'Seguros', 'Otros'],
        ));
    }

    public function down(): void
    {
        Schema::dropIfExists('vales_gasto');
        Schema::dropIfExists('categorias_gasto');
    }
};
