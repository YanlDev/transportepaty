<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Un mes cerrado de la cobranza: sus facturas y sus cobros ya no se
     * crean, corrigen ni anulan, hasta que alguien lo reabra.
     */
    public function up(): void
    {
        Schema::create('cierres_de_mes', function (Blueprint $table) {
            $table->id();
            $table->string('mes', 7)->unique();
            $table->foreignId('cerrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cierres_de_mes');
    }
};
