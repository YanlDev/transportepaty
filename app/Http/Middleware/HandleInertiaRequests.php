<?php

namespace App\Http\Middleware;

use App\Enums\TipoVehiculo;
use App\Models\Conductor;
use App\Models\User;
use App\Models\Vehiculo;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
                'roles' => $user ? $user->getRoleNames()->all() : [],
            ],
            'sinAsignar' => $this->totalSinAsignar($user),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * Cuántos fierros y conductores están parados sin unidad, para el aviso del
     * menú lateral. Son tres conteos sobre índices; solo se calcula para quien
     * puede ver el módulo de asignaciones.
     */
    private function totalSinAsignar(?User $user): int
    {
        if ($user === null || ! $user->hasAnyRole(['admin', 'visor'])) {
            return 0;
        }

        return Vehiculo::query()->sinAsignar(TipoVehiculo::Tracto)->count()
            + Vehiculo::query()->sinAsignar(TipoVehiculo::Carreta)->count()
            + Conductor::query()->sinAsignar()->count();
    }
}
