<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthTokenMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Obtener el token del header Authorization
        $token = $request->bearerToken();
        $expectedToken = env('API_TOKEN', 'default_token');

        // Validar el token
        if (!$token || $token !== $expectedToken) {
            return response()->json(['error' => 'No autorizado'], 401);
        }

        return $next($request);
    }
}