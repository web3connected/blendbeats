@extends('admin.layouts.app', [
    'title' => 'Affiliate Campaigns',
    'heading' => 'Affiliate Campaigns',
    'subtitle' => 'Group referral codes and performance by promotion, season, influencer, or targeted affiliate program.',
])

@section('admin_content')
    @php
        $badge = fn (string $status): string => match ($status) {
            'active' => 'success',
            'draft' => 'secondary',
            'paused' => 'warning',
            'ended' => 'info',
            'archived' => 'dark',
            default => 'secondary',
        };
        $label = fn (?string $value): string => str((string) ($value ?: 'none'))->replace('_', ' ')->headline()->toString();
        $percent = fn (float|int $value): string => number_format((float) $value, 2).'%';
    @endphp

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="row">
        <div class="col-lg-3 col-md-6">
            <div class="small-box bg-primary">
                <div class="inner">
                    <h3>{{ $stats['total'] }}</h3>
                    <p>Total Campaigns</p>
                </div>
                <div class="icon"><i class="fas fa-bullhorn"></i></div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="small-box bg-success">
                <div class="inner">
                    <h3>{{ $stats['active'] }}</h3>
                    <p>Active Campaigns</p>
                </div>
                <div class="icon"><i class="fas fa-toggle-on"></i></div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="small-box bg-warning">
                <div class="inner">
                    <h3>{{ $stats['paused'] }}</h3>
                    <p>Paused Campaigns</p>
                </div>
                <div class="icon"><i class="fas fa-pause-circle"></i></div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="small-box bg-info">
                <div class="inner">
                    <h3>{{ $stats['codes'] }}</h3>
                    <p>Assigned Codes</p>
                </div>
                <div class="icon"><i class="fas fa-link"></i></div>
            </div>
        </div>
    </div>

    @can('affiliates.update')
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Create Campaign</h3>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.admincenter.affiliatecampaigns.store') }}">
                    @csrf
                    <div class="row">
                        <div class="form-group col-lg-3">
                            <label for="campaign_name">Name</label>
                            <input id="campaign_name" type="text" name="name" value="{{ old('name') }}" class="form-control" required>
                        </div>
                        <div class="form-group col-lg-3">
                            <label for="campaign_slug">Slug</label>
                            <input id="campaign_slug" type="text" name="slug" value="{{ old('slug') }}" class="form-control" placeholder="auto-generated">
                        </div>
                        <div class="form-group col-lg-2">
                            <label for="campaign_status">Status</label>
                            <select id="campaign_status" name="status" class="form-control">
                                @foreach ($statuses as $status)
                                    <option value="{{ $status }}" @selected(old('status', 'draft') === $status)>{{ $label($status) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-lg-2">
                            <label for="campaign_starts_at">Starts</label>
                            <input id="campaign_starts_at" type="datetime-local" name="starts_at" value="{{ old('starts_at') }}" class="form-control">
                        </div>
                        <div class="form-group col-lg-2">
                            <label for="campaign_ends_at">Ends</label>
                            <input id="campaign_ends_at" type="datetime-local" name="ends_at" value="{{ old('ends_at') }}" class="form-control">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="campaign_description">Description</label>
                        <textarea id="campaign_description" name="description" rows="2" class="form-control">{{ old('description') }}</textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-plus mr-1"></i> Create Campaign
                    </button>
                </form>
            </div>
        </div>
    @endcan

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Campaign Search</h3>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('admin.admincenter.affiliatecampaigns.index') }}">
                <div class="row">
                    <div class="form-group col-lg-6">
                        <label for="campaign_search">Search</label>
                        <input id="campaign_search" type="search" name="search" value="{{ $filters['search'] ?? '' }}" class="form-control" placeholder="Name, slug, or description">
                    </div>
                    <div class="form-group col-lg-3">
                        <label for="campaign_filter_status">Status</label>
                        <select id="campaign_filter_status" name="status" class="form-control">
                            <option value="">Any status</option>
                            @foreach ($statuses as $status)
                                <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $label($status) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group col-lg-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary mr-2">
                            <i class="fas fa-search mr-1"></i> Search
                        </button>
                        <a href="{{ route('admin.admincenter.affiliatecampaigns.index') }}" class="btn btn-outline-secondary">Reset</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Campaign Management</h3>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped mb-0">
                    <thead>
                        <tr>
                            <th>Campaign</th>
                            <th>Status</th>
                            <th>Schedule</th>
                            <th>Tracking</th>
                            <th class="text-right">Management</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($campaigns as $campaign)
                            <tr>
                                <td>
                                    <strong>{{ $campaign->name }}</strong>
                                    <div class="small text-muted">{{ $campaign->slug }}</div>
                                    @if ($campaign->description)
                                        <div class="small text-muted">{{ str($campaign->description)->limit(120) }}</div>
                                    @endif
                                </td>
                                <td><span class="badge badge-{{ $badge($campaign->status) }}">{{ $label($campaign->status) }}</span></td>
                                <td>
                                    <div>Starts {{ $campaign->starts_at?->format('M j, Y g:i A') ?? 'Anytime' }}</div>
                                    <div class="small text-muted">Ends {{ $campaign->ends_at?->format('M j, Y g:i A') ?? 'No end date' }}</div>
                                </td>
                                <td>
                                    {{ $campaign->referral_codes_count }} codes
                                    <div class="small text-muted">
                                        {{ $campaign->referral_visits_count }} visits, {{ $campaign->referrals_count }} signups, {{ $campaign->qualified_referrals_count }} qualified
                                    </div>
                                </td>
                                <td class="text-right" style="min-width: 520px;">
                                    @can('affiliates.update')
                                        <form method="POST" action="{{ route('admin.admincenter.affiliatecampaigns.update', $campaign) }}">
                                            @csrf
                                            @method('PATCH')
                                            <div class="form-row justify-content-end">
                                                <div class="col-auto">
                                                    <input type="text" name="name" value="{{ $campaign->name }}" class="form-control form-control-sm" placeholder="Name" required>
                                                </div>
                                                <div class="col-auto">
                                                    <input type="text" name="slug" value="{{ $campaign->slug }}" class="form-control form-control-sm" placeholder="Slug">
                                                </div>
                                                <div class="col-auto">
                                                    <select name="status" class="form-control form-control-sm">
                                                        @foreach ($statuses as $status)
                                                            <option value="{{ $status }}" @selected($campaign->status === $status)>{{ $label($status) }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="col-auto">
                                                    <input type="datetime-local" name="starts_at" value="{{ $campaign->starts_at?->format('Y-m-d\TH:i') }}" class="form-control form-control-sm">
                                                </div>
                                                <div class="col-auto">
                                                    <input type="datetime-local" name="ends_at" value="{{ $campaign->ends_at?->format('Y-m-d\TH:i') }}" class="form-control form-control-sm">
                                                </div>
                                                <div class="col-auto">
                                                    <input type="text" name="description" value="{{ $campaign->description }}" class="form-control form-control-sm" placeholder="Description">
                                                </div>
                                                <div class="col-auto">
                                                    <button type="submit" class="btn btn-primary btn-sm">Save</button>
                                                </div>
                                            </div>
                                        </form>
                                    @else
                                        <span class="text-muted">View only</span>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">No campaigns match the current filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($campaigns->hasPages())
            <div class="card-footer">
                {{ $campaigns->links() }}
            </div>
        @endif
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Referral Code Campaign Assignment</h3>
            <div class="card-tools text-muted">Showing up to 50 referral codes.</div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped mb-0">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Affiliate</th>
                            <th>Current Campaign</th>
                            <th class="text-right">Assignment</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($codes as $code)
                            <tr>
                                <td><span class="badge badge-light border">{{ $code->code }}</span></td>
                                <td>
                                    <strong>{{ $code->affiliateAccount?->display_name ?: $code->affiliateAccount?->user?->name }}</strong>
                                    <div class="small text-muted">{{ $code->affiliateAccount?->contact_email ?: $code->affiliateAccount?->user?->email }}</div>
                                </td>
                                <td>
                                    @if ($code->campaign)
                                        {{ $code->campaign->name }}
                                        <div class="small text-muted">{{ $label($code->campaign->status) }}</div>
                                    @else
                                        <span class="text-muted">No campaign</span>
                                    @endif
                                </td>
                                <td class="text-right">
                                    @can('affiliates.update')
                                        <form method="POST" action="{{ route('admin.admincenter.affiliatecodes.campaign.update', $code) }}" class="form-inline justify-content-end">
                                            @csrf
                                            @method('PATCH')
                                            <select name="affiliate_campaign_id" class="form-control form-control-sm mr-2">
                                                <option value="">No campaign</option>
                                                @foreach ($campaignOptions as $campaignOption)
                                                    <option value="{{ $campaignOption->id }}" @selected($code->affiliate_campaign_id === $campaignOption->id)>
                                                        {{ $campaignOption->name }} ({{ $label($campaignOption->status) }})
                                                    </option>
                                                @endforeach
                                            </select>
                                            <button type="submit" class="btn btn-primary btn-sm">Save</button>
                                        </form>
                                    @else
                                        <span class="text-muted">View only</span>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">No referral codes are available for campaign assignment.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Campaign Analytics</h3>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped mb-0">
                    <thead>
                        <tr>
                            <th>Campaign</th>
                            <th>Visits</th>
                            <th>Signups</th>
                            <th>Qualified</th>
                            <th>Membership Credits</th>
                            <th>Conversion</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($analytics as $campaign)
                            <tr>
                                <td>
                                    <strong>{{ $campaign['name'] }}</strong>
                                    <div class="small text-muted">{{ $campaign['slug'] }}</div>
                                </td>
                                <td>{{ $campaign['referral_visits'] }}</td>
                                <td>{{ $campaign['attributed_signups'] }}</td>
                                <td>{{ $campaign['qualified_referrals'] }}</td>
                                <td>
                                    {{ $campaign['membership_credits_issued'] }} issued
                                    <div class="small text-muted">
                                        {{ $campaign['membership_credits_redeemed'] }} redeemed, {{ $campaign['membership_credits_expired'] }} expired
                                    </div>
                                </td>
                                <td>
                                    {{ $percent($campaign['visit_to_signup_rate']) }} signup
                                    <div class="small text-muted">
                                        {{ $percent($campaign['signup_to_qualified_rate']) }} qualified, {{ $percent($campaign['visit_to_qualified_rate']) }} visit-qualified
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">No campaign analytics are available yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
