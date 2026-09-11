<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * El rol vive en la base, no en código, así que el seeder no alcanza para
     * los entornos ya desplegados: sin esta migración el rol no existiría en
     * producción y no se podría asignar a nadie.
     */
    public function up(): void
    {
        Role::findOrCreate('contador', 'web');
    }

    public function down(): void
    {
        Role::query()->where('name', 'contador')->where('guard_name', 'web')->delete();
    }
};
