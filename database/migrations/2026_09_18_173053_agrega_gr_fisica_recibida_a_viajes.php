<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cuándo llegó a la oficina el papel de la GR. Lo marca a mano la cobranza;
 * nulo es «todavía no llegó».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('viajes', function (Blueprint $table): void {
            $table->timestamp('gr_fisica_recibida_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('viajes', function (Blueprint $table): void {
            $table->dropColumn('gr_fisica_recibida_at');
        });
    }
};
