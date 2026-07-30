<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Índices únicos parciales que impiden que un mismo fierro o conductor
     * aparezca en dos asignaciones vigentes a la vez. Se declaran en SQL crudo
     * porque el Blueprint no expone la cláusula WHERE; la sintaxis es común a
     * PostgreSQL (producción) y SQLite (tests).
     *
     * @var array<string, string>
     */
    private const INDICES_VIGENTES = [
        'asignaciones_conductor_vigente_unique' => 'conductor_id',
        'asignaciones_tracto_vigente_unique' => 'tracto_id',
        'asignaciones_carreta_vigente_unique' => 'carreta_id',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('asignaciones', function (Blueprint $table) {
            $table->id();

            $table->foreignId('conductor_id')->constrained('conductores')->cascadeOnDelete();
            $table->foreignId('tracto_id')->constrained('vehiculos')->cascadeOnDelete();

            // La carreta es opcional: un tracto puede quedar asignado sin ella.
            $table->foreignId('carreta_id')->nullable()->constrained('vehiculos')->nullOnDelete();

            // `desde` la estampa el sistema al registrar; `hasta` nulo significa
            // que la asignación sigue vigente. Cerrarla en vez de sobrescribirla
            // es lo que conserva el historial de quién anduvo con qué unidad.
            $table->date('desde');
            $table->date('hasta')->nullable();

            $table->text('observaciones')->nullable();

            $table->timestamps();

            $table->index('hasta');
        });

        foreach (self::INDICES_VIGENTES as $nombre => $columna) {
            DB::statement("CREATE UNIQUE INDEX {$nombre} ON asignaciones ({$columna}) WHERE hasta IS NULL");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asignaciones');
    }
};
