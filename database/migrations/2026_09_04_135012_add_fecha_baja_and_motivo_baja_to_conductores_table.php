<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('conductores', function (Blueprint $table) {
            $table->date('fecha_baja')->nullable()->after('activo');
            $table->string('motivo_baja')->nullable()->after('fecha_baja');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('conductores', function (Blueprint $table) {
            $table->dropColumn(['fecha_baja', 'motivo_baja']);
        });
    }
};
