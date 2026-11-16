<?php

namespace App\Http\Middleware;

use App\Http\GatewayIdentity;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Usage: ->middleware('role:client') or 'role:receptionist,mechanic'.
 */
class EnsureGatewayRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (! in_array(GatewayIdentity::fromRequest($request)->role, $roles, true)) {
            return response()->json(['message' => 'Acesso não autorizado para este perfil.'], 403);
        }

        return $next($request);
    }
}
