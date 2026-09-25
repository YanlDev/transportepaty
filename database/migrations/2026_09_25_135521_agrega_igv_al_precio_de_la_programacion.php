<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Si el flete acordado ya lleva el IGV adentro o hay que sumárselo.
 *
 * El monto se guarda tal como se pactó —es lo que se habló con el cliente— y
 * esta bandera dice cómo leerlo; el otro importe se calcula. Guardar los dos
 * montos dejaría dos verdades que pueden contradecirse si cambia la tasa.
 *
 * Por defecto `false`: lo habitual es acordar el flete sin IGV.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('programaciones', function (Blueprint $table): void {
            $table->boolean('precio_incluye_igv')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('programaciones', function (Blueprint $table): void {
            $table->dropColumn('precio_incluye_igv');
        });
    }
};
