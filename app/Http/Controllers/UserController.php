<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserPasswordRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Conductor;
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
            'email' => $validated['email'],
            'password' => $validated['password'],
        ]);

        // Las cuentas creadas a mano se marcan como verificadas para que puedan
        // iniciar sesión sin el flujo de verificación de correo. La columna no
        // es asignable en masa, así que se fuerza explícitamente.
        $user->forceFill(['email_verified_at' => now()])->save();

        $user->assignRole($validated['role']);

        $this->sincronizarConductor(
            $user,
            $validated['role'] === 'conductor' ? ($validated['conductor_id'] ?? null) : null,
        );

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
                'email' => $user->email,
                'role' => $user->getRoleNames()->first(),
                'conductor_id' => Conductor::where('user_id', $user->id)->value('id'),
            ],
            ...$this->datosFormulario($user),
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
            'email' => $validated['email'],
        ]);

        $user->syncRoles([$validated['role']]);

        $this->sincronizarConductor(
            $user,
            $validated['role'] === 'conductor' ? ($validated['conductor_id'] ?? null) : null,
        );

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

        $this->sincronizarConductor($user, null);

        $user->delete();

        return to_route('usuarios.index')
            ->with('toast', ['type' => 'success', 'message' => 'Usuario eliminado correctamente.']);
    }

    /**
     * Sincroniza el vínculo usuario ↔ conductor.
     *
     * Desvincula cualquier conductor ligado actualmente al usuario y, si se
     * indica uno nuevo, lo asocia.
     */
    private function sincronizarConductor(User $user, ?int $conductorId): void
    {
        Conductor::where('user_id', $user->id)
            ->when($conductorId, fn ($query) => $query->whereKeyNot($conductorId))
            ->update(['user_id' => null]);

        if ($conductorId) {
            Conductor::whereKey($conductorId)->update(['user_id' => $user->id]);
        }
    }

    /**
     * Opciones compartidas por los formularios de creación y edición.
     *
     * @return array<string, mixed>
     */
    private function datosFormulario(?User $user = null): array
    {
        return [
            'roles' => Role::query()->orderBy('name')->pluck('name'),
            'conductores' => Conductor::query()
                ->where(function ($query) use ($user): void {
                    $query->whereNull('user_id');

                    if ($user) {
                        $query->orWhere('user_id', $user->id);
                    }
                })
                ->orderBy('apellidos')
                ->orderBy('nombres')
                ->get(['id', 'nombres', 'apellidos']),
        ];
    }
}
