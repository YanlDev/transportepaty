<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El preaviso que se le manda al conductor antes de salir: queda registrado
 * cuándo se envió y quién lo mandó.
 *
 * No es adorno: una unidad que sale sin guía de remisión es multa de hasta 4
 * UIT, y estas dos columnas son la constancia de que se avisó —frente a SUNAT
 * y frente al conductor que dice que nadie le dijo nada—.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('programaciones', function (Blueprint $table): void {
            $table->timestamp('aviso_enviado_at')->nullable();
            // Queda null si el usuario que avisó se borra: importa que se
            // avisó y cuándo, aunque ya no esté quién lo hizo.
            $table->foreignId('aviso_enviado_por')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('programaciones', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('aviso_enviado_por');
            $table->dropColumn('aviso_enviado_at');
        });
    }
};
