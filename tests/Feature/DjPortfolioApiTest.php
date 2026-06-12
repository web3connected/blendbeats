<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DjPortfolioApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_activate_and_manage_portfolio_media(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['name' => 'DJ Portfolio']);

        $this->actingAs($user)
            ->postJson('/api/media/setup')
            ->assertOk()
            ->assertJsonPath('media_account.status', 'active')
            ->assertJsonPath('features.0.feature_key', 'media_library');

        $this->actingAs($user)
            ->getJson('/api/media/files?disk=public&collection=dj_media')
            ->assertOk()
            ->assertJsonPath('files', []);

        $fileId = $this->actingAs($user)
            ->postJson('/api/media/files', [
                'file' => UploadedFile::fake()->create('first-mix.mp3', 128, 'audio/mpeg'),
                'disk' => 'public',
                'collection' => 'dj_media',
                'title' => 'First Mix',
                'description' => 'Opening set with scratches.',
                'genre' => 'Hip-Hop',
                'visibility' => 'draft',
                'media_kind' => 'mix',
            ])
            ->assertCreated()
            ->assertJsonPath('file.original_name', 'first-mix.mp3')
            ->assertJsonPath('file.is_audio', true)
            ->assertJsonPath('file.portfolio_title', 'First Mix')
            ->assertJsonPath('file.portfolio_genre', 'Hip-Hop')
            ->assertJsonPath('file.portfolio_visibility', 'draft')
            ->assertJsonPath('file.path', fn (string $path): bool => str_starts_with($path, 'media/portfolios/dj-portfolio/'))
            ->assertJsonPath('file.url', fn (string $url): bool => str_contains($url, '/media/portfolios/dj-portfolio/'))
            ->json('file.id');

        $this->actingAs($user)
            ->deleteJson("/api/media/files/{$fileId}")
            ->assertOk()
            ->assertJsonPath('deleted', true);
    }
}
