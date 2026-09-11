<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;

/**
 * La única forma de reponer la contraseña del administrador.
 *
 * El resto de las cuentas las resetea el admin desde `/usuarios`, pero la suya
 * no tiene a quién pedírsela: no hay recuperación por correo en el sistema.
 * Este comando es esa salida, y por eso vive en el servidor y no en la web.
 */
#[Signature('usuario:password
    {usuario : Nombre de usuario o correo de la cuenta}
    {--password= : La contraseña nueva; si se omite, se pide sin mostrarla en pantalla}')]
#[Description('Cambia la contraseña de un usuario desde la consola.')]
class CambiarPasswordUsuario extends Command
{
    public function handle(): int
    {
        $identificador = (string) $this->argument('usuario');

        $user = User::where('username', $identificador)
            ->orWhere('email', $identificador)
            ->first();

        if (! $user instanceof User) {
            $this->components->error("No hay ninguna cuenta con usuario o correo «{$identificador}».");

            return self::FAILURE;
        }

        $nueva = (string) ($this->option('password') ?? password(
            label: "Contraseña nueva para {$user->name} ({$user->username})",
            required: true,
        ));

        $validator = Validator::make(
            ['password' => $nueva],
            ['password' => ['required', Password::defaults()]],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $user->update(['password' => $nueva]);

        $this->components->info("Contraseña actualizada para {$user->username}.");

        return self::SUCCESS;
    }
}
