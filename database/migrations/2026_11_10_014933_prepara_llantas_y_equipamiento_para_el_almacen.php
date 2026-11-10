<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Las llantas y el equipamiento de las unidades pasan a salir del
     * almacén, sin perder nada de lo ya registrado.
     *
     * - `categorias_articulo.clave`: reconoce las categorías que el sistema
     *   necesita («llantas», «equipamiento») aunque les cambien el nombre.
     * - `llantas`: de qué artículo es y con qué línea de vale salió. Lo que
     *   ya estaba cargado queda como `previa` (de antes del almacén).
     * - `movimientos_llanta.vehiculo_origen_id`: de qué unidad viene una
     *   llanta que se vuelve a montar en otra.
     * - `items_equipamiento`: su artículo del almacén, a qué unidades aplica
     *   y si lleva fecha de vencimiento (extintor, botiquín).
     * - `movimientos_equipamiento`: lo que una unidad tiene sin haber salido
     *   con vale (el saldo de su primer conteo), lo que pasa a otra unidad y
     *   lo que se da de baja. Lo que sale del almacén no se anota acá: se lee
     *   de los vales de salida a nombre de la unidad.
     * - `vencimientos_equipamiento`: hasta cuándo vale el extintor o el
     *   botiquín de cada unidad.
     * - `detalles_ingreso.vence_el`: el vencimiento de lo que entra al almacén.
     *
     * Se agregan al catálogo los ítems que faltaban (extintor, botiquín,
     * pico, pala, kit antiderrame, conos, caja de herramientas).
     */
    public function up(): void
    {
        Schema::table('categorias_articulo', function (Blueprint $table) {
            $table->string('clave', 30)->nullable()->unique()->after('nombre');
        });

        Schema::table('llantas', function (Blueprint $table) {
            $table->foreignId('articulo_id')->nullable()->after('vehiculo_id')->constrained('articulos')->restrictOnDelete();
            $table->foreignId('detalle_salida_id')->nullable()->after('articulo_id')->constrained('detalles_salida')->restrictOnDelete();
            $table->string('origen', 20)->default('previa')->after('detalle_salida_id');
        });

        Schema::table('movimientos_llanta', function (Blueprint $table) {
            $table->foreignId('vehiculo_origen_id')->nullable()->after('vehiculo_id')->constrained('vehiculos')->nullOnDelete();
        });

        Schema::table('items_equipamiento', function (Blueprint $table) {
            $table->foreignId('articulo_id')->nullable()->after('nombre')->constrained('articulos')->restrictOnDelete();
            $table->string('aplica_a', 10)->default('carreta')->after('articulo_id');
            $table->boolean('lleva_vencimiento')->default(false)->after('aplica_a');
        });

        Schema::create('movimientos_equipamiento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehiculo_id')->constrained('vehiculos')->cascadeOnDelete();
            $table->foreignId('item_equipamiento_id')->constrained('items_equipamiento')->restrictOnDelete();
            $table->string('tipo', 20);
            $table->unsignedSmallInteger('cantidad');
            $table->date('fecha');
            // A qué unidad pasó, en un traslado.
            $table->foreignId('vehiculo_destino_id')->nullable()->constrained('vehiculos')->nullOnDelete();
            // El conteo que lo originó, cuando es el saldo inicial.
            $table->foreignId('conteo_equipamiento_id')->nullable()->constrained('conteos_equipamiento')->cascadeOnDelete();
            $table->string('motivo', 500)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('anulado_at')->nullable();
            $table->foreignId('anulado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('motivo_anulacion', 500)->nullable();
            $table->timestamps();

            $table->index(['vehiculo_id', 'item_equipamiento_id']);
            $table->index('vehiculo_destino_id');
        });

        Schema::create('vencimientos_equipamiento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehiculo_id')->constrained('vehiculos')->cascadeOnDelete();
            $table->foreignId('item_equipamiento_id')->constrained('items_equipamiento')->cascadeOnDelete();
            $table->date('vence_el');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['vehiculo_id', 'item_equipamiento_id']);
        });

        Schema::table('detalles_ingreso', function (Blueprint $table) {
            $table->date('vence_el')->nullable()->after('costo_total');
        });

        $ahora = now();

        foreach (['llantas' => 'Llantas', 'equipamiento' => 'Equipamiento'] as $clave => $nombre) {
            $existe = DB::table('categorias_articulo')->whereRaw('lower(nombre) = ?', [mb_strtolower($nombre)])->value('id');

            if ($existe === null) {
                DB::table('categorias_articulo')->insert(['nombre' => $nombre, 'clave' => $clave, 'created_at' => $ahora, 'updated_at' => $ahora]);
            } else {
                DB::table('categorias_articulo')->where('id', $existe)->update(['clave' => $clave]);
            }
        }

        $orden = (int) DB::table('items_equipamiento')->max('orden');

        foreach ([
            ['Extintor', true],
            ['Botiquín', true],
            ['Conos de seguridad', false],
            ['Kit antiderrame', false],
            ['Pico', false],
            ['Pala', false],
            ['Caja de herramientas', false],
        ] as [$nombre, $vence]) {
            if (DB::table('items_equipamiento')->whereRaw('lower(nombre) = ?', [mb_strtolower($nombre)])->exists()) {
                continue;
            }

            DB::table('items_equipamiento')->insert([
                'nombre' => $nombre,
                'aplica_a' => 'tracto',
                'lleva_vencimiento' => $vence,
                'minimo' => null,
                'orden' => ++$orden,
                'activo' => true,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('detalles_ingreso', function (Blueprint $table) {
            $table->dropColumn('vence_el');
        });

        Schema::dropIfExists('vencimientos_equipamiento');
        Schema::dropIfExists('movimientos_equipamiento');

        Schema::table('items_equipamiento', function (Blueprint $table) {
            $table->dropConstrainedForeignId('articulo_id');
            $table->dropColumn(['aplica_a', 'lleva_vencimiento']);
        });

        Schema::table('movimientos_llanta', function (Blueprint $table) {
            $table->dropConstrainedForeignId('vehiculo_origen_id');
        });

        Schema::table('llantas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('detalle_salida_id');
            $table->dropConstrainedForeignId('articulo_id');
            $table->dropColumn('origen');
        });

        Schema::table('categorias_articulo', function (Blueprint $table) {
            $table->dropUnique(['clave']);
            $table->dropColumn('clave');
        });
    }
};
