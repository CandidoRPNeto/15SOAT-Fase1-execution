<?php

namespace App\Http\Middleware;

use App\Http\GatewayIdentity;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reads the identity headers set by the gateway. The Service is ClusterIP
 * and reachable only through Traefik, which overwrites client-sent X-User-*.
 */
class TrustGatewayIdentity
{
    public function handle(Request $request, Closure $next): Response
    {
        $userId = $request->header('X-User-Id');
        $role = $request->header('X-User-Role');

        if (! is_numeric($userId) || ! in_array($role, ['receptionist', 'mechanic', 'client'], true)) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $clientId = $request->header('X-User-Client-Id');
        $request->attributes->set(GatewayIdentity::ATTRIBUTE, new GatewayIdentity(
            (int) $userId,
            $role,
            is_numeric($clientId) ? (int) $clientId : null,
        ));

        return $next($request);
    }
}
