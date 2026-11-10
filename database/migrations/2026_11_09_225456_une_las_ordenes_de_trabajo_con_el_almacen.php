<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La orden de trabajo pasa a regirse como un documento del almacén:
     *
     * - No se borra, se anula (`anulado_at`, `anulado_por`,
     *   `motivo_anulacion`), igual que los vales y las compras.
     * - `justificacion_materiales`: por qué una preventiva se cerró sin que
     *   saliera del almacén todo lo que pide su nivel.
     * - Los vales y el kardex dejan de soltar su orden si esta desaparece:
     *   antes quedaban sin destino y el costo de la orden se perdía.
     *
     * No toca ninguna fila existente.
     */
    public function up(): void
    {
        Schema::table('ordenes_trabajo', function (Blueprint $table) {
            $table->string('justificacion_materiales', 1000)->nullable()->after('observacion');
            $table->timestamp('anulado_at')->nullable();
            $table->foreignId('anulado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('motivo_anulacion', 500)->nullable();
        });

        foreach (['salidas_almacen', 'movimientos_almacen'] as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->dropForeign(['orden_trabajo_id']);
                $table->foreign('orden_trabajo_id')->references('id')->on('ordenes_trabajo')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['salidas_almacen', 'movimientos_almacen'] as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->dropForeign(['orden_trabajo_id']);
                $table->foreign('orden_trabajo_id')->references('id')->on('ordenes_trabajo')->nullOnDelete();
            });
        }

        Schema::table('ordenes_trabajo', function (Blueprint $table) {
            $table->dropConstrainedForeignId('anulado_por');
            $table->dropColumn(['justificacion_materiales', 'anulado_at', 'motivo_anulacion']);
        });
    }
};
