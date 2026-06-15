<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CommerceProductController extends Controller
{
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
        $product = Product::query()->create($this->validatedProductData($request));

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
        $product->update($this->validatedProductData($request, $product));

        return redirect()
            ->route('admin.products.edit', $product)
            ->with('status', 'Product updated.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $product->delete();

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
            'image_url' => ['nullable', 'string', 'max:2048'],
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
            'image_url' => $validated['image_url'] ?? null,
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
