<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;

/**
 * Saca de la base el rol `conductor`, que nunca tuvo pantalla propia: el login
 * manda al tablero y ese rol no lo podía ver, así que su único destino posible
 * era un 403.
 *
 * Borrar el rol arrastra sus filas de `model_has_roles`, y cualquier usuario
 * que lo tuviera queda sin rol —sin acceso a nada— hasta que un admin le
 * asigne uno. Es lo correcto: no hay a qué degradarlo automáticamente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Role::where('name', 'conductor')->where('guard_name', 'web')->delete();
    }

    /**
     * Repone el rol vacío. Las asignaciones que tenía no se recuperan: quién lo
     * tenía no se guarda en ningún lado.
     */
    public function down(): void
    {
        Role::findOrCreate('conductor', 'web');
    }
};
