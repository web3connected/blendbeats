@extends('admin.layouts.app', [
    'title' => 'Affiliate Program Settings',
    'heading' => 'Affiliate Program Settings',
    'subtitle' => 'Current affiliate reward rules loaded from configuration.',
])

@section('admin_content')
    @php
        $label = fn (?string $value): string => str((string) ($value ?: 'none'))->replace('_', ' ')->headline()->toString();
    @endphp

    <div class="row">
        <div class="col-lg-4 col-md-6">
            <div class="small-box bg-primary">
                <div class="inner">
                    <h3>{{ $settings['membership_credit_days'] }}</h3>
                    <p>Membership Credit Days</p>
                </div>
                <div class="icon"><i class="fas fa-calendar-plus"></i></div>
            </div>
        </div>
        <div class="col-lg-4 col-md-6">
            <div class="small-box bg-info">
                <div class="inner">
                    <h3>{{ $settings['membership_credit_expiration_months'] }}</h3>
                    <p>Expiration Months</p>
                </div>
                <div class="icon"><i class="fas fa-hourglass-end"></i></div>
            </div>
        </div>
        <div class="col-lg-4 col-md-6">
            <div class="small-box bg-warning">
                <div class="inner">
                    <h3>{{ $settings['expiring_soon_notification_days'] }}</h3>
                    <p>Expiring-Soon Days</p>
                </div>
                <div class="icon"><i class="fas fa-bell"></i></div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Current Reward Rules</h3>
            <div class="card-tools text-muted">Values are read from config/affiliate.php and environment overrides.</div>
        </div>
        <div class="card-body p-0">
            <table class="table table-striped mb-0">
                <tbody>
                    <tr>
                        <th style="width: 260px;">Reward Plan</th>
                        <td>{{ $label($settings['reward_plan']) }}</td>
                    </tr>
                    <tr>
                        <th>Qualification Event</th>
                        <td>{{ $label($settings['qualification_event']) }}</td>
                    </tr>
                    <tr>
                        <th>Membership Credit Tier</th>
                        <td>{{ $label($settings['membership_credit_tier']) }}</td>
                    </tr>
                    <tr>
                        <th>Membership Credit Value</th>
                        <td>{{ $settings['membership_credit_days'] }} days</td>
                    </tr>
                    <tr>
                        <th>Credit Expiration</th>
                        <td>{{ $settings['membership_credit_expiration_months'] }} months after issue</td>
                    </tr>
                    <tr>
                        <th>Expiring-Soon Notification Window</th>
                        <td>{{ $settings['expiring_soon_notification_days'] }} days before expiration</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Configuration Keys</h3>
        </div>
        <div class="card-body">
            <div class="row">
                @foreach ([
                    'AFFILIATE_REWARD_PLAN',
                    'AFFILIATE_QUALIFICATION_EVENT',
                    'AFFILIATE_MEMBERSHIP_CREDIT_TIER',
                    'AFFILIATE_MEMBERSHIP_CREDIT_DAYS',
                    'AFFILIATE_MEMBERSHIP_CREDIT_EXPIRES_AFTER_MONTHS',
                    'AFFILIATE_MEMBERSHIP_CREDIT_EXPIRING_SOON_DAYS',
                ] as $key)
                    <div class="col-lg-6">
                        <code>{{ $key }}</code>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endsection
