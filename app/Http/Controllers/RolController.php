<?php

namespace App\Http\Controllers;

use App\Enums\Permiso;
use App\Http\Requests\GuardarRolRequest;
use App\Services\SincronizadorPermisos;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

/**
 * El panel de roles y permisos: qué puede hacer cada rol, casilla por casilla.
 *
 * El admin se muestra pero no se edita ni se borra: siempre tiene todo, y es
 * lo que garantiza que quede alguien capaz de volver a repartir permisos.
 */
class RolController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('administrar-roles');

        $roles = Role::query()
            ->where('guard_name', 'web')
            ->with('permissions:id,name')
            ->withCount('users')
            ->orderByRaw('name = ? desc', [SincronizadorPermisos::ROL_ADMIN])
            ->orderBy('name')
            ->get()
            ->map(fn (Role $rol): array => [
                'id' => $rol->id,
                'name' => $rol->name,
                'usuarios' => $rol->users_count,
                'permisos' => $rol->permissions->pluck('name')->values(),
                'bloqueado' => $rol->name === SincronizadorPermisos::ROL_ADMIN,
            ]);

        return Inertia::render('roles/index', [
            'roles' => $roles,
            'modulos' => self::modulos(),
        ]);
    }

    public function store(GuardarRolRequest $request): RedirectResponse
    {
        Gate::authorize('administrar-roles');

        $rol = Role::create(['name' => $request->validated('name'), 'guard_name' => 'web']);

        $base = $request->filled('copiar_de')
            ? Role::query()->with('permissions:id,name')->find($request->integer('copiar_de'))
            : null;

        $rol->load('permissions')->syncPermissions(
            $base?->permissions->pluck('name')->all() ?? $request->validated('permisos', []),
        );

        return to_route('roles.index', ['rol' => $rol->id])
            ->with('toast', ['type' => 'success', 'message' => "Rol «{$rol->name}» creado."]);
    }

    public function update(GuardarRolRequest $request, Role $rol): RedirectResponse
    {
        Gate::authorize('administrar-roles');

        if ($rol->name === SincronizadorPermisos::ROL_ADMIN) {
            return back()->with('toast', ['type' => 'error', 'message' => 'El administrador siempre tiene todos los permisos.']);
        }

        $rol->update(['name' => $request->validated('name')]);
        $rol->load('permissions')->syncPermissions($request->validated('permisos', []));

        return to_route('roles.index', ['rol' => $rol->id])
            ->with('toast', ['type' => 'success', 'message' => "Permisos de «{$rol->name}» guardados."]);
    }

    /**
     * Un rol con gente adentro no se borra: esas cuentas quedarían sin acceso
     * a nada sin que nadie lo decidiera. Primero se las pasa a otro rol.
     */
    public function destroy(Role $rol): RedirectResponse
    {
        Gate::authorize('administrar-roles');

        if ($rol->name === SincronizadorPermisos::ROL_ADMIN) {
            return back()->with('toast', ['type' => 'error', 'message' => 'El rol administrador no se puede eliminar.']);
        }

        if ($rol->users()->exists()) {
            return back()->with('toast', ['type' => 'error', 'message' => 'No se puede eliminar: hay usuarios con este rol.']);
        }

        $rol->delete();

        return to_route('roles.index')
            ->with('toast', ['type' => 'success', 'message' => 'Rol eliminado.']);
    }

    /**
     * El catálogo agrupado como lo pinta la matriz: un bloque por módulo.
     *
     * @return list<array{clave: string, nombre: string, permisos: list<array{value: string, label: string}>}>
     */
    public static function modulos(): array
    {
        $porModulo = [];

        foreach (Permiso::cases() as $permiso) {
            $porModulo[$permiso->modulo()][] = ['value' => $permiso->value, 'label' => $permiso->label()];
        }

        return array_map(
            fn (string $clave, string $nombre): array => [
                'clave' => $clave,
                'nombre' => $nombre,
                'permisos' => $porModulo[$clave] ?? [],
            ],
            array_keys(Permiso::MODULOS),
            Permiso::MODULOS,
        );
    }
}
