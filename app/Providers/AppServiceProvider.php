<?php

namespace App\Providers;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureGates();
    }

    /**
     * Las habilidades que no cuelgan de un modelo y por eso no las puede
     * resolver una policy.
     */
    protected function configureGates(): void
    {
        // El tablero no es una entidad, pero es la pantalla que más junta: la
        // meta mensual, la mezcla de carga por cliente y el estado documental
        // de toda la flota. Sin esto alcanzaba con estar autenticado, y el rol
        // `conductor` —pensado para que un chofer consulte su unidad— entraba
        // a la lectura completa de la operación.
        //
        // El contador sí entra: los viajes son la contrapartida de lo que
        // factura, el mismo criterio con el que `ViajePolicy` lo deja leer el
        // listado.
        Gate::define('ver-tablero', fn (User $user): bool => $user->hasAnyRole(['admin', 'visor', 'contador']));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        // Cualquier N+1 que se cuele revienta acá en vez de aparecer como
        // lentitud silenciosa en producción.
        Model::preventLazyLoading(! app()->isProduction());

        Password::defaults(fn (): Password => Password::min(8));
    }
}
