<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DjHubApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_dj_hub_endpoint_returns_empty_payload_until_profile_tables_exist(): void
    {
        $this->getJson('/api/dj-hub/djs')
            ->assertOk()
            ->assertJsonPath('djs', [])
            ->assertJsonPath('featured_djs', [])
            ->assertJsonPath('filters.genres', [])
            ->assertJsonPath('filters.dj_types', []);
    }
}
