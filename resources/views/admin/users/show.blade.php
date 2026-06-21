@extends('admin.layouts.app', [
    'title' => 'User',
    'heading' => $user->name,
    'subtitle' => $user->email,
])

@section('admin_content')
    @php
        $membershipTier = config("media_storage.tier_aliases.{$user->media_storage_tier}", $user->media_storage_tier ?? 'free');
        $membershipLabel = config("billing.subscription.tiers.{$membershipTier}.name", $membershipTier);
        $subscriptionTierLabels = collect(config('billing.subscription.tiers'))
            ->mapWithKeys(fn ($tier, $key) => [$key => $tier['name'] ?? $key]);
        $billingProviderLabel = match ($user->billing_provider) {
            'internal' => 'Complimentary',
            'paypal' => 'PayPal',
            default => $user->billing_provider ? str($user->billing_provider)->replace('_', ' ')->title() : 'Not Set',
        };
        $subscriptionIdLabel = $user->billing_provider === 'internal'
            ? 'Not required'
            : ($user->paypal_subscription_id ?: 'Not Set');
    @endphp

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">User Details</h3>
            <div class="card-tools">
                <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-primary btn-sm">
                    <i class="fas fa-edit mr-1"></i> Edit
                </a>
                <a href="{{ route('admin.users.index') }}" class="btn btn-secondary btn-sm">
                    <i class="fas fa-arrow-left mr-1"></i> Back
                </a>
            </div>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-lg-4 text-center mb-4 mb-lg-0">
                    <img src="{{ $user->avatar_url }}" alt="{{ $user->name }}" class="img-circle elevation-2 mb-3" style="height: 160px; object-fit: cover; width: 160px;">
                    <h4 class="mb-1">{{ $user->name }}</h4>
                    <p class="text-muted mb-0">{{ ucfirst($user->avatar_source) }} Avatar</p>
                </div>
                <div class="col-lg-8">
                    <table class="table table-striped mb-0">
                        <tbody>
                            <tr><th>ID</th><td>{{ $user->id }}</td></tr>
                            <tr><th>Name</th><td>{{ $user->name }}</td></tr>
                            <tr><th>First Name</th><td>{{ $user->first_name ?: 'Empty' }}</td></tr>
                            <tr><th>Last Name</th><td>{{ $user->last_name ?: 'Empty' }}</td></tr>
                            <tr><th>Email</th><td>{{ $user->email }}</td></tr>
                            <tr><th>Email Verified At</th><td>{{ optional($user->email_verified_at)->format('Y-m-d H:i:s') ?? 'Not verified' }}</td></tr>
                            <tr><th>Avatar</th><td>{{ $user->avatar ?: 'Empty' }}</td></tr>
                            <tr><th>Use Gravatar</th><td>{{ $user->use_gravatar ? 'Yes' : 'No' }}</td></tr>
                            <tr><th>Is Gravatar</th><td>{{ $user->is_gravatar ? 'Yes' : 'No' }}</td></tr>
                            <tr><th>Membership Tier</th><td id="user-membership-tier">{{ $membershipLabel }}</td></tr>
                            <tr><th>Password</th><td><span class="text-muted">Stored hash hidden</span></td></tr>
                            <tr><th>Remember Token</th><td>{{ $user->remember_token ? 'Set' : 'Not set' }}</td></tr>
                            <tr><th>Created At</th><td>{{ optional($user->created_at)->format('Y-m-d H:i:s') }}</td></tr>
                            <tr><th>Updated At</th><td>{{ optional($user->updated_at)->format('Y-m-d H:i:s') }}</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="card" id="subscription-management">
        <div class="card-header">
            <h3 class="card-title">Subscription Management</h3>
        </div>
        <div class="card-body">
            <div id="subscription-management-alert" class="alert d-none" role="alert"></div>

            <div class="row">
                <div class="col-lg-6 mb-4 mb-lg-0">
                    <table class="table table-striped mb-0">
                        <tbody>
                            <tr><th>Current Plan</th><td id="subscription-current-plan">{{ $membershipLabel }}</td></tr>
                            <tr><th>Status</th><td id="subscription-status">{{ $user->paypal_subscription_status ? str($user->paypal_subscription_status)->replace('_', ' ')->title() : 'Not Set' }}</td></tr>
                            <tr><th>Billing Provider</th><td id="subscription-billing-provider">{{ $billingProviderLabel }}</td></tr>
                            <tr><th>Subscription ID</th><td id="subscription-id">{{ $subscriptionIdLabel }}</td></tr>
                            <tr><th>Expires At</th><td id="subscription-expires-at">{{ optional($user->comped_subscription_expires_at)->format('Y-m-d H:i') ?? 'Not Set' }}</td></tr>
                            <tr><th>Reason</th><td id="subscription-reason">{{ $user->comped_subscription_reason ?: 'Not Set' }}</td></tr>
                        </tbody>
                    </table>
                </div>

                <div class="col-lg-6">
                    <form id="grant-free-subscription-form" class="border rounded p-3 mb-3">
                        <h5 class="mb-3">Grant Free DJ Plus</h5>
                        <div class="form-group">
                            <label for="subscription-expires-input">Expiration Date <span class="text-muted">(optional)</span></label>
                            <input
                                type="date"
                                id="subscription-expires-input"
                                name="expires_at"
                                class="form-control"
                            >
                        </div>
                        <div class="form-group">
                            <label for="subscription-reason-input">Reason <span class="text-muted">(optional)</span></label>
                            <input
                                type="text"
                                id="subscription-reason-input"
                                name="reason"
                                class="form-control"
                                maxlength="255"
                                placeholder="Manual free DJ Plus"
                            >
                        </div>
                        <button type="submit" id="grant-free-subscription-button" class="btn btn-success">
                            <i class="fas fa-gift mr-1"></i> Grant Free DJ Plus
                        </button>
                    </form>

                    <button
                        type="button"
                        id="revoke-free-subscription-button"
                        class="btn btn-outline-danger {{ $user->billing_provider === 'internal' ? '' : 'd-none' }}"
                    >
                        <i class="fas fa-ban mr-1"></i> Revoke Free Subscription
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('js')
    <script>
        (() => {
            const tierLabels = @json($subscriptionTierLabels);
            const tierAliases = @json(config('media_storage.tier_aliases', []));
            const grantUrl = @json(url("/api/admin/users/{$user->id}/grant-free-subscription"));
            const revokeUrl = @json(url("/api/admin/users/{$user->id}/revoke-free-subscription"));
            const grantForm = document.getElementById('grant-free-subscription-form');
            const grantButton = document.getElementById('grant-free-subscription-button');
            const revokeButton = document.getElementById('revoke-free-subscription-button');
            const alertBox = document.getElementById('subscription-management-alert');

            const fields = {
                membershipTier: document.getElementById('user-membership-tier'),
                currentPlan: document.getElementById('subscription-current-plan'),
                status: document.getElementById('subscription-status'),
                provider: document.getElementById('subscription-billing-provider'),
                subscriptionId: document.getElementById('subscription-id'),
                expiresAt: document.getElementById('subscription-expires-at'),
                reason: document.getElementById('subscription-reason'),
            };

            const formatLabel = (value) => {
                if (!value) return 'Not Set';

                return String(value)
                    .replace(/_/g, ' ')
                    .replace(/\b\w/g, (letter) => letter.toUpperCase());
            };

            const formatDate = (value) => {
                if (!value) return 'Not Set';

                const date = new Date(value);

                if (Number.isNaN(date.getTime())) return 'Not Set';

                return new Intl.DateTimeFormat(undefined, {
                    month: 'short',
                    day: 'numeric',
                    year: 'numeric',
                }).format(date);
            };

            const planLabel = (plan) => {
                const normalizedPlan = tierAliases[plan] || plan || 'free';

                return tierLabels[normalizedPlan] || formatLabel(normalizedPlan);
            };

            const providerLabel = (provider) => {
                if (provider === 'internal') return 'Complimentary';
                if (provider === 'paypal') return 'PayPal';

                return formatLabel(provider);
            };

            const showAlert = (type, message) => {
                alertBox.className = `alert alert-${type}`;
                alertBox.textContent = message;
            };

            const setBusy = (busy) => {
                grantButton.disabled = busy;
                revokeButton.disabled = busy;
            };

            const updateSubscriptionUi = (user) => {
                const plan = planLabel(user.media_storage_tier);
                const isInternal = user.billing_provider === 'internal';

                fields.membershipTier.textContent = plan;
                fields.currentPlan.textContent = plan;
                fields.status.textContent = formatLabel(user.paypal_subscription_status);
                fields.provider.textContent = providerLabel(user.billing_provider);
                fields.subscriptionId.textContent = isInternal ? 'Not required' : (user.paypal_subscription_id || 'Not Set');
                fields.expiresAt.textContent = formatDate(user.comped_subscription_expires_at);
                fields.reason.textContent = user.comped_subscription_reason || 'Not Set';
                revokeButton.classList.toggle('d-none', !isInternal);
            };

            const postSubscriptionAction = async (url, body = {}) => {
                const response = await fetch(url, {
                    method: 'POST',
                    credentials: 'include',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(body),
                });
                const text = await response.text();
                const data = text ? JSON.parse(text) : {};

                if (!response.ok) {
                    throw new Error(data.message || 'Subscription action failed.');
                }

                return data;
            };

            grantForm.addEventListener('submit', async (event) => {
                event.preventDefault();
                setBusy(true);

                const formData = new FormData(grantForm);

                try {
                    const data = await postSubscriptionAction(grantUrl, {
                        expires_at: formData.get('expires_at') || null,
                        reason: formData.get('reason') || null,
                    });

                    updateSubscriptionUi(data.user);
                    grantForm.reset();
                    showAlert('success', data.message || 'Free DJ Plus subscription granted.');
                } catch (error) {
                    showAlert('danger', error instanceof Error ? error.message : 'Subscription action failed.');
                } finally {
                    setBusy(false);
                }
            });

            revokeButton.addEventListener('click', async () => {
                if (!window.confirm('Revoke this free DJ Plus subscription?')) return;

                setBusy(true);

                try {
                    const data = await postSubscriptionAction(revokeUrl);

                    updateSubscriptionUi(data.user);
                    showAlert('success', data.message || 'Free DJ Plus subscription revoked.');
                } catch (error) {
                    showAlert('danger', error instanceof Error ? error.message : 'Subscription action failed.');
                } finally {
                    setBusy(false);
                }
            });
        })();
    </script>
@endsection
