<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A dónde más mandar el preaviso de salida.
 *
 * Son dos casos distintos y por eso viven en tablas distintas: hay
 * conductores que usan otro celular —eso es de la persona y vale para todas
 * sus salidas—, y salidas donde además hay que avisarle a un tercero, como el
 * dueño de la unidad, que solo tiene sentido en esa programación.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conductores', function (Blueprint $table): void {
            $table->string('telefono_alterno', 30)->nullable()->after('telefono');
        });

        Schema::table('programaciones', function (Blueprint $table): void {
            $table->string('whatsapp_adicional', 30)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('conductores', function (Blueprint $table): void {
            $table->dropColumn('telefono_alterno');
        });

        Schema::table('programaciones', function (Blueprint $table): void {
            $table->dropColumn('whatsapp_adicional');
        });
    }
};
