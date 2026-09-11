<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * El nombre de usuario con el que entra cada cuenta.
 *
 * Hasta acá el login era el correo, y eso obligaba a inventarle una casilla a
 * gente que no usa correo. Ahora el identificador es el `username` y el correo
 * queda opcional: lo conserva el admin, que es quien entra con él.
 *
 * `username` no es nulo ni para el admin: Fortify lo usa como atributo del
 * modelo —etiqueta del QR de doble factor y confirmación de contraseña—, así
 * que una cuenta sin él rompería esas dos pantallas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('username', 30)->nullable()->after('name');
        });

        $this->rellenarDesdeElCorreo();

        Schema::table('users', function (Blueprint $table): void {
            $table->string('username', 30)->nullable(false)->change();
            $table->unique('username');
            $table->string('email')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Las cuentas sin correo no pueden volver a un esquema donde el correo
        // es obligatorio: se les pone uno interno para no perderlas.
        DB::table('users')
            ->whereNull('email')
            ->update(['email' => DB::raw("username || '@sin-correo.local'")]);

        Schema::table('users', function (Blueprint $table): void {
            $table->string('email')->nullable(false)->change();
            $table->dropUnique(['username']);
            $table->dropColumn('username');
        });
    }

    /**
     * Le da un usuario a las cuentas que ya existen, derivado de la parte local
     * del correo. Si dos correos distintos dan el mismo usuario, el id desempata.
     */
    private function rellenarDesdeElCorreo(): void
    {
        $usados = [];

        foreach (DB::table('users')->select('id', 'email')->orderBy('id')->get() as $usuario) {
            $base = Str::of($usuario->email)
                ->before('@')
                ->slug('_')
                ->limit(25, '')
                ->value();

            $username = $base === '' ? "usuario_{$usuario->id}" : $base;

            if (in_array($username, $usados, strict: true)) {
                $username = "{$username}_{$usuario->id}";
            }

            $usados[] = $username;

            DB::table('users')->where('id', $usuario->id)->update(['username' => $username]);
        }
    }
};
