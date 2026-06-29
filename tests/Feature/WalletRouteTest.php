<?php

namespace Tests\Feature;

use Tests\TestCase;

class WalletRouteTest extends TestCase
{
    public function test_guest_wallet_requests_redirect_to_spa_login(): void
    {
        $this->get('/account/wallet')
            ->assertRedirect('/login');
    }

    public function test_spa_login_route_is_named_and_serves_shell(): void
    {
        $this->assertSame('/login', route('login', absolute: false));

        $this->get('/login')
            ->assertOk();
    }
}
