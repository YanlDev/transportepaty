<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `viajes.cliente_ruc` nació con el largo por defecto de `string()` mientras
 * `clientes.ruc` es de 11 y la validación exige `digits:11`. Un RUC es de 11
 * dígitos siempre, así que la columna ancha solo dejaba entrar basura sin que
 * nada la frenara.
 *
 * Se verificó antes de acotarla que el dato real ya cabe: el largo máximo
 * guardado es 11 y no hay ninguna fila con otro largo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('viajes', function (Blueprint $table): void {
            $table->string('cliente_ruc', 11)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('viajes', function (Blueprint $table): void {
            $table->string('cliente_ruc')->nullable()->change();
        });
    }
};
