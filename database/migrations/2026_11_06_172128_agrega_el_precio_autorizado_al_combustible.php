<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El precio por galón con que se autoriza el combustible: de ahí sale el
     * costo estimado del ticket mientras no llega el voucher. El del voucher
     * (`precio_galon`) sigue aparte: es el real.
     *
     * Lo ya registrado con precio toma ese mismo como autorizado.
     */
    public function up(): void
    {
        Schema::table('abastecimientos', function (Blueprint $table) {
            $table->decimal('precio_autorizado', 7, 3)->nullable()->after('galones');
        });

        DB::table('abastecimientos')
            ->whereNotNull('precio_galon')
            ->update(['precio_autorizado' => DB::raw('precio_galon')]);
    }

    public function down(): void
    {
        Schema::table('abastecimientos', function (Blueprint $table) {
            $table->dropColumn('precio_autorizado');
        });
    }
};
