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
        Schema::create('importacion_filas', function (Blueprint $table) {
            $table->id();

            $table->foreignId('importacion_id')->constrained('importaciones')->cascadeOnDelete();

            // Fila del Excel, para poder señalar «revisa la fila 14» si algo
            // sale mal. La cabecera está en la fila 2, así que la primera fila
            // de datos es la 3.
            $table->unsignedSmallInteger('numero_fila');

            // Lo que decía la celda, tal cual, columna por columna. Es la
            // evidencia: cuando algo no se resuelve, hay que poder ver
            // exactamente qué había ahí sin volver a abrir el archivo.
            $table->json('crudo');

            // Resueltos contra el catálogo. Nulos mientras no se pudieron
            // resolver; el texto crudo de arriba es lo que queda para revisar.
            $table->foreignId('tracto_id')->nullable()->constrained('vehiculos')->nullOnDelete();
            $table->foreignId('carreta_id')->nullable()->constrained('vehiculos')->nullOnDelete();
            $table->foreignId('conductor_id')->nullable()->constrained('conductores')->nullOnDelete();
            $table->string('tipo_carga')->nullable();
            $table->foreignId('origen_id')->nullable()->constrained('ubicaciones')->nullOnDelete();
            $table->foreignId('destino_id')->nullable()->constrained('ubicaciones')->nullOnDelete();
            $table->foreignId('ubicacion_id')->nullable()->constrained('ubicaciones')->nullOnDelete();
            $table->text('observaciones')->nullable();

            // Problemas de resolución detectados sobre esta fila —tracto no
            // encontrado, ruta escrita en la columna de carga, etc.—, no las
            // alertas de negocio: esas las calculan los validadores de siempre
            // en cuanto la fila se convierte en un estado diario.
            $table->json('problemas')->nullable();

            // El usuario puede destildar una fila antes de confirmar, típico
            // cuando el tracto no se encontró y no hay nada que aplicar.
            $table->boolean('incluir')->default(true);

            // Se llena al confirmar, apuntando al estado diario que resultó.
            $table->foreignId('estado_unidad_id')->nullable()->constrained('estados_unidad')->nullOnDelete();

            $table->timestamps();

            $table->index(['importacion_id', 'numero_fila']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('importacion_filas');
    }
};
