<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Los números de factura que faltan en el correlativo y alguien ya
     * explicó (se anuló en SUNAT, no es un flete): dejan de avisarse.
     */
    public function up(): void
    {
        Schema::create('numeros_de_factura_explicados', function (Blueprint $table) {
            $table->id();
            $table->string('serie', 10);
            $table->unsignedInteger('numero');
            $table->string('motivo', 30);
            $table->string('nota', 255)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['serie', 'numero']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('numeros_de_factura_explicados');
    }
};
