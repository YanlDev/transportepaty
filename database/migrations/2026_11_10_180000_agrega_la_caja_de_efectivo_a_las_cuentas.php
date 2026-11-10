<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Hay clientes que pagan en efectivo y ese cobro no tenía dónde anotarse: la
 * entidad solo ofrecía cuentas de banco. La caja entra como una cuenta más,
 * de otro tipo y sin número de cuenta, y se deja creada una en soles donde
 * ya hay cuentas registradas.
 *
 * Las cuentas y los cobros que ya existían no cambian: todas quedan como
 * cuentas de banco.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cuentas_bancarias', function (Blueprint $table): void {
            $table->string('tipo', 10)->default('banco')->after('id');
            // La caja no tiene número de cuenta.
            $table->string('numero_cuenta')->nullable()->change();
        });

        // Solo donde ya se cobra: una instalación vacía crea su caja desde
        // la pantalla de Cuentas cuando la necesite.
        if (DB::table('cuentas_bancarias')->exists()) {
            DB::table('cuentas_bancarias')->insert([
                'tipo' => 'caja',
                'banco' => 'Caja',
                'alias' => 'EFECTIVO',
                'numero_cuenta' => null,
                'moneda' => 'PEN',
                'activa' => true,
                'notas' => 'Cobros en efectivo. Creada por el sistema.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * La caja se conserva: puede tener cobros encima, y devolver el número de
     * cuenta a obligatorio fallaría con ella adentro.
     */
    public function down(): void
    {
        Schema::table('cuentas_bancarias', function (Blueprint $table): void {
            $table->dropColumn('tipo');
        });
    }
};
