@csrf

@if ($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

<div class="row">
    <div class="col-md-8">
        <div class="form-group">
            <label for="title">Title</label>
            <input id="title" name="title" class="form-control" value="{{ old('title', $product->title) }}" required>
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group">
            <label for="slug">Slug</label>
            <input id="slug" name="slug" class="form-control" value="{{ old('slug', $product->slug) }}" placeholder="Auto generated if blank">
        </div>
    </div>
</div>

<div class="form-group">
    <label for="description">Description</label>
    <textarea id="description" name="description" class="form-control" rows="3">{{ old('description', $product->description) }}</textarea>
</div>

<div class="row">
    <div class="col-md-3">
        <div class="form-group">
            <label for="base_price">Base Price</label>
            <input id="base_price" name="base_price" type="number" min="0" step="0.01" class="form-control" value="{{ old('base_price', number_format(($product->base_price_cents ?? 0) / 100, 2, '.', '')) }}" required>
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group">
            <label for="sale_price">Sale Price</label>
            <input id="sale_price" name="sale_price" type="number" min="0" step="0.01" class="form-control" value="{{ old('sale_price', $product->sale_price_cents ? number_format($product->sale_price_cents / 100, 2, '.', '') : '') }}">
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group">
            <label for="category">Category</label>
            <input id="category" name="category" class="form-control" value="{{ old('category', $product->category) }}">
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group">
            <label for="status">Status</label>
            <select id="status" name="status" class="form-control">
                @foreach (['draft', 'active', 'paused', 'archived'] as $status)
                    <option value="{{ $status }}" @selected(old('status', $product->status) === $status)>{{ ucfirst($status) }}</option>
                @endforeach
            </select>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-4">
        <div class="form-group">
            <label for="source_type">Source Type</label>
            <select id="source_type" name="source_type" class="form-control">
                @foreach ($sourceTypes as $sourceType)
                    <option value="{{ $sourceType }}" @selected(old('source_type', $product->source_type) === $sourceType)>{{ str_replace('_', ' ', ucfirst($sourceType)) }}</option>
                @endforeach
            </select>
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group">
            <label for="fulfillment_type">Fulfillment Type</label>
            <select id="fulfillment_type" name="fulfillment_type" class="form-control">
                @foreach ($fulfillmentTypes as $fulfillmentType)
                    <option value="{{ $fulfillmentType }}" @selected(old('fulfillment_type', $product->fulfillment_type) === $fulfillmentType)>{{ str_replace('_', ' ', ucfirst($fulfillmentType)) }}</option>
                @endforeach
            </select>
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group">
            <label for="vendor_name">Vendor Name</label>
            <input id="vendor_name" name="vendor_name" class="form-control" value="{{ old('vendor_name', $product->vendor_name) }}">
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label for="external_product_url">External Product URL</label>
            <input id="external_product_url" name="external_product_url" class="form-control" value="{{ old('external_product_url', $product->external_product_url) }}">
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label for="affiliate_tracking_url">Affiliate Tracking URL</label>
            <input id="affiliate_tracking_url" name="affiliate_tracking_url" class="form-control" value="{{ old('affiliate_tracking_url', $product->affiliate_tracking_url) }}">
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="form-group">
            <label for="image_url">Image URL</label>
            <input id="image_url" name="image_url" class="form-control" value="{{ old('image_url', $product->image_url) }}" placeholder="/media/products/example.jpg">
            <small class="form-text text-muted">Leave blank to use the default product artwork on the frontend.</small>
        </div>
    </div>
    <div class="col-md-2">
        <div class="form-group">
            <label for="commission_rate">Commission %</label>
            <input id="commission_rate" name="commission_rate" type="number" min="0" max="100" step="0.01" class="form-control" value="{{ old('commission_rate', $product->commission_rate) }}">
        </div>
    </div>
    <div class="col-md-2">
        <div class="form-group pt-4">
            <div class="custom-control custom-switch mt-2">
                <input type="checkbox" class="custom-control-input" id="requires_customization" name="requires_customization" value="1" @checked(old('requires_customization', $product->requires_customization))>
                <label class="custom-control-label" for="requires_customization">Customizable</label>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label for="customization_schema">Customization Schema JSON</label>
            <textarea id="customization_schema" name="customization_schema" class="form-control" rows="6" placeholder='{"size":["S","M","L"],"color":["Black"]}'>{{ old('customization_schema', $product->customization_schema ? json_encode($product->customization_schema, JSON_PRETTY_PRINT) : '') }}</textarea>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label for="metadata">Metadata JSON</label>
            <textarea id="metadata" name="metadata" class="form-control" rows="6" placeholder='{"lane":"affiliate_redirect"}'>{{ old('metadata', $product->metadata ? json_encode($product->metadata, JSON_PRETTY_PRINT) : '') }}</textarea>
        </div>
    </div>
</div>

<div class="d-flex justify-content-between">
    <a href="{{ route('admin.products.index') }}" class="btn btn-secondary">Cancel</a>
    <button type="submit" class="btn btn-primary">
        <i class="fas fa-save mr-1"></i> Save Product
    </button>
</div>
