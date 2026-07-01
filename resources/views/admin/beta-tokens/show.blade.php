@extends('admin.layouts.app', [
    'title' => 'Beta Token Transactions',
    'heading' => 'Beta Token Transactions',
    'subtitle' => $user->name.' / '.$user->email,
])

@section('admin_content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="mb-3">
        <a href="{{ route('admin.admincenter.beta-tokens.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-left mr-1"></i> Back to Beta Tokens
        </a>
    </div>

    <div class="row">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="text-muted small text-uppercase">Available Test Tokens</div>
                    <h3>{{ number_format($wallet->available_balance) }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="text-muted small text-uppercase">Locked Test Tokens</div>
                    <h3>{{ number_format($wallet->locked_balance) }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="text-muted small text-uppercase">Lifetime Earned</div>
                    <h3>{{ number_format($wallet->lifetime_earned) }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="text-muted small text-uppercase">Lifetime Spent</div>
                    <h3>{{ number_format($wallet->lifetime_spent) }}</h3>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Transaction History</h3>
        </div>
        <div class="card-body table-responsive p-0">
            <table class="table table-striped table-sm mb-0">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Direction</th>
                        <th class="text-right">Amount</th>
                        <th class="text-right">Available Before</th>
                        <th class="text-right">Available After</th>
                        <th class="text-right">Locked Before</th>
                        <th class="text-right">Locked After</th>
                        <th>Admin</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($transactions as $transaction)
                        <tr>
                            <td>{{ optional($transaction->created_at)->format('Y-m-d H:i') }}</td>
                            <td>{{ str($transaction->type)->replace('_', ' ')->headline() }}</td>
                            <td>{{ str($transaction->direction)->headline() }}</td>
                            <td class="text-right">{{ number_format($transaction->amount) }}</td>
                            <td class="text-right">{{ number_format($transaction->balance_before) }}</td>
                            <td class="text-right">{{ number_format($transaction->balance_after) }}</td>
                            <td class="text-right">{{ number_format($transaction->locked_balance_before) }}</td>
                            <td class="text-right">{{ number_format($transaction->locked_balance_after) }}</td>
                            <td>{{ $transaction->createdByAdmin?->name ?? '-' }}</td>
                            <td>
                                {{ $transaction->description ?: '-' }}
                                @if ($transaction->related)
                                    <div class="text-muted small">{{ class_basename($transaction->related_type) }} #{{ $transaction->related_id }}</div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="text-center text-muted py-4">No token transactions yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($transactions->hasPages())
            <div class="card-footer">
                {{ $transactions->links() }}
            </div>
        @endif
    </div>
@endsection
