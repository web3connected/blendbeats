@extends('admin.layouts.app', [
    'title' => 'Payment Providers',
    'heading' => 'Payment Providers',
    'subtitle' => 'Enable PayPal and Stripe, then manage each active payment service below.',
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
                <div class="icon"><i class="fas fa-credit-card"></i></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="small-box bg-success">
                <div class="inner">
                    <h3>{{ $activeCount }}</h3>
                    <p>Active Services</p>
                </div>
                <div class="icon"><i class="fas fa-toggle-on"></i></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="small-box bg-warning">
                <div class="inner">
                    <h3>{{ $primaryProvider?->display_name ?? 'None' }}</h3>
                    <p>Primary Checkout</p>
                </div>
                <div class="icon"><i class="fas fa-star"></i></div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Service Switches</h3>
            <div class="card-tools text-muted">Enable a provider here to create its active service tab below.</div>
        </div>
        <form method="POST" action="{{ route('admin.admincenter.paymentproviders.status.update') }}">
            @csrf
            @method('PUT')
            <div class="card-body">
                <div class="alert alert-info">
                    PayPal is the first payment provider for checkout. Stripe can stay off until we are ready to use it.
                    Secrets stay encrypted and are configured inside each active service tab.
                </div>

                <div class="row">
                    @foreach ($providers as $provider)
                        <div class="col-lg-6">
                            <div class="border rounded p-3 mb-3">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div>
                                        <h4 class="mb-1">
                                            <i class="fas fa-{{ $provider->provider === 'paypal' ? 'wallet' : 'credit-card' }} mr-1"></i>
                                            {{ $provider->display_name }}
                                        </h4>
                                        <p class="text-muted mb-0">
                                            {{ $provider->provider === 'paypal' ? 'PayPal checkout, subscriptions, and promotion payments.' : 'Stripe subscriptions, checkout, billing portal, and future payment flows.' }}
                                        </p>
                                    </div>
                                    <div class="custom-control custom-switch">
                                        <input
                                            id="provider_switch_{{ $provider->id }}"
                                            type="checkbox"
                                            name="active_providers[]"
                                            value="{{ $provider->id }}"
                                            class="custom-control-input"
                                            @checked($provider->is_active)
                                        >
                                        <label for="provider_switch_{{ $provider->id }}" class="custom-control-label">
                                            {{ $provider->is_active ? 'On' : 'Off' }}
                                        </label>
                                    </div>
                                </div>

                                <div class="mt-3 pt-3 border-top">
                                    <div class="custom-control custom-radio">
                                        <input
                                            id="provider_primary_{{ $provider->id }}"
                                            type="radio"
                                            name="primary_provider"
                                            value="{{ $provider->id }}"
                                            class="custom-control-input"
                                            @checked($provider->is_primary)
                                        >
                                        <label for="provider_primary_{{ $provider->id }}" class="custom-control-label">
                                            Use {{ $provider->display_name }} as primary checkout provider
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="card-footer text-right">
                @can('paymentproviders.update')
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fas fa-save mr-1"></i> Save Service Switches
                    </button>
                @endcan
            </div>
        </form>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Active Service Configuration</h3>
            <div class="card-tools text-muted">Only enabled providers show here.</div>
        </div>
        <div class="card-body">
            @if ($activeProviders->isEmpty())
                <div class="alert alert-warning mb-0">
                    No payment services are active. Turn on PayPal or Stripe above to create its configuration tab.
                </div>
            @else
                <ul class="nav nav-tabs" id="payment-provider-tabs" role="tablist">
                    @foreach ($activeProviders as $provider)
                        <li class="nav-item">
                            <a
                                class="nav-link @if ($loop->first) active @endif"
                                id="provider-tab-{{ $provider->provider }}"
                                data-toggle="pill"
                                href="#provider-panel-{{ $provider->provider }}"
                                role="tab"
                                aria-controls="provider-panel-{{ $provider->provider }}"
                                aria-selected="{{ $loop->first ? 'true' : 'false' }}"
                            >
                                {{ $provider->display_name }}
                                @if ($provider->is_primary)
                                    <span class="badge badge-warning ml-1">Primary</span>
                                @endif
                            </a>
                        </li>
                    @endforeach
                </ul>

                <div class="tab-content border border-top-0 p-3" id="payment-provider-tab-content">
                    @foreach ($activeProviders as $provider)
                        <div
                            class="tab-pane fade @if ($loop->first) show active @endif"
                            id="provider-panel-{{ $provider->provider }}"
                            role="tabpanel"
                            aria-labelledby="provider-tab-{{ $provider->provider }}"
                        >
                            <form method="POST" action="{{ route('admin.admincenter.paymentproviders.update', $provider) }}">
                                @csrf
                                @method('PUT')

                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <div>
                                        <h4 class="mb-1">{{ $provider->display_name }} Settings</h4>
                                        <p class="text-muted mb-0">
                                            Configure credentials and webhook details for this active service.
                                        </p>
                                    </div>
                                    <div>
                                        <span class="badge badge-success">Active</span>
                                        @if ($provider->is_primary)
                                            <span class="badge badge-warning">Primary</span>
                                        @endif
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="form-group col-md-6">
                                        <label for="provider_{{ $provider->id }}_display_name">Display Name</label>
                                        <input id="provider_{{ $provider->id }}_display_name" type="text" name="display_name" value="{{ old('display_name', $provider->display_name) }}" class="form-control" required>
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
                                        <input id="provider_{{ $provider->id }}_client_id" type="text" name="client_id" value="{{ old('client_id', $provider->client_id) }}" class="form-control" autocomplete="off">
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label for="provider_{{ $provider->id }}_secret">
                                            {{ $provider->provider === 'paypal' ? 'PayPal Secret' : 'Stripe Secret Key' }}
                                        </label>
                                        <input id="provider_{{ $provider->id }}_secret" type="password" name="secret" class="form-control" placeholder="{{ $provider->hasSecret() ? 'Saved. Enter a new value to replace.' : 'Not saved yet.' }}" autocomplete="new-password">
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
                                        <input id="provider_{{ $provider->id }}_webhook_id" type="text" name="webhook_id" value="{{ old('webhook_id', $provider->webhook_id) }}" class="form-control" autocomplete="off">
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label for="provider_{{ $provider->id }}_webhook_secret">Webhook Secret</label>
                                        <input id="provider_{{ $provider->id }}_webhook_secret" type="password" name="webhook_secret" class="form-control" placeholder="{{ $provider->hasWebhookSecret() ? 'Saved. Enter a new value to replace.' : 'Not saved yet.' }}" autocomplete="new-password">
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
                                        <input id="provider_{{ $provider->id }}_merchant_id" type="text" name="merchant_id" value="{{ old('merchant_id', $provider->merchant_id) }}" class="form-control" autocomplete="off">
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label for="provider_{{ $provider->id }}_dashboard_url">Dashboard URL</label>
                                        <input id="provider_{{ $provider->id }}_dashboard_url" type="url" name="dashboard_url" value="{{ old('dashboard_url', $provider->dashboard_url) }}" class="form-control">
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="provider_{{ $provider->id }}_supported_features">Supported Features</label>
                                    <input id="provider_{{ $provider->id }}_supported_features" type="text" name="supported_features" value="{{ old('supported_features', collect($provider->supported_features ?? [])->implode(', ')) }}" class="form-control" placeholder="checkout, subscriptions, webhooks">
                                    <small class="text-muted">Comma-separated feature labels for internal admin reference.</small>
                                </div>

                                <div class="form-group">
                                    <label for="provider_{{ $provider->id }}_notes">Admin Notes</label>
                                    <textarea id="provider_{{ $provider->id }}_notes" name="notes" rows="3" class="form-control" placeholder="Internal setup notes, next steps, or integration reminders.">{{ old('notes', $provider->notes) }}</textarea>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="custom-control custom-checkbox mb-2">
                                            <input id="provider_{{ $provider->id }}_clear_secret" type="checkbox" name="clear_secret" value="1" class="custom-control-input">
                                            <label for="provider_{{ $provider->id }}_clear_secret" class="custom-control-label">Clear saved secret</label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="custom-control custom-checkbox mb-2">
                                            <input id="provider_{{ $provider->id }}_clear_webhook_secret" type="checkbox" name="clear_webhook_secret" value="1" class="custom-control-input">
                                            <label for="provider_{{ $provider->id }}_clear_webhook_secret" class="custom-control-label">Clear webhook secret</label>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex align-items-center justify-content-between border-top pt-3 mt-3">
                                    @if ($provider->dashboard_url)
                                        <a href="{{ $provider->dashboard_url }}" target="_blank" rel="noreferrer" class="btn btn-outline-secondary btn-sm">
                                            <i class="fas fa-external-link-alt mr-1"></i> Provider Dashboard
                                        </a>
                                    @else
                                        <span class="text-muted small">No dashboard URL set.</span>
                                    @endif

                                    @can('paymentproviders.update')
                                        <button type="submit" class="btn btn-primary btn-sm">
                                            <i class="fas fa-save mr-1"></i> Save {{ $provider->display_name }}
                                        </button>
                                    @endcan
                                </div>
                            </form>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
@endsection
