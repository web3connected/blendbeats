<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Post;
use App\Services\BlendNewsImageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BlendNewsImageManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('blendnews.images.disk', 'public');
        Storage::fake('public');
    }

    public function test_admin_can_create_story_with_managed_jpeg_image(): void
    {
        $response = $this->actingAs($this->admin(), 'admin')->post('/admin/blendnews', [
            ...$this->validPayload(),
            'featured_image' => UploadedFile::fake()->image('story.jpg', 1200, 630),
        ]);

        $post = Post::query()->sole();
        $path = data_get($post->featured_image, 'path');

        $response->assertRedirect(route('admin.blendnews.edit', $post));
        $this->assertMatchesRegularExpression('#^media/blend-news/[0-9a-f-]+\.jpg$#', $path);
        Storage::disk('public')->assertExists($path);
        $this->assertSame(Storage::disk('public')->url($path), $post->featured_image_url);
    }

    public function test_png_upload_is_accepted(): void
    {
        $this->actingAs($this->admin(), 'admin')->post('/admin/blendnews', [
            ...$this->validPayload(),
            'featured_image' => UploadedFile::fake()->image('story.png'),
        ])->assertSessionHasNoErrors();

        $this->assertStringEndsWith('.png', data_get(Post::query()->sole()->featured_image, 'path'));
    }

    public function test_webp_upload_is_accepted(): void
    {
        $webp = UploadedFile::fake()->createWithContent(
            'story.webp',
            base64_decode('UklGRiIAAABXRUJQVlA4IBYAAAAwAQCdASoBAAEADsD+JaQAA3AAAAAA'),
        );

        $this->actingAs($this->admin(), 'admin')->post('/admin/blendnews', [
            ...$this->validPayload(),
            'featured_image' => $webp,
        ])->assertSessionHasNoErrors();

        $this->assertStringEndsWith('.webp', data_get(Post::query()->sole()->featured_image, 'path'));
    }

    public function test_invalid_and_oversized_files_are_rejected_without_orphans(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')->post('/admin/blendnews', [
            ...$this->validPayload(),
            'featured_image' => UploadedFile::fake()->create('story.pdf', 100, 'application/pdf'),
        ])->assertSessionHasErrors('featured_image');

        $this->actingAs($admin, 'admin')->post('/admin/blendnews', [
            ...$this->validPayload(),
            'featured_image' => UploadedFile::fake()->image('large.jpg')->size(5121),
        ])->assertSessionHasErrors('featured_image');

        $this->assertDatabaseCount('posts', 0);
        $this->assertSame([], Storage::disk('public')->allFiles('media/blend-news'));
    }

    public function test_edit_without_replacement_preserves_existing_image_and_updates_alt_text(): void
    {
        $path = 'media/blend-news/existing.jpg';
        Storage::disk('public')->put($path, 'image');
        $post = $this->postWithImage($path);

        $this->actingAs($this->admin(), 'admin')->put("/admin/blendnews/{$post->id}", [
            ...$this->validPayload(['title' => 'Updated story']),
            'featured_image_alt' => 'Updated alternate text',
        ])->assertSessionHasNoErrors();

        $post->refresh();
        $this->assertSame($path, data_get($post->featured_image, 'path'));
        $this->assertSame('Updated alternate text', data_get($post->featured_image, 'alt'));
        Storage::disk('public')->assertExists($path);
    }

    public function test_replacement_stores_new_image_then_removes_old_managed_image(): void
    {
        $oldPath = 'media/blend-news/old.jpg';
        Storage::disk('public')->put($oldPath, 'old');
        $post = $this->postWithImage($oldPath);

        $this->actingAs($this->admin(), 'admin')->put("/admin/blendnews/{$post->id}", [
            ...$this->validPayload(),
            'featured_image' => UploadedFile::fake()->image('replacement.png'),
        ])->assertSessionHasNoErrors();

        $newPath = data_get($post->fresh()->featured_image, 'path');
        $this->assertNotSame($oldPath, $newPath);
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($newPath);
    }

    public function test_external_images_still_render_and_are_never_deleted(): void
    {
        $external = 'https://images.example.com/story.jpg';
        $post = $this->postWithImage($external);

        $this->assertSame($external, $post->featured_image_url);
        $this->actingAs($this->admin(), 'admin')->get("/admin/blendnews/{$post->id}/edit")
            ->assertOk()
            ->assertSee($external, false);

        $this->actingAs($this->admin(), 'admin')->delete("/admin/blendnews/{$post->id}")
            ->assertRedirect('/admin/blendnews');
    }

    public function test_deleting_story_removes_managed_image_but_not_outside_prefix(): void
    {
        $managed = 'media/blend-news/delete-me.jpg';
        Storage::disk('public')->put($managed, 'managed');
        $post = $this->postWithImage($managed);

        $this->actingAs($this->admin(), 'admin')->delete("/admin/blendnews/{$post->id}");
        Storage::disk('public')->assertMissing($managed);

        $outside = 'media/keep-me.jpg';
        Storage::disk('public')->put($outside, 'outside');
        $post = $this->postWithImage($outside, ['slug' => 'outside-prefix-story']);
        $this->actingAs($this->admin(), 'admin')->delete("/admin/blendnews/{$post->id}");
        Storage::disk('public')->assertExists($outside);
    }

    public function test_failed_storage_does_not_create_story_or_expose_internal_error(): void
    {
        $service = $this->mock(BlendNewsImageService::class);
        $service->shouldReceive('store')->once()->andThrow(new \RuntimeException('private disk detail'));
        $service->shouldReceive('deleteManaged')->once()->with(null)->andReturn(false);

        $response = $this->actingAs($this->admin(), 'admin')->post('/admin/blendnews', [
            ...$this->validPayload(),
            'featured_image' => UploadedFile::fake()->image('story.jpg'),
        ]);

        $response->assertSessionHasErrors('featured_image')->assertSessionDoesntHaveErrors(['title']);
        $this->assertStringNotContainsString('private disk detail', session('errors')->first('featured_image'));
        $this->assertDatabaseCount('posts', 0);
    }

    public function test_database_failure_cleans_up_newly_stored_image(): void
    {
        $admin = $this->admin();
        $path = 'media/blend-news/orphan.jpg';
        $service = $this->mock(BlendNewsImageService::class);
        $service->shouldReceive('store')->once()->andReturnUsing(function () use ($path): string {
            Storage::disk('public')->put($path, 'new image');

            return $path;
        });
        $service->shouldReceive('deleteManaged')->once()->with($path)->andReturnUsing(function ($deletedPath): bool {
            return Storage::disk('public')->delete($deletedPath);
        });
        Post::creating(fn () => throw new \RuntimeException('simulated database failure'));

        $this->actingAs($admin, 'admin')->post('/admin/blendnews', [
            ...$this->validPayload(),
            'featured_image' => UploadedFile::fake()->image('story.jpg'),
        ])->assertSessionHasErrors('featured_image');

        $this->assertDatabaseCount('posts', 0);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_failed_replacement_preserves_existing_image_and_record(): void
    {
        $oldPath = 'media/blend-news/preserved.jpg';
        Storage::disk('public')->put($oldPath, 'old');
        $post = $this->postWithImage($oldPath);

        $service = $this->mock(BlendNewsImageService::class);
        $service->shouldReceive('store')->once()->andThrow(new \RuntimeException('storage unavailable'));
        $service->shouldReceive('deleteManaged')->once()->with(null)->andReturn(false);

        $this->actingAs($this->admin(), 'admin')->put("/admin/blendnews/{$post->id}", [
            ...$this->validPayload(['title' => 'Must not persist']),
            'featured_image' => UploadedFile::fake()->image('replacement.jpg'),
        ])->assertSessionHasErrors('featured_image');

        $post->refresh();
        $this->assertSame('Industry news story', $post->title);
        $this->assertSame($oldPath, data_get($post->featured_image, 'path'));
        Storage::disk('public')->assertExists($oldPath);
    }

    public function test_guests_cannot_access_image_management_routes(): void
    {
        $post = $this->postWithImage(null);

        $this->get('/admin/blendnews/create')->assertRedirect('/admin/login');
        $this->post('/admin/blendnews', $this->validPayload())->assertRedirect('/admin/login');
        $this->put("/admin/blendnews/{$post->id}", $this->validPayload())->assertRedirect('/admin/login');
        $this->delete("/admin/blendnews/{$post->id}")->assertRedirect('/admin/login');
    }

    private function admin(): Admin
    {
        return Admin::query()->create([
            'name' => 'News Admin',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'role' => 'super-admin',
            'is_active' => true,
        ]);
    }

    private function postWithImage(?string $path, array $overrides = []): Post
    {
        return Post::query()->create([
            ...$this->validPayload(),
            'content_type' => Post::TYPE_NEWS,
            'featured_image' => $path ? ['path' => $path, 'alt' => 'Story image'] : null,
            ...$overrides,
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function validPayload(array $overrides = []): array
    {
        return [
            'title' => 'Industry news story',
            'slug' => 'industry-news-story',
            'excerpt' => 'A concise summary.',
            'content' => '<p>Story content.</p>',
            'status' => Post::STATUS_DRAFT,
            'verification_status' => 'unverified',
            'importance_level' => 1,
            ...$overrides,
        ];
    }
}
