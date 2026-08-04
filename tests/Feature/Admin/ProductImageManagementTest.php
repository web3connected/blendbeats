<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Product;
use App\Services\ProductImageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductImageManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('commerce.images.disk', 'public');
        Storage::fake('public');
    }

    public function test_admin_can_create_product_with_managed_image(): void
    {
        $response = $this->actingAs($this->admin(), 'admin')->post('/admin/products', [
            ...$this->payload(),
            'product_image' => UploadedFile::fake()->image('shirt.jpg', 1200, 1200),
        ]);

        $product = Product::query()->where('slug', 'test-shirt')->sole();
        $path = $product->getRawOriginal('image_url');

        $response->assertRedirect(route('admin.products.edit', $product));
        $this->assertMatchesRegularExpression('#^media/products/[0-9a-f-]+\.jpg$#', $path);
        Storage::disk('public')->assertExists($path);
        $this->assertSame(Storage::disk('public')->url($path), $product->image_url);
    }

    public function test_supported_formats_are_accepted_and_invalid_or_oversized_files_are_rejected(): void
    {
        $admin = $this->admin();

        foreach (['jpg', 'png'] as $extension) {
            $this->actingAs($admin, 'admin')->post('/admin/products', [
                ...$this->payload(['title' => "Product {$extension}", 'slug' => "product-{$extension}"]),
                'product_image' => UploadedFile::fake()->image("product.{$extension}"),
            ])->assertSessionHasNoErrors();
        }

        $webp = UploadedFile::fake()->createWithContent(
            'product.webp',
            base64_decode('UklGRiIAAABXRUJQVlA4IBYAAAAwAQCdASoBAAEADsD+JaQAA3AAAAAA'),
        );
        $this->actingAs($admin, 'admin')->post('/admin/products', [
            ...$this->payload(['title' => 'Product WebP', 'slug' => 'product-webp']),
            'product_image' => $webp,
        ])->assertSessionHasNoErrors();

        $this->actingAs($admin, 'admin')->post('/admin/products', [
            ...$this->payload(['slug' => 'invalid-file']),
            'product_image' => UploadedFile::fake()->create('product.pdf', 100, 'application/pdf'),
        ])->assertSessionHasErrors('product_image');

        $this->actingAs($admin, 'admin')->post('/admin/products', [
            ...$this->payload(['slug' => 'oversized-file']),
            'product_image' => UploadedFile::fake()->image('large.jpg')->size(5121),
        ])->assertSessionHasErrors('product_image');
    }

    public function test_edit_without_upload_preserves_image_and_replacement_removes_old_managed_file(): void
    {
        $admin = $this->admin();
        $oldPath = 'media/products/11111111-1111-4111-8111-111111111111.jpg';
        Storage::disk('public')->put($oldPath, 'old');
        $product = $this->product($oldPath);

        $this->actingAs($admin, 'admin')->put("/admin/products/{$product->id}", [
            ...$this->payload(['title' => 'Updated Shirt']),
        ])->assertSessionHasNoErrors();
        $this->assertSame($oldPath, $product->fresh()->getRawOriginal('image_url'));
        Storage::disk('public')->assertExists($oldPath);

        $this->actingAs($admin, 'admin')->put("/admin/products/{$product->id}", [
            ...$this->payload(['title' => 'Replacement Shirt']),
            'product_image' => UploadedFile::fake()->image('replacement.png'),
        ])->assertSessionHasNoErrors();

        $newPath = $product->fresh()->getRawOriginal('image_url');
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($newPath);
    }

    public function test_external_and_legacy_urls_remain_compatible_and_are_not_deleted(): void
    {
        $external = $this->product('https://images.example.com/product.jpg');
        $this->assertSame('https://images.example.com/product.jpg', $external->image_url);

        $this->actingAs($this->admin(), 'admin')->delete("/admin/products/{$external->id}")
            ->assertRedirect('/admin/products');

        $legacyPath = '/media/products/legacy.jpg';
        $legacy = $this->product($legacyPath, ['slug' => 'legacy-product']);
        $this->assertSame($legacyPath, $legacy->image_url);
    }

    public function test_deleting_product_removes_only_managed_image(): void
    {
        $path = 'media/products/22222222-2222-4222-8222-222222222222.jpg';
        Storage::disk('public')->put($path, 'image');
        $product = $this->product($path);

        $this->actingAs($this->admin(), 'admin')->delete("/admin/products/{$product->id}");
        Storage::disk('public')->assertMissing($path);

        $outside = 'media/keep.jpg';
        Storage::disk('public')->put($outside, 'keep');
        $product = $this->product($outside, ['slug' => 'outside-image']);
        $this->actingAs($this->admin(), 'admin')->delete("/admin/products/{$product->id}");
        Storage::disk('public')->assertExists($outside);
    }

    public function test_database_failure_cleans_up_new_upload(): void
    {
        $path = 'media/products/33333333-3333-4333-8333-333333333333.jpg';
        $service = $this->mock(ProductImageService::class);
        $service->shouldReceive('store')->once()->andReturnUsing(function () use ($path): string {
            Storage::disk('public')->put($path, 'new');
            return $path;
        });
        $service->shouldReceive('deleteManaged')->once()->with($path)->andReturnUsing(
            fn ($deletedPath) => Storage::disk('public')->delete($deletedPath),
        );
        Product::creating(fn () => throw new \RuntimeException('database failure'));

        $this->actingAs($this->admin(), 'admin')->post('/admin/products', [
            ...$this->payload(),
            'product_image' => UploadedFile::fake()->image('product.jpg'),
        ])->assertSessionHasErrors('product_image');

        Storage::disk('public')->assertMissing($path);
    }

    public function test_guests_cannot_manage_product_images(): void
    {
        $product = $this->product(null);
        $this->get('/admin/products/create')->assertRedirect('/admin/login');
        $this->post('/admin/products', $this->payload())->assertRedirect('/admin/login');
        $this->put("/admin/products/{$product->id}", $this->payload())->assertRedirect('/admin/login');
        $this->delete("/admin/products/{$product->id}")->assertRedirect('/admin/login');
    }

    private function admin(): Admin
    {
        return Admin::query()->create([
            'name' => 'Commerce Admin',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'role' => 'super-admin',
            'is_active' => true,
        ]);
    }

    private function product(?string $image, array $overrides = []): Product
    {
        return Product::query()->create([
            'title' => 'Test Shirt',
            'slug' => 'test-shirt',
            'base_price_cents' => 2999,
            'source_type' => 'internal',
            'fulfillment_type' => 'internal',
            'status' => 'active',
            'image_url' => $image,
            ...$overrides,
        ]);
    }

    private function payload(array $overrides = []): array
    {
        return [
            'title' => 'Test Shirt',
            'slug' => 'test-shirt',
            'description' => 'Product description',
            'base_price' => '29.99',
            'sale_price' => null,
            'source_type' => 'internal',
            'fulfillment_type' => 'internal',
            'status' => 'active',
            ...$overrides,
        ];
    }
}
