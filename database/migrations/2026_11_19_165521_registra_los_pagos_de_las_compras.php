<?php

use App\Services\SincronizadorPermisos;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El pedido de compra dice cómo se paga (contado o crédito) y cada pago
     * que se le hace queda anotado: fecha, monto, de qué cuenta salió, el
     * número de operación y el voucher. El dinero se mueve en el banco; acá
     * solo se anota que se pagó.
     */
    public function up(): void
    {
        Schema::table('vales_compra', function (Blueprint $table) {
            $table->string('forma_pago', 20)->default('contado')->after('moneda');
            $table->unsignedSmallInteger('dias_credito')->nullable()->after('forma_pago');
        });

        Schema::table('proveedores', function (Blueprint $table) {
            // A qué cuenta se le paga: banco, número, CCI, titular.
            $table->string('cuenta_pago', 500)->nullable()->after('condicion_pago');
        });

        Schema::create('pagos_compra', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vale_compra_id')->constrained('vales_compra')->restrictOnDelete();
            $table->date('fecha');
            $table->decimal('monto', 12, 2);
            $table->string('medio', 20);
            $table->foreignId('cuenta_bancaria_id')->nullable()->constrained('cuentas_bancarias')->nullOnDelete();
            $table->string('numero_operacion', 60)->nullable();
            $table->unsignedBigInteger('voucher_id')->nullable();
            $table->string('observacion', 500)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('anulado_at')->nullable();
            $table->foreignId('anulado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('motivo_anulacion', 500)->nullable();
            $table->timestamps();

            $table->index('vale_compra_id');
        });

        // El permiso de registrar pagos llega a quien le toca según RolBase.
        app(SincronizadorPermisos::class)->sincronizar();
    }

    public function down(): void
    {
        Schema::dropIfExists('pagos_compra');

        Schema::table('proveedores', function (Blueprint $table) {
            $table->dropColumn('cuenta_pago');
        });

        Schema::table('vales_compra', function (Blueprint $table) {
            $table->dropColumn(['forma_pago', 'dias_credito']);
        });
    }
};
