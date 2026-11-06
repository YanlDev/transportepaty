<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Un usuario que deja la empresa se da de baja, no se borra: su nombre
     * sigue en el historial de lo que hizo y la cuenta ya no entra.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('baja_at')->nullable();
            $table->foreignId('baja_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('motivo_baja', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('baja_por');
            $table->dropColumn(['baja_at', 'motivo_baja']);
        });
    }
};
