<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Una cuenta dada de baja no sigue adentro aunque tuviera la sesión abierta
 * (o entrara con su llave de acceso): se cierra la sesión y vuelve al login.
 */
class CerrarSesionDeBaja
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        if ($usuario !== null && $usuario->estaDeBaja()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['username' => 'Esta cuenta está dada de baja.']);
        }

        return $next($request);
    }
}
