<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Una ruta que vuelve sin carga cuesta la vuelta igual: la unidad rueda y
 * queda tomada esos días. Se guardan aparte de la ida para que la proforma
 * diga cuánto del precio es el regreso en vacío.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cotizaciones', function (Blueprint $table): void {
            $table->unsignedInteger('km_retorno')->default(0)->after('dias');
            $table->decimal('dias_retorno', 6, 2)->default(0)->after('km_retorno');
        });
    }

    public function down(): void
    {
        Schema::table('cotizaciones', function (Blueprint $table): void {
            $table->dropColumn(['km_retorno', 'dias_retorno']);
        });
    }
};
