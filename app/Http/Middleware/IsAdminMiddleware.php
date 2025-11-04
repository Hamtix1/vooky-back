<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class IsAdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Primero, comprueba si el usuario está autenticado y si su rol es 'admin'
        if (Auth::check() && Auth::user()->role === 'admin') {
            // Si es admin, permite que la solicitud continúe
            return $next($request);
        }

        // Si no es admin, devuelve una respuesta de error de "prohibido"
        return response()->json(['message' => 'Acceso denegado. Se requiere un rol diferente.'], 403);
    }
}