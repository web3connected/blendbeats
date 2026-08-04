<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\ProductImageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class CommerceProductController extends Controller
{
    public function __construct(private readonly ProductImageService $images) {}

    public function index(Request $request): View
    {
        $products = Product::query()
            ->when($request->query('source_type'), fn ($query, $sourceType) => $query->where('source_type', $sourceType))
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.products.index', [
            'products' => $products,
            'sourceTypes' => Product::SOURCE_TYPES,
            'fulfillmentTypes' => Product::FULFILLMENT_TYPES,
        ]);
    }

    public function create(): View
    {
        return view('admin.products.create', [
            'product' => new Product([
                'source_type' => 'internal',
                'fulfillment_type' => 'internal',
                'status' => 'draft',
            ]),
            'sourceTypes' => Product::SOURCE_TYPES,
            'fulfillmentTypes' => Product::FULFILLMENT_TYPES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedProductData($request);
        $storedPath = null;

        try {
            if ($request->hasFile('product_image')) {
                $storedPath = $this->images->store($request->file('product_image'));
                $data['image_url'] = $storedPath;
            }

            $product = DB::transaction(fn (): Product => Product::query()->create($data));
        } catch (Throwable $exception) {
            $this->images->deleteManaged($storedPath);
            Log::error('Admin product creation failed.', ['exception' => $exception::class]);

            return back()->withInput()->withErrors([
                'product_image' => 'The product could not be saved. Please verify the image and try again.',
            ]);
        }

        return redirect()
            ->route('admin.products.edit', $product)
            ->with('status', 'Product created.');
    }

    public function edit(Product $product): View
    {
        return view('admin.products.edit', [
            'product' => $product,
            'sourceTypes' => Product::SOURCE_TYPES,
            'fulfillmentTypes' => Product::FULFILLMENT_TYPES,
        ]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $data = $this->validatedProductData($request, $product);
        $oldPath = $product->getRawOriginal('image_url');
        $storedPath = null;

        try {
            if ($request->hasFile('product_image')) {
                $storedPath = $this->images->store($request->file('product_image'));
                $data['image_url'] = $storedPath;
            }

            DB::transaction(fn () => $product->update($data));
        } catch (Throwable $exception) {
            $this->images->deleteManaged($storedPath);
            Log::error('Admin product update failed.', [
                'product_id' => $product->id,
                'exception' => $exception::class,
            ]);

            return back()->withInput()->withErrors([
                'product_image' => 'The product could not be updated. The existing image was kept.',
            ]);
        }

        if ($storedPath !== null) {
            $this->images->deleteManaged($oldPath);
        }

        return redirect()
            ->route('admin.products.edit', $product)
            ->with('status', 'Product updated.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $path = $product->getRawOriginal('image_url');
        $product->delete();
        $this->images->deleteManaged($path);

        return redirect()
            ->route('admin.products.index')
            ->with('status', 'Product deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedProductData(Request $request, ?Product $product = null): array
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('products', 'slug')->ignore($product?->id)],
            'description' => ['nullable', 'string'],
            'base_price' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'sale_price' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'vendor_name' => ['nullable', 'string', 'max:255'],
            'source_type' => ['required', Rule::in(Product::SOURCE_TYPES)],
            'external_product_url' => ['nullable', 'string', 'max:2048'],
            'affiliate_tracking_url' => ['nullable', 'string', 'max:2048'],
            'product_image' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.(int) config('commerce.images.max_kilobytes', 5120)],
            'category' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['draft', 'active', 'paused', 'archived'])],
            'requires_customization' => ['nullable', 'boolean'],
            'fulfillment_type' => ['required', Rule::in(Product::FULFILLMENT_TYPES)],
            'commission_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'customization_schema' => ['nullable', 'json'],
            'metadata' => ['nullable', 'json'],
        ]);

        $basePrice = (float) $validated['base_price'];
        $salePrice = $validated['sale_price'] === null || $validated['sale_price'] === ''
            ? null
            : (float) $validated['sale_price'];

        return [
            'title' => $validated['title'],
            'slug' => $validated['slug'] ?: Str::slug($validated['title']),
            'description' => $validated['description'] ?? null,
            'base_price_cents' => (int) round($basePrice * 100),
            'sale_price_cents' => $salePrice === null ? null : (int) round($salePrice * 100),
            'vendor_name' => $validated['vendor_name'] ?? null,
            'source_type' => $validated['source_type'],
            'external_product_url' => $validated['external_product_url'] ?? null,
            'affiliate_tracking_url' => $validated['affiliate_tracking_url'] ?? null,
            'category' => $validated['category'] ?? null,
            'status' => $validated['status'],
            'requires_customization' => $request->boolean('requires_customization'),
            'fulfillment_type' => $validated['fulfillment_type'],
            'commission_rate' => $validated['commission_rate'] ?? null,
            'customization_schema' => $this->decodeJson($validated['customization_schema'] ?? null),
            'metadata' => $this->decodeJson($validated['metadata'] ?? null),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeJson(?string $value): ?array
    {
        if (! $value) {
            return null;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : null;
    }
}
