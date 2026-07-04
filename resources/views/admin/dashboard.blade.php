@extends('admin.layouts.app', [
    'title' => 'Dashboard',
    'heading' => 'Site Dashboard',
    'subtitle' => 'A quick WordPress-style overview of site content, users, commerce, and admin tasks.',
])

@section('admin_content')
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
        <div>
            <h2 class="h5 mb-1">At a Glance</h2>
            <p class="text-muted mb-0">The main site dashboard keeps platform operations separate from battle operations.</p>
        </div>
        <span class="text-muted small">Updated {{ $generatedAt->format('M j, Y g:i A') }}</span>
    </div>

    <div class="row">
        @foreach ($summaryCards as $card)
            <div class="col-12 col-md-6 col-xl-3">
                <div class="small-box bg-{{ $card['theme'] }}">
                    <div class="inner">
                        <h3>{{ $card['value'] }}</h3>
                        <p class="mb-1">{{ $card['label'] }}</p>
                        <span class="small">{{ $card['detail'] }}</span>
                    </div>
                    <div class="icon">
                        <i class="{{ $card['icon'] }}"></i>
                    </div>
                    @if ($card['href'])
                        <a href="{{ $card['href'] }}" class="small-box-footer">
                            Manage <i class="fas fa-arrow-circle-right ml-1"></i>
                        </a>
                    @else
                        <span class="small-box-footer text-white-50">Overview</span>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    <div class="row">
        @foreach ($activityCards as $card)
            <div class="col-12 col-md-6 col-xl-3">
                <div class="info-box">
                    <span class="info-box-icon bg-{{ $card['theme'] }}">
                        <i class="{{ $card['icon'] }}"></i>
                    </span>
                    <div class="info-box-content">
                        <span class="info-box-text">{{ $card['label'] }}</span>
                        <span class="info-box-number">{{ $card['value'] }}</span>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row">
        <div class="col-12 col-xl-8">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Site Activity</h3>
                </div>
                <div class="card-body p-0">
                    <div class="row no-gutters">
                        <div class="col-12 col-lg-6 border-right">
                            <div class="p-3">
                                <h4 class="h6 text-uppercase text-muted font-weight-bold">Recent Users</h4>
                                <div class="table-responsive">
                                    <table class="table table-sm mb-0">
                                        <tbody>
                                            @forelse ($recentUsers as $user)
                                                <tr>
                                                    <td>
                                                        <div class="font-weight-bold">{{ $user->name ?: 'Unnamed User' }}</div>
                                                        <div class="text-muted small">{{ $user->email }}</div>
                                                    </td>
                                                    <td class="text-right text-muted small">{{ $user->created_at->diffForHumans() }}</td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td class="text-muted py-4">No users yet.</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-lg-6">
                            <div class="p-3">
                                <h4 class="h6 text-uppercase text-muted font-weight-bold">Recent Mixes</h4>
                                <div class="table-responsive">
                                    <table class="table table-sm mb-0">
                                        <tbody>
                                            @forelse ($recentMixes as $mix)
                                                <tr>
                                                    <td>
                                                        <div class="font-weight-bold">{{ $mix->title }}</div>
                                                        <div class="text-muted small">
                                                            {{ $mix->user?->name ?: 'Unknown DJ' }}
                                                            @if ($mix->genre)
                                                                <span class="mx-1">&middot;</span>{{ $mix->genre }}
                                                            @endif
                                                        </div>
                                                    </td>
                                                    <td class="text-right">
                                                        <span class="badge badge-{{ $mix->is_public ? 'success' : 'secondary' }}">
                                                            {{ $mix->is_public ? 'Public' : 'Draft' }}
                                                        </span>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td class="text-muted py-4">No mixes yet.</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Recent BlendNews</h3>
                    <div class="card-tools">
                        <a href="{{ route('admin.blendnews.index') }}" class="btn btn-tool">
                            <i class="fas fa-external-link-alt"></i>
                        </a>
                    </div>
                </div>
                <div class="card-body p-0">
                    <table class="table table-striped mb-0">
                        <thead>
                            <tr>
                                <th>Story</th>
                                <th>Author</th>
                                <th>Status</th>
                                <th class="text-right">Updated</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($recentPosts as $post)
                                <tr>
                                    <td class="font-weight-bold">{{ $post->title }}</td>
                                    <td>{{ $post->author?->name ?: 'Unknown' }}</td>
                                    <td>
                                        <span class="badge badge-{{ $post->status === 'published' ? 'success' : ($post->status === 'review' ? 'warning' : 'secondary') }}">
                                            {{ str($post->status)->headline() }}
                                        </span>
                                    </td>
                                    <td class="text-right text-muted small">{{ $post->created_at->diffForHumans() }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">No stories yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-4">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Quick Actions</h3>
                </div>
                <div class="card-body">
                    @foreach ($quickActions as $action)
                        <a href="{{ $action['href'] }}" class="btn btn-outline-{{ $action['theme'] }} btn-block text-left mb-2">
                            <i class="{{ $action['icon'] }} mr-2"></i>
                            <span class="font-weight-bold">{{ $action['label'] }}</span>
                            <span class="d-block text-muted small ml-4">{{ $action['description'] }}</span>
                        </a>
                    @endforeach
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Content Snapshot</h3>
                </div>
                <div class="card-body p-0">
                    @include('admin.partials.dashboard-status-list', ['items' => $contentSnapshot])
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Commerce Snapshot</h3>
                </div>
                <div class="card-body p-0">
                    @include('admin.partials.dashboard-status-list', ['items' => $commerceSnapshot])
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Community Snapshot</h3>
                </div>
                <div class="card-body p-0">
                    @include('admin.partials.dashboard-status-list', ['items' => $communitySnapshot])
                </div>
            </div>

            <div class="card card-outline card-danger">
                <div class="card-header">
                    <h3 class="card-title">Battle Snapshot</h3>
                    <div class="card-tools">
                        <a href="{{ route('admin.battle-admin.dashboard') }}" class="btn btn-tool">
                            <i class="fas fa-trophy"></i>
                        </a>
                    </div>
                </div>
                <div class="card-body p-0">
                    @include('admin.partials.dashboard-status-list', ['items' => $battleSnapshot])
                </div>
                <div class="card-footer">
                    <a href="{{ route('admin.battle-admin.dashboard') }}" class="btn btn-danger btn-block">
                        <i class="fas fa-trophy mr-1"></i> Open Battle Admin
                    </a>
                </div>
            </div>
        </div>
    </div>
@endsection
