@extends('admin.layouts.app', [
    'title' => 'Commerce Products',
    'heading' => 'Commerce Products',
    'subtitle' => 'Manage internal, affiliate, vendor, marketplace, and print-on-demand products.',
])

@section('admin_content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Products</h3>
            <div class="card-tools">
                <a href="{{ route('admin.products.create') }}" class="btn btn-primary btn-sm">
                    <i class="fas fa-plus mr-1"></i> Create Product
                </a>
            </div>
        </div>
        <div class="card-body border-bottom">
            <form class="form-inline" method="GET" action="{{ route('admin.products.index') }}">
                <select name="source_type" class="form-control form-control-sm mr-2">
                    <option value="">All source types</option>
                    @foreach ($sourceTypes as $sourceType)
                        <option value="{{ $sourceType }}" @selected(request('source_type') === $sourceType)>{{ str_replace('_', ' ', ucfirst($sourceType)) }}</option>
                    @endforeach
                </select>
                <select name="status" class="form-control form-control-sm mr-2">
                    <option value="">All statuses</option>
                    @foreach (['draft', 'active', 'paused', 'archived'] as $status)
                        <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
                <button class="btn btn-secondary btn-sm" type="submit">Filter</button>
            </form>
        </div>
        <div class="card-body p-0">
            <table class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Source</th>
                        <th>Fulfillment</th>
                        <th>Vendor</th>
                        <th>Price</th>
                        <th>Status</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($products as $product)
                        <tr>
                            <td>
                                <strong>{{ $product->title }}</strong>
                                <div class="text-muted small">{{ $product->slug }}</div>
                                @if ($product->requires_customization)
                                    <span class="badge badge-warning">Customization required</span>
                                @endif
                            </td>
                            <td><span class="badge badge-dark">{{ str_replace('_', ' ', $product->source_type) }}</span></td>
                            <td>{{ str_replace('_', ' ', $product->fulfillment_type) }}</td>
                            <td>{{ $product->vendor_name ?: 'BlendBeats' }}</td>
                            <td>
                                ${{ number_format($product->currentPriceCents() / 100, 2) }}
                                @if ($product->sale_price_cents)
                                    <div class="text-muted small">Base ${{ number_format($product->base_price_cents / 100, 2) }}</div>
                                @endif
                            </td>
                            <td><span class="badge badge-{{ $product->status === 'active' ? 'success' : 'secondary' }}">{{ ucfirst($product->status) }}</span></td>
                            <td class="text-right">
                                <a href="{{ route('admin.products.edit', $product) }}" class="btn btn-warning btn-sm">
                                    <i class="fas fa-edit mr-1"></i> Edit
                                </a>
                                <form method="POST" action="{{ route('admin.products.destroy', $product) }}" class="d-inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Delete this product?')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">No commerce products found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($products->hasPages())
            <div class="card-footer">{{ $products->links() }}</div>
        @endif
    </div>
@endsection
