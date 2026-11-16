<?php

namespace Tests\Support;

/** Identity headers Traefik injects after ForwardAuth. */
final class GatewayHeaders
{
    /** @return array<string, string> */
    public static function client(int $clientId = 10): array
    {
        return ['X-User-Id' => (string) $clientId, 'X-User-Role' => 'client', 'X-User-Client-Id' => (string) $clientId];
    }

    /** @return array<string, string> */
    public static function staff(string $role = 'mechanic'): array
    {
        return ['X-User-Id' => '1', 'X-User-Role' => $role, 'X-User-Client-Id' => ''];
    }
}
