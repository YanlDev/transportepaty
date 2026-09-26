<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Los números de las áreas que reciben los avisos de Programación, y los
 * ajustes sueltos (el teléfono de la oficina), pasan del .env a la base: se
 * cambian desde el panel de WhatsApp sin tocar el servidor.
 *
 * Arrancan con lo que ya estaba configurado, para que nada deje de llegar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('areas_aviso', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre', 60);
            $table->string('numero', 20);
            $table->boolean('ve_flete')->default(false);
            $table->boolean('activa')->default(true);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();
        });

        Schema::create('ajustes', function (Blueprint $table): void {
            $table->string('clave', 60)->primary();
            $table->text('valor')->nullable();
            $table->timestamps();
        });

        $ahora = now();

        $areas = array_filter([
            ['nombre' => 'Abastecimiento', 'numero' => config('transpaty.areas.abastecimiento'), 've_flete' => false, 'orden' => 1],
            ['nombre' => 'Facturación', 'numero' => config('transpaty.areas.facturacion'), 've_flete' => true, 'orden' => 2],
        ], fn (array $area): bool => filled($area['numero']));

        foreach ($areas as $area) {
            DB::table('areas_aviso')->insert([...$area, 'activa' => true, 'created_at' => $ahora, 'updated_at' => $ahora]);
        }

        $oficina = config('transpaty.operaciones.telefono_oficina');

        if (filled($oficina)) {
            DB::table('ajustes')->insert(['clave' => 'telefono_oficina', 'valor' => $oficina, 'created_at' => $ahora, 'updated_at' => $ahora]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ajustes');
        Schema::dropIfExists('areas_aviso');
    }
};
