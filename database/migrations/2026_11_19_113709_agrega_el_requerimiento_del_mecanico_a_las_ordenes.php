<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La orden nace como pre-orden de operaciones; el mecánico arma el
     * requerimiento (tareas, repuestos y fotos del antes) y lo envía, y
     * operaciones lo aprueba: recién ahí es una orden de trabajo.
     */
    public function up(): void
    {
        Schema::table('ordenes_trabajo', function (Blueprint $table) {
            $table->dateTime('requerimiento_at')->nullable()->after('prioridad');
            $table->foreignId('requerimiento_por')->nullable()->after('requerimiento_at')->constrained('users')->nullOnDelete();
            $table->dateTime('aprobado_at')->nullable()->after('requerimiento_por');
            $table->foreignId('aprobado_por')->nullable()->after('aprobado_at')->constrained('users')->nullOnDelete();
            $table->text('devolucion_requerimiento')->nullable()->after('aprobado_por');
        });

        Schema::create('repuestos_orden', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orden_trabajo_id')->constrained('ordenes_trabajo')->cascadeOnDelete();
            // Del catálogo del almacén si existe ahí; si no, va en texto.
            $table->foreignId('articulo_id')->nullable()->constrained('articulos')->nullOnDelete();
            $table->string('descripcion', 300);
            $table->decimal('cantidad', 10, 2);
            $table->string('unidad', 20)->nullable();
            // Pedido después de aprobado el requerimiento: se aprueba aparte.
            $table->boolean('adicional')->default(false);
            $table->dateTime('aprobado_at')->nullable();
            $table->foreignId('aprobado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index('orden_trabajo_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repuestos_orden');

        Schema::table('ordenes_trabajo', function (Blueprint $table) {
            $table->dropConstrainedForeignId('requerimiento_por');
            $table->dropConstrainedForeignId('aprobado_por');
            $table->dropColumn(['requerimiento_at', 'aprobado_at', 'devolucion_requerimiento']);
        });
    }
};
