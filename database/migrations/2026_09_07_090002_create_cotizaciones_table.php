<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cotizaciones', function (Blueprint $table) {
            $table->id();
            $table->string('numero')->unique();
            $table->date('fecha');
            $table->date('valido_hasta');

            // Mismo criterio que en `viajes`: el cliente puede estar en el
            // padrón o no —a un particular se le cotiza antes de darlo de
            // alta—, así que el nombre y el RUC viajan como texto y la FK es
            // solo el enlace cuando existe.
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->string('cliente_nombre');
            $table->string('cliente_ruc', 11)->nullable();

            $table->foreignId('punto_partida_id')->nullable()->constrained('puntos_traslado')->nullOnDelete();
            $table->foreignId('punto_llegada_id')->nullable()->constrained('puntos_traslado')->nullOnDelete();
            $table->string('origen');
            $table->string('destino');
            $table->string('material')->nullable();

            $table->unsignedInteger('km');
            $table->decimal('dias', 6, 2);

            // Los costos propios del tramo, uno por concepto: son todos
            // directos y son lo primero que se discute cuando el cliente
            // pregunta por qué una ruta cuesta más que otra de los mismos
            // kilómetros.
            $table->decimal('peajes', 10, 2)->default(0);
            $table->decimal('viaticos', 10, 2)->default(0);
            $table->decimal('alojamiento', 10, 2)->default(0);
            $table->decimal('cochera', 10, 2)->default(0);
            $table->decimal('carga_descarga', 10, 2)->default(0);
            $table->decimal('otros_ruta', 10, 2)->default(0);

            // La foto del desglose con el que se emitió: nombre, tipo,
            // naturaleza, tasa e importe de cada componente. Si mañana sube el
            // diésel y se editan los parámetros, una proforma ya entregada no
            // puede cambiar de precio sola.
            $table->json('desglose');
            $table->decimal('margen_pct', 5, 4);

            $table->decimal('total_directo', 12, 2);
            $table->decimal('total_indirecto', 12, 2);
            $table->decimal('costo_operativo', 12, 2);
            $table->decimal('margen', 12, 2);
            $table->decimal('subtotal', 12, 2);
            $table->decimal('igv', 12, 2);
            $table->decimal('total', 12, 2);

            $table->string('estado')->default('borrador');
            $table->text('notas')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cotizaciones');
    }
};
