<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Laravel\Sanctum\PersonalAccessToken;

class CheckSanctumToken
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $bearerToken = $request->bearerToken();
        
        if (!$bearerToken) {
            return response()->json([
                'code' => -1,
                'message' => 'Token no proporcionado',
                'result' => null
            ], 401);
        }

        $token = PersonalAccessToken::findToken($bearerToken);

        if (!$token) {
            return response()->json([
                'code' => -1,
                'message' => 'Token inválido o expirado. Por favor, inicie sesión nuevamente.',
                'result' => null
            ], 401);
        }

        if ($token->expires_at && $token->expires_at->isPast()) {
            return response()->json([
                'code' => -1,
                'message' => 'El token ha expirado. Por favor, inicie sesión nuevamente.',
                'result' => null
            ], 401);
        }

        $request->setUserResolver(function () use ($token) {
            return $token->tokenable;
        });

        return $next($request);
    }
}