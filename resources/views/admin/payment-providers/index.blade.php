@extends('admin.layouts.app', [
    'title' => 'Payment Providers',
    'heading' => 'Payment Providers',
    'subtitle' => 'Manage PayPal and Stripe configuration for checkout, subscriptions, billing, and promotion payments.',
])

@section('admin_content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="row">
        <div class="col-md-4">
            <div class="small-box bg-primary">
                <div class="inner">
                    <h3>{{ $providers->count() }}</h3>
                    <p>Providers</p>
                </div>
                <div class="icon">
                    <i class="fas fa-credit-card"></i>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="small-box bg-success">
                <div class="inner">
                    <h3>{{ $activeCount }}</h3>
                    <p>Active Providers</p>
                </div>
                <div class="icon">
                    <i class="fas fa-toggle-on"></i>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="small-box bg-warning">
                <div class="inner">
                    <h3>{{ $primaryProvider?->display_name ?? 'None' }}</h3>
                    <p>Primary Provider</p>
                </div>
                <div class="icon">
                    <i class="fas fa-star"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="alert alert-info">
        PayPal is the active provider for the first payment flow. Stripe remains configured for future subscription and billing portal work.
        Secret fields are encrypted and never displayed after saving.
    </div>

    <div class="row">
        @foreach ($providers as $provider)
            <div class="col-xl-6">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex align-items-center justify-content-between">
                            <h3 class="card-title mb-0">
                                <i class="fas fa-{{ $provider->provider === 'paypal' ? 'wallet' : 'credit-card' }} mr-1"></i>
                                {{ $provider->display_name }}
                            </h3>
                            <div>
                                <span class="badge badge-{{ $provider->is_active ? 'success' : 'secondary' }}">
                                    {{ $provider->is_active ? 'Active' : 'Inactive' }}
                                </span>
                                @if ($provider->is_primary)
                                    <span class="badge badge-warning">Primary</span>
                                @endif
                            </div>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('admin.admincenter.paymentproviders.update', $provider) }}">
                        @csrf
                        @method('PUT')

                        <div class="card-body">
                            <div class="row">
                                <div class="form-group col-md-6">
                                    <label for="provider_{{ $provider->id }}_display_name">Display Name</label>
                                    <input
                                        id="provider_{{ $provider->id }}_display_name"
                                        type="text"
                                        name="display_name"
                                        value="{{ old('display_name', $provider->display_name) }}"
                                        class="form-control"
                                        required
                                    >
                                </div>
                                <div class="form-group col-md-6">
                                    <label for="provider_{{ $provider->id }}_mode">Mode</label>
                                    <select id="provider_{{ $provider->id }}_mode" name="mode" class="form-control" required>
                                        @foreach (['sandbox' => 'Sandbox', 'test' => 'Test', 'live' => 'Live', 'production' => 'Production'] as $mode => $label)
                                            <option value="{{ $mode }}" @selected(old('mode', $provider->mode) === $mode)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="row">
                                <div class="form-group col-md-6">
                                    <label for="provider_{{ $provider->id }}_client_id">
                                        {{ $provider->provider === 'paypal' ? 'PayPal Client ID' : 'Stripe Publishable Key' }}
                                    </label>
                                    <input
                                        id="provider_{{ $provider->id }}_client_id"
                                        type="text"
                                        name="client_id"
                                        value="{{ old('client_id', $provider->client_id) }}"
                                        class="form-control"
                                        autocomplete="off"
                                    >
                                </div>
                                <div class="form-group col-md-6">
                                    <label for="provider_{{ $provider->id }}_secret">
                                        {{ $provider->provider === 'paypal' ? 'PayPal Secret' : 'Stripe Secret Key' }}
                                    </label>
                                    <input
                                        id="provider_{{ $provider->id }}_secret"
                                        type="password"
                                        name="secret"
                                        class="form-control"
                                        placeholder="{{ $provider->hasSecret() ? 'Saved. Enter a new value to replace.' : 'Not saved yet.' }}"
                                        autocomplete="new-password"
                                    >
                                    <small class="text-muted">
                                        Status:
                                        <span class="badge badge-{{ $provider->hasSecret() ? 'success' : 'secondary' }}">
                                            {{ $provider->hasSecret() ? 'Saved' : 'Missing' }}
                                        </span>
                                    </small>
                                </div>
                            </div>

                            <div class="row">
                                <div class="form-group col-md-6">
                                    <label for="provider_{{ $provider->id }}_webhook_id">Webhook ID</label>
                                    <input
                                        id="provider_{{ $provider->id }}_webhook_id"
                                        type="text"
                                        name="webhook_id"
                                        value="{{ old('webhook_id', $provider->webhook_id) }}"
                                        class="form-control"
                                        autocomplete="off"
                                    >
                                </div>
                                <div class="form-group col-md-6">
                                    <label for="provider_{{ $provider->id }}_webhook_secret">Webhook Secret</label>
                                    <input
                                        id="provider_{{ $provider->id }}_webhook_secret"
                                        type="password"
                                        name="webhook_secret"
                                        class="form-control"
                                        placeholder="{{ $provider->hasWebhookSecret() ? 'Saved. Enter a new value to replace.' : 'Not saved yet.' }}"
                                        autocomplete="new-password"
                                    >
                                    <small class="text-muted">
                                        Status:
                                        <span class="badge badge-{{ $provider->hasWebhookSecret() ? 'success' : 'secondary' }}">
                                            {{ $provider->hasWebhookSecret() ? 'Saved' : 'Missing' }}
                                        </span>
                                    </small>
                                </div>
                            </div>

                            <div class="row">
                                <div class="form-group col-md-6">
                                    <label for="provider_{{ $provider->id }}_merchant_id">Merchant / Account ID</label>
                                    <input
                                        id="provider_{{ $provider->id }}_merchant_id"
                                        type="text"
                                        name="merchant_id"
                                        value="{{ old('merchant_id', $provider->merchant_id) }}"
                                        class="form-control"
                                        autocomplete="off"
                                    >
                                </div>
                                <div class="form-group col-md-6">
                                    <label for="provider_{{ $provider->id }}_dashboard_url">Dashboard URL</label>
                                    <input
                                        id="provider_{{ $provider->id }}_dashboard_url"
                                        type="url"
                                        name="dashboard_url"
                                        value="{{ old('dashboard_url', $provider->dashboard_url) }}"
                                        class="form-control"
                                    >
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="provider_{{ $provider->id }}_supported_features">Supported Features</label>
                                <input
                                    id="provider_{{ $provider->id }}_supported_features"
                                    type="text"
                                    name="supported_features"
                                    value="{{ old('supported_features', collect($provider->supported_features ?? [])->implode(', ')) }}"
                                    class="form-control"
                                    placeholder="checkout, subscriptions, webhooks"
                                >
                                <small class="text-muted">Comma-separated feature labels for internal admin reference.</small>
                            </div>

                            <div class="form-group">
                                <label for="provider_{{ $provider->id }}_notes">Admin Notes</label>
                                <textarea
                                    id="provider_{{ $provider->id }}_notes"
                                    name="notes"
                                    rows="3"
                                    class="form-control"
                                    placeholder="Internal setup notes, next steps, or integration reminders."
                                >{{ old('notes', $provider->notes) }}</textarea>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="custom-control custom-checkbox mb-2">
                                        <input id="provider_{{ $provider->id }}_is_active" type="checkbox" name="is_active" value="1" class="custom-control-input" @checked(old('is_active', $provider->is_active))>
                                        <label for="provider_{{ $provider->id }}_is_active" class="custom-control-label">Provider active</label>
                                    </div>
                                    <div class="custom-control custom-checkbox mb-2">
                                        <input id="provider_{{ $provider->id }}_is_primary" type="checkbox" name="is_primary" value="1" class="custom-control-input" @checked(old('is_primary', $provider->is_primary))>
                                        <label for="provider_{{ $provider->id }}_is_primary" class="custom-control-label">Primary checkout provider</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="custom-control custom-checkbox mb-2">
                                        <input id="provider_{{ $provider->id }}_clear_secret" type="checkbox" name="clear_secret" value="1" class="custom-control-input">
                                        <label for="provider_{{ $provider->id }}_clear_secret" class="custom-control-label">Clear saved secret</label>
                                    </div>
                                    <div class="custom-control custom-checkbox mb-2">
                                        <input id="provider_{{ $provider->id }}_clear_webhook_secret" type="checkbox" name="clear_webhook_secret" value="1" class="custom-control-input">
                                        <label for="provider_{{ $provider->id }}_clear_webhook_secret" class="custom-control-label">Clear webhook secret</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card-footer d-flex align-items-center justify-content-between">
                            @if ($provider->dashboard_url)
                                <a href="{{ $provider->dashboard_url }}" target="_blank" rel="noreferrer" class="btn btn-outline-secondary btn-sm">
                                    <i class="fas fa-external-link-alt mr-1"></i> Provider Dashboard
                                </a>
                            @else
                                <span class="text-muted small">No dashboard URL set.</span>
                            @endif

                            @can('paymentproviders.update')
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="fas fa-save mr-1"></i> Save Provider
                                </button>
                            @endcan
                        </div>
                    </form>
                </div>
            </div>
        @endforeach
    </div>
@endsection
