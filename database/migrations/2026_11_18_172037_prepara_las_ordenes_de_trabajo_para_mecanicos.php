<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La orden deja de ser un formulario de una sola persona: se asigna a uno
     * o varios mecánicos, lleva su lista de tareas y recuerda quién la
     * empezó, quién la terminó y quién de taller la revisó.
     */
    public function up(): void
    {
        Schema::table('ordenes_trabajo', function (Blueprint $table) {
            $table->string('prioridad', 20)->default('normal')->after('estado');
            $table->foreignId('iniciado_por')->nullable()->after('inicio')->constrained('users')->nullOnDelete();
            $table->foreignId('terminado_por')->nullable()->after('termino')->constrained('users')->nullOnDelete();
            $table->text('nota_mecanico')->nullable()->after('observacion');
            $table->foreignId('revisado_por')->nullable()->after('nota_mecanico')->constrained('users')->nullOnDelete();
            $table->dateTime('revisado_at')->nullable()->after('revisado_por');
            $table->text('observacion_revision')->nullable()->after('revisado_at');

            $table->index('estado');
        });

        Schema::create('orden_trabajo_mecanico', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orden_trabajo_id')->constrained('ordenes_trabajo')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('responsable')->default(false);
            $table->timestamps();

            $table->unique(['orden_trabajo_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::create('tareas_orden', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orden_trabajo_id')->constrained('ordenes_trabajo')->cascadeOnDelete();
            $table->string('descripcion', 500);
            $table->unsignedSmallInteger('posicion')->default(0);
            $table->dateTime('hecha_at')->nullable();
            $table->foreignId('hecha_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['orden_trabajo_id', 'posicion']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tareas_orden');
        Schema::dropIfExists('orden_trabajo_mecanico');

        Schema::table('ordenes_trabajo', function (Blueprint $table) {
            $table->dropIndex(['estado']);
            $table->dropConstrainedForeignId('iniciado_por');
            $table->dropConstrainedForeignId('terminado_por');
            $table->dropConstrainedForeignId('revisado_por');
            $table->dropColumn(['prioridad', 'nota_mecanico', 'revisado_at', 'observacion_revision']);
        });
    }
};
