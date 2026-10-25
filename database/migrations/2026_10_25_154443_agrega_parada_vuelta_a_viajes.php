<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * En qué parada de qué vuelta cayó cada GR. Nullable: una GR existe
     * aunque todavía no se haya armado su vuelta, y borrar una vuelta no
     * borra sus guías.
     */
    public function up(): void
    {
        Schema::table('viajes', function (Blueprint $table) {
            $table->foreignId('parada_vuelta_id')->nullable()->after('conductor_id')
                ->constrained('paradas_vuelta')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('viajes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parada_vuelta_id');
        });
    }
};
