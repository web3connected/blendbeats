@extends('admin.layouts.app', [
    'title' => 'Beta Token Management',
    'heading' => 'Beta Token Management',
    'subtitle' => 'Manage beta test tokens for battle economy testing.',
])

@section('admin_content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="alert alert-warning">
        <strong>Beta Demo Mode:</strong>
        Test tokens are not real money and cannot be withdrawn or converted to cash.
    </div>

    <div class="row">
        <div class="col-md-3">
            <div class="small-box {{ $settings['demo_mode'] ? 'bg-success' : 'bg-secondary' }}">
                <div class="inner">
                    <h3>{{ $settings['demo_mode'] ? 'On' : 'Off' }}</h3>
                    <p>Demo Mode Status</p>
                </div>
                <div class="icon"><i class="fas fa-vial"></i></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="small-box bg-info">
                <div class="inner">
                    <h3>{{ number_format($stats['total_issued']) }}</h3>
                    <p>Total Test Tokens Issued</p>
                </div>
                <div class="icon"><i class="fas fa-coins"></i></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="small-box bg-warning">
                <div class="inner">
                    <h3>{{ number_format($stats['total_locked']) }}</h3>
                    <p>Total Test Tokens Locked</p>
                </div>
                <div class="icon"><i class="fas fa-lock"></i></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="small-box bg-danger">
                <div class="inner">
                    <h3>{{ number_format($stats['total_spent']) }}</h3>
                    <p>Total Test Tokens Spent</p>
                </div>
                <div class="icon"><i class="fas fa-fire"></i></div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">User Token Manager</h3>
                    <div class="card-tools">
                        <span class="badge badge-dark">{{ number_format($stats['active_wallets']) }} active beta wallets</span>
                    </div>
                </div>
                <div class="card-body">
                    <form method="GET" action="{{ route('admin.admincenter.beta-tokens.index') }}" class="input-group">
                        <input type="search" name="search" value="{{ $search }}" class="form-control" placeholder="Search name, email, DJ name, or handle">
                        <div class="input-group-append">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search mr-1"></i> Search
                            </button>
                            <a href="{{ route('admin.admincenter.beta-tokens.index') }}" class="btn btn-outline-secondary">Clear</a>
                        </div>
                    </form>
                </div>
                <div class="card-body table-responsive p-0">
                    <table class="table table-striped table-sm mb-0">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>DJ Profile</th>
                                <th class="text-right">Available</th>
                                <th class="text-right">Locked</th>
                                <th class="text-right">Earned</th>
                                <th class="text-right">Spent</th>
                                <th>Last Activity</th>
                                <th>Status</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($users as $user)
                                @php($wallet = $user->wallet)
                                <tr>
                                    <td>
                                        <strong>{{ $user->name }}</strong>
                                        <div class="text-muted small">{{ $user->email }}</div>
                                    </td>
                                    <td>
                                        @if ($user->djProfile)
                                            {{ $user->djProfile->dj_name }}
                                            <div class="text-muted small">{{ '@'.$user->djProfile->handle }} / {{ $user->djProfile->profile_status }}</div>
                                        @else
                                            <span class="text-muted">No DJ profile</span>
                                        @endif
                                    </td>
                                    <td class="text-right">{{ number_format((int) ($wallet?->available_balance ?? 0)) }}</td>
                                    <td class="text-right">{{ number_format((int) ($wallet?->locked_balance ?? 0)) }}</td>
                                    <td class="text-right">{{ number_format((int) ($wallet?->lifetime_earned ?? 0)) }}</td>
                                    <td class="text-right">{{ number_format((int) ($wallet?->lifetime_spent ?? 0)) }}</td>
                                    <td>{{ $user->last_token_activity_at ? \Illuminate\Support\Carbon::parse($user->last_token_activity_at)->format('Y-m-d H:i') : 'Never' }}</td>
                                    <td>
                                        <span class="badge {{ ($wallet?->status ?? 'active') === 'active' ? 'badge-success' : 'badge-secondary' }}">
                                            {{ ucfirst($wallet?->status ?? 'active') }}
                                        </span>
                                    </td>
                                    <td class="text-right">
                                        <a href="{{ route('admin.admincenter.beta-tokens.show', $user) }}" class="btn btn-info btn-xs">
                                            <i class="fas fa-history mr-1"></i> Transactions
                                        </a>
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="9">
                                        <div class="d-flex flex-wrap justify-content-end" style="gap: .5rem;">
                                            <form method="POST" action="{{ route('admin.admincenter.beta-tokens.grant', $user) }}" class="form-inline">
                                                @csrf
                                                <input type="number" name="amount" min="1" max="1000000" class="form-control form-control-sm mr-1" placeholder="Amount" required>
                                                <input type="text" name="notes" class="form-control form-control-sm mr-1" placeholder="Notes">
                                                <button type="submit" class="btn btn-success btn-sm">Grant</button>
                                            </form>

                                            <form method="POST" action="{{ route('admin.admincenter.beta-tokens.remove', $user) }}" class="form-inline">
                                                @csrf
                                                <input type="number" name="amount" min="1" max="1000000" class="form-control form-control-sm mr-1" placeholder="Amount" required>
                                                <input type="text" name="notes" class="form-control form-control-sm mr-1" placeholder="Notes">
                                                <button type="submit" class="btn btn-warning btn-sm">Remove</button>
                                            </form>

                                            <form method="POST" action="{{ route('admin.admincenter.beta-tokens.reset', $user) }}" class="form-inline">
                                                @csrf
                                                <input type="number" name="amount" min="0" max="1000000" value="{{ $settings['default_beta_tokens'] }}" class="form-control form-control-sm mr-1" required>
                                                <input type="text" name="notes" class="form-control form-control-sm mr-1" placeholder="Notes">
                                                <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Reset this beta token balance? Locked tokens will be cleared.')">Reset</button>
                                            </form>

                                            <form method="POST" action="{{ route('admin.admincenter.beta-tokens.status', $user) }}">
                                                @csrf
                                                <input type="hidden" name="status" value="{{ ($wallet?->status ?? 'active') === 'active' ? 'suspended' : 'active' }}">
                                                <button type="submit" class="btn btn-secondary btn-sm">
                                                    {{ ($wallet?->status ?? 'active') === 'active' ? 'Disable' : 'Enable' }}
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center text-muted py-4">No users match that search.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($users->hasPages())
                    <div class="card-footer">
                        {{ $users->links() }}
                    </div>
                @endif
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Demo Settings</h3>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <tr><th>Default Signup Grant</th><td class="text-right">{{ number_format($settings['default_beta_tokens']) }}</td></tr>
                        <tr><th>Manual Admin Grants</th><td class="text-right">{{ $settings['admin_grants'] ? 'Enabled' : 'Disabled' }}</td></tr>
                        <tr><th>Battle Staking</th><td class="text-right">{{ $settings['battle_staking'] ? 'Enabled' : 'Disabled' }}</td></tr>
                        <tr><th>Fan Reward Simulation</th><td class="text-right">{{ $settings['fan_rewards'] ? 'Enabled' : 'Disabled' }}</td></tr>
                        <tr><th>Winner Reward Simulation</th><td class="text-right">{{ $settings['winner_rewards'] ? 'Enabled' : 'Disabled' }}</td></tr>
                        <tr><th>Withdrawals</th><td class="text-right">{{ $settings['withdrawals_enabled'] ? 'Enabled' : 'Disabled' }}</td></tr>
                    </table>
                </div>
                <div class="card-footer text-muted small">
                    These values are driven by wallet config and environment settings.
                </div>
            </div>
        </div>
    </div>
@endsection
