<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserPasswordRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', User::class);

        $filtros = [
            'buscar' => $request->string('buscar')->trim()->value(),
        ];

        $usuarios = User::query()
            ->with('roles:id,name')
            ->when($filtros['buscar'], function ($query, string $buscar): void {
                $query->where(function ($query) use ($buscar): void {
                    $query->whereLike('name', "%{$buscar}%", caseSensitive: false)
                        ->orWhereLike('username', "%{$buscar}%", caseSensitive: false)
                        ->orWhereLike('email', "%{$buscar}%", caseSensitive: false);
                });
            })
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        return Inertia::render('usuarios/index', [
            'usuarios' => $usuarios,
            'filtros' => $filtros,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        $this->authorize('create', User::class);

        return Inertia::render('usuarios/create', $this->datosFormulario());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreUserRequest $request): RedirectResponse
    {
        $this->authorize('create', User::class);

        $validated = $request->validated();

        $user = User::create([
            'name' => $validated['name'],
            'username' => $validated['username'],
            'email' => $validated['email'] ?? null,
            'password' => $validated['password'],
        ]);

        // El correo lo carga el admin, así que se da por bueno de entrada. No
        // hay flujo de verificación que lo confirme después, y la columna no es
        // asignable en masa: se fuerza explícitamente.
        $user->forceFill(['email_verified_at' => now()])->save();

        $user->assignRole($validated['role']);

        return to_route('usuarios.index')
            ->with('toast', ['type' => 'success', 'message' => 'Usuario creado correctamente.']);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(User $user): Response
    {
        $this->authorize('update', $user);

        return Inertia::render('usuarios/edit', [
            'usuario' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->getRoleNames()->first(),
            ],
            ...$this->datosFormulario(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $validated = $request->validated();

        $user->update([
            'name' => $validated['name'],
            'username' => $validated['username'],
            'email' => $validated['email'] ?? null,
        ]);

        $user->syncRoles([$validated['role']]);

        return to_route('usuarios.index')
            ->with('toast', ['type' => 'success', 'message' => 'Usuario actualizado correctamente.']);
    }

    /**
     * Update the specified user's password.
     */
    public function updatePassword(UpdateUserPasswordRequest $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $user->update(['password' => $request->validated()['password']]);

        return to_route('usuarios.index')
            ->with('toast', ['type' => 'success', 'message' => 'Contraseña actualizada correctamente.']);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        if ($user->hasRole('admin') && User::role('admin')->count() <= 1) {
            return back()->with('toast', [
                'type' => 'error',
                'message' => 'No se puede eliminar: es el único administrador.',
            ]);
        }

        $user->delete();

        return to_route('usuarios.index')
            ->with('toast', ['type' => 'success', 'message' => 'Usuario eliminado correctamente.']);
    }

    /**
     * Opciones compartidas por los formularios de creación y edición.
     *
     * @return array<string, mixed>
     */
    private function datosFormulario(): array
    {
        return [
            'roles' => Role::query()->orderBy('name')->pluck('name'),
        ];
    }
}
