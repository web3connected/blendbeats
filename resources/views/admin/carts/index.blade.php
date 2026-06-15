@extends('admin.layouts.app', [
    'title' => 'Commerce Carts',
    'heading' => 'Commerce Carts',
    'subtitle' => 'Review active carts and how items are routed by fulfillment type.',
])

@section('admin_content')
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Recent Carts</h3>
        </div>
        <div class="card-body p-0">
            <table class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th>Cart</th>
                        <th>User</th>
                        <th>Status</th>
                        <th>Items</th>
                        <th>Total</th>
                        <th>Routes</th>
                        <th>Updated</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($carts as $cart)
                        @php($payload = $cartService->payload($cart))
                        <tr>
                            <td>#{{ $cart->id }}</td>
                            <td>
                                @if ($cart->user)
                                    {{ $cart->user->name }}
                                    <div class="text-muted small">{{ $cart->user->email }}</div>
                                @else
                                    Guest session
                                    <div class="text-muted small">{{ $cart->session_id ?: 'No session recorded' }}</div>
                                @endif
                            </td>
                            <td><span class="badge badge-{{ $cart->status === 'active' ? 'success' : 'secondary' }}">{{ ucfirst($cart->status) }}</span></td>
                            <td>{{ $payload['item_count'] }}</td>
                            <td>{{ $payload['estimated_total_label'] }}</td>
                            <td>
                                @forelse ($payload['checkout_groups'] as $group)
                                    <span class="badge badge-dark">{{ $group['label'] }}: {{ $group['item_count'] }}</span>
                                @empty
                                    <span class="text-muted">No routes yet</span>
                                @endforelse
                            </td>
                            <td>{{ optional($cart->updated_at)->format('Y-m-d H:i') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">No carts found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($carts->hasPages())
            <div class="card-footer">{{ $carts->links() }}</div>
        @endif
    </div>
@endsection
