@extends('admin.layouts.app', [
    'title' => 'Live Videos',
    'heading' => 'Live Videos',
    'subtitle' => 'Monitor broadcasts and verify or remove saved live recordings.',
])

@section('admin_content')
    @if (session('status'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('status') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        </div>
    @endif

    <div class="row">
        <div class="col-md-4"><div class="small-box bg-danger"><div class="inner"><h3>{{ $summary['live'] }}</h3><p>Live Now</p></div><div class="icon"><i class="fas fa-broadcast-tower"></i></div></div></div>
        <div class="col-md-4"><div class="small-box bg-info"><div class="inner"><h3>{{ $summary['saved'] }}</h3><p>Saved Stream Records</p></div><div class="icon"><i class="fas fa-video"></i></div></div></div>
        <div class="col-md-4"><div class="small-box bg-success"><div class="inner"><h3>{{ $summary['ready'] }}</h3><p>Marked Ready</p></div><div class="icon"><i class="fas fa-check-circle"></i></div></div></div>
    </div>

    <div class="card">
        <div class="card-header"><h3 class="card-title">Live Stream Monitor</h3></div>
        <div class="card-body">
            <form method="GET" class="row">
                <div class="col-md-7"><div class="form-group"><label for="search">Search</label><input id="search" type="search" name="search" value="{{ $filters['search'] ?? '' }}" class="form-control" placeholder="Title, DJ, or Agora channel"></div></div>
                <div class="col-md-3"><div class="form-group"><label for="status">Stream Status</label><select id="status" name="status" class="form-control"><option value="">All streams</option><option value="live" @selected(($filters['status'] ?? null) === 'live')>Live</option><option value="ended" @selected(($filters['status'] ?? null) === 'ended')>Ended</option></select></div></div>
                <div class="col-md-2 d-flex align-items-end"><div class="form-group w-100"><button class="btn btn-primary btn-block"><i class="fas fa-filter mr-1"></i> Filter</button></div></div>
            </form>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped mb-0">
                    <thead><tr><th>Stream</th><th>DJ</th><th>Status</th><th>Recording Health</th><th>Engagement</th><th>Dates</th><th class="text-right">Actions</th></tr></thead>
                    <tbody>
                    @forelse ($streams as $stream)
                        <tr>
                            <td><div class="font-weight-bold">{{ $stream->title }}</div><div class="small text-muted">#{{ $stream->id }} · {{ $stream->agora_channel_name }}</div></td>
                            <td><div>{{ $stream->user?->djProfile?->dj_name ?? $stream->user?->name ?? 'Unknown DJ' }}</div><div class="small text-muted">{{ $stream->liveChannel?->username_slug ? '@'.$stream->liveChannel->username_slug : '' }}</div></td>
                            <td><span class="badge badge-{{ $stream->status === 'live' ? 'danger' : 'secondary' }}">{{ strtoupper($stream->status) }}</span></td>
                            <td>
                                @if (!$stream->recording_enabled)
                                    <span class="badge badge-secondary">Not Recorded</span>
                                @elseif ($stream->recording_file_exists)
                                    <span class="badge badge-success">File Available</span><div class="small text-muted mt-1">{{ \Illuminate\Support\Number::fileSize($stream->recording_file_size) }}</div>
                                @else
                                    <span class="badge badge-danger">File Missing</span>
                                @endif
                                <div class="small text-muted text-break mt-1">{{ $stream->recording_storage_path ?: 'No storage path' }}</div>
                            </td>
                            <td><div>{{ number_format($stream->views_count) }} views</div><div class="small text-muted">{{ $stream->likes_count }} likes · {{ $stream->comments_count }} comments</div></td>
                            <td><div class="small">Started: {{ $stream->started_at?->format('M j, Y g:i A') ?? '—' }}</div><div class="small text-muted">Ended: {{ $stream->ended_at?->format('M j, Y g:i A') ?? '—' }}</div></td>
                            <td class="text-right text-nowrap">
                                @if ($stream->status === 'ended' && $stream->recording_enabled)
                                    <a href="{{ url('/live/replay/'.$stream->id) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-info" title="Open replay"><i class="fas fa-external-link-alt"></i></a>
                                @endif
                                @if ($stream->status === 'ended')
                                    <form method="POST" action="{{ route('admin.admincenter.live-videos.destroy', $stream) }}" class="d-inline" onsubmit="return confirm('Remove this saved stream and its recording file? This cannot be undone.');">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-danger" title="Remove saved stream"><i class="fas fa-trash"></i></button>
                                    </form>
                                @else
                                    <button type="button" class="btn btn-sm btn-secondary" disabled title="Active streams cannot be removed"><i class="fas fa-lock"></i></button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="py-4 text-center text-muted">No live streams match these filters.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($streams->hasPages())<div class="card-footer">{{ $streams->links() }}</div>@endif
    </div>
@endsection
