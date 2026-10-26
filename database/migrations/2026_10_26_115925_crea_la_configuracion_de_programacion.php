<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lo que antes estaba fijo en el código de la programación pasa a
     * configurarse desde la app:
     *
     * - `opciones_tramo`: lo que se elige al programar la ida o la vuelta
     *   («Concentrado a Pisco», «Escoria a mina»…), con sus paradas.
     * - `destinos_particulares`: a dónde va la carga particular de vuelta;
     *   si no es Juliaca, de ahí la unidad sigue a Juliaca.
     * - `palabras_zona`: las palabras de la dirección de la GR que delatan
     *   cada zona (ANTAUTA → mina, PARACAS → Pisco…). Las zonas en sí no se
     *   editan: de ellas depende cómo se arma cada ticket.
     *
     * Se cargan con lo que había en el código, tal cual.
     */
    public function up(): void
    {
        Schema::create('opciones_tramo', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 80);
            $table->string('descripcion', 120)->nullable();
            $table->string('sentido', 10);
            // [{zona_origen, zona_destino|null, tipo_carga}]: el destino vacío
            // es el que se elige al programar (carga particular).
            $table->json('paradas');
            $table->boolean('pide_destino')->default(false);
            $table->boolean('activa')->default(true);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();
        });

        Schema::create('destinos_particulares', function (Blueprint $table) {
            $table->id();
            $table->string('lugar', 60)->unique();
            $table->string('zona', 20);
            $table->boolean('activo')->default(true);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();
        });

        Schema::create('palabras_zona', function (Blueprint $table) {
            $table->id();
            $table->string('zona', 20);
            $table->string('palabra', 60)->unique();
            $table->timestamps();

            $table->index('zona');
        });

        $ahora = now();
        $parada = fn (string $origen, ?string $destino, string $carga): array => [
            'zona_origen' => $origen, 'zona_destino' => $destino, 'tipo_carga' => $carga,
        ];

        $opciones = [
            ['Concentrado a Pisco', 'Mina → Pisco', 'ida', [$parada('mina', 'pisco', 'concentrado')]],
            ['Concentrado + metálico al Callao', 'Mina → Pisco → Callao', 'ida', [$parada('mina', 'pisco', 'concentrado'), $parada('pisco', 'lima', 'metalico')]],
            ['Particular a Lima', 'Juliaca → Lima', 'ida', [$parada('juliaca', 'lima', 'particular')]],
            ['Vacía a Lima', 'Juliaca ⇢ Lima', 'ida', [$parada('juliaca', 'lima', 'vacio')]],
            ['Escoria a mina', 'Pisco → Mina', 'regreso', [$parada('pisco', 'mina', 'escoria')]],
            ['Materiales a mina', 'Lima → Mina (Ransa)', 'regreso', [$parada('lima', 'mina', 'materiales')]],
            ['Carga particular', 'Lima → destino, y sigue a Juliaca', 'regreso', [$parada('lima', null, 'particular')]],
            ['Vacía a Juliaca', 'Lima ⇢ Juliaca', 'regreso', [$parada('lima', 'juliaca', 'vacio')]],
        ];

        foreach ($opciones as $orden => [$nombre, $descripcion, $sentido, $paradas]) {
            DB::table('opciones_tramo')->insert([
                'nombre' => $nombre,
                'descripcion' => $descripcion,
                'sentido' => $sentido,
                'paradas' => json_encode($paradas),
                'pide_destino' => collect($paradas)->contains(fn (array $parada): bool => $parada['zona_destino'] === null),
                'activa' => true,
                'orden' => $orden + 1,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        }

        foreach ([['Juliaca', 'juliaca'], ['Arequipa', 'sur'], ['Tacna', 'sur'], ['Moquegua', 'sur'], ['Ilo', 'sur'], ['Cusco', 'cusco'], ['Puno', 'juliaca']] as $orden => [$lugar, $zona]) {
            DB::table('destinos_particulares')->insert([
                'lugar' => $lugar, 'zona' => $zona, 'activo' => true, 'orden' => $orden + 1,
                'created_at' => $ahora, 'updated_at' => $ahora,
            ]);
        }

        $palabras = [
            'mina' => ['ANTAUTA', 'SAN RAFAEL'],
            'pisco' => ['PARACAS', 'PISCO', 'CHINCHA'],
            'lima' => ['CALLAO', 'LIMA', 'HUARAL', 'CHILCA', 'LURIN', 'VENTANILLA', 'HUAURA', 'VEGUETA', 'HUAROCHIRI', 'CAÑETE', 'VILLA EL SALVADOR'],
            'juliaca' => ['JULIACA', 'SAN ROMAN', 'PUNO', 'CHUCUITO', 'EL COLLAO', 'ILAVE', 'DESAGUADERO'],
            'cusco' => ['CUSCO', 'QUISPICANCHI'],
            'sur' => ['AREQUIPA', 'CAYLLOMA', 'CASTILLA', 'ISLAY', 'CAMANA', 'TACNA', 'MOQUEGUA', 'MARISCAL NIETO', 'ILO'],
        ];

        foreach ($palabras as $zona => $lista) {
            foreach ($lista as $palabra) {
                DB::table('palabras_zona')->insert([
                    'zona' => $zona, 'palabra' => $palabra, 'created_at' => $ahora, 'updated_at' => $ahora,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('palabras_zona');
        Schema::dropIfExists('destinos_particulares');
        Schema::dropIfExists('opciones_tramo');
    }
};
