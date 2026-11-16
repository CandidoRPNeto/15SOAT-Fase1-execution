<?php

namespace App\Http;

use Illuminate\Http\Request;

/**
 * Caller identity injected by Traefik ForwardAuth (ADR-013): the OS Service
 * validated the token and returned X-User-* headers.
 */
final readonly class GatewayIdentity
{
    public const ATTRIBUTE = 'gateway_identity';

    public function __construct(
        public int $userId,
        public string $role,
        public ?int $clientId,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return $request->attributes->get(self::ATTRIBUTE);
    }

    public function isClient(): bool
    {
        return $this->role === 'client';
    }

    /** Staff sees everything; a client only its own records. */
    public function canSee(int $ownerClientId): bool
    {
        return ! $this->isClient() || $this->clientId === $ownerClientId;
    }
}
