<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El cobro deja de ser una fecha dentro de la factura y pasa a ser un
     * registro propio, como en el banco: un depósito (fecha, cuenta, N° de
     * operación, monto) que se reparte entre una o varias facturas. Así una
     * factura puede cobrarse en partes y un depósito sin factura queda como
     * saldo a favor del cliente.
     *
     * En la factura quedan, calculados solos, lo cobrado hasta hoy
     * (`cobrado`) y —cuando ya entró todo el neto— la fecha, la cuenta y la
     * operación del último cobro, que es lo que leen la tabla y el Excel.
     */
    public function up(): void
    {
        Schema::create('cobros', function (Blueprint $table) {
            $table->id();
            $table->date('fecha')->index();
            $table->foreignId('cuenta_bancaria_id')->nullable()->constrained('cuentas_bancarias')->nullOnDelete();
            $table->string('numero_operacion', 30)->nullable();
            $table->decimal('monto', 12, 2);
            $table->string('moneda', 3)->default('PEN');
            // De quién es la plata: hace falta cuando sobra saldo a favor.
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->text('observacion')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('anulado_at')->nullable();
            $table->foreignId('anulado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('motivo_anulacion', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('cobro_factura', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cobro_id')->constrained('cobros')->cascadeOnDelete();
            $table->foreignId('factura_id')->constrained('facturas')->cascadeOnDelete();
            $table->decimal('monto', 12, 2);
            $table->timestamps();
            $table->unique(['cobro_id', 'factura_id']);
        });

        Schema::table('facturas', function (Blueprint $table) {
            $table->decimal('cobrado', 12, 2)->default(0);
        });

        $this->convertirLosCobrosQueYaHabia();
    }

    /**
     * Cada factura cobrada pasa a tener su cobro, por su neto. Las que
     * comparten N° de operación, fecha, cuenta y moneda son un solo depósito
     * que pagó varias: un cobro repartido entre ellas. Sin N° de operación
     * no se junta nada: un cobro por factura.
     */
    private function convertirLosCobrosQueYaHabia(): void
    {
        $clientes = DB::table('clientes')->whereNotNull('ruc')->pluck('id', 'ruc');
        $cobradas = DB::table('facturas')
            ->whereNotNull('fecha_pago')
            ->whereNotNull('neto')
            ->orderBy('fecha_pago')
            ->orderBy('id')
            ->get(['id', 'fecha_pago', 'cuenta_bancaria_id', 'numero_operacion', 'neto', 'moneda', 'cliente_ruc']);

        $grupos = $cobradas->groupBy(fn (object $factura): string => filled($factura->numero_operacion)
            ? implode('|', [mb_strtoupper(trim($factura->numero_operacion)), $factura->fecha_pago, $factura->cuenta_bancaria_id, $factura->moneda])
            : 'factura-'.$factura->id);

        foreach ($grupos as $facturas) {
            $primera = $facturas->first();
            $rucs = $facturas->pluck('cliente_ruc')->unique();

            $cobroId = DB::table('cobros')->insertGetId([
                'fecha' => $primera->fecha_pago,
                'cuenta_bancaria_id' => $primera->cuenta_bancaria_id,
                'numero_operacion' => filled($primera->numero_operacion) ? trim($primera->numero_operacion) : null,
                'monto' => round((float) $facturas->sum(fn (object $factura): float => (float) $factura->neto), 2),
                'moneda' => $primera->moneda,
                // Solo si todas son del mismo cliente.
                'cliente_id' => $rucs->count() === 1 ? $clientes->get($rucs->first()) : null,
                'observacion' => 'Cobro registrado antes de que existieran los cobros.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('cobro_factura')->insert($facturas->map(fn (object $factura): array => [
                'cobro_id' => $cobroId,
                'factura_id' => $factura->id,
                'monto' => $factura->neto,
                'created_at' => now(),
                'updated_at' => now(),
            ])->all());
        }

        DB::table('facturas')->whereNotNull('fecha_pago')->whereNotNull('neto')->update(['cobrado' => DB::raw('neto')]);
    }

    public function down(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->dropColumn('cobrado');
        });
        Schema::dropIfExists('cobro_factura');
        Schema::dropIfExists('cobros');
    }
};
