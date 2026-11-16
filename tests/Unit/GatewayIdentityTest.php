<?php

namespace Tests\Unit;

use App\Http\GatewayIdentity;
use PHPUnit\Framework\TestCase;

class GatewayIdentityTest extends TestCase
{
    public function test_staff_sees_every_record(): void
    {
        $staff = new GatewayIdentity(1, 'mechanic', null);

        $this->assertFalse($staff->isClient());
        $this->assertTrue($staff->canSee(42));
    }

    public function test_client_sees_only_own_records(): void
    {
        $client = new GatewayIdentity(10, 'client', 10);

        $this->assertTrue($client->isClient());
        $this->assertTrue($client->canSee(10));
        $this->assertFalse($client->canSee(11));
    }
}
