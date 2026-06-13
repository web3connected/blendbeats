@extends('admin.layouts.app', [
    'title' => 'Lounge Playlist',
    'heading' => 'Lounge Playlist',
    'subtitle' => 'Approve and order the public uploads used by DJ Lounge Live.',
])

@php
    $mediaTitle = function ($file): string {
        $portfolio = $file->metadata['portfolio'] ?? [];

        return $portfolio['title'] ?? $file->original_name ?? $file->name ?? 'Untitled upload';
    };

    $mediaMeta = function ($file): string {
        $portfolio = $file->metadata['portfolio'] ?? [];
        $parts = array_filter([
            $portfolio['media_kind'] ?? 'mix',
            $portfolio['genre'] ?? null,
            $file->user?->name,
        ]);

        return implode(' / ', $parts);
    };
@endphp

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
                    <h3>{{ $tracks->where('is_active', true)->count() }}</h3>
                    <p>Active Lounge Tracks</p>
                </div>
                <div class="icon">
                    <i class="fas fa-music"></i>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="small-box bg-success">
                <div class="inner">
                    <h3>{{ $tracks->where('is_featured', true)->count() }}</h3>
                    <p>Featured Plays</p>
                </div>
                <div class="icon">
                    <i class="fas fa-star"></i>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="small-box bg-warning">
                <div class="inner">
                    <h3>{{ $availableMedia->count() }}</h3>
                    <p>Public Uploads Available</p>
                </div>
                <div class="icon">
                    <i class="fas fa-headphones"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Approve Public Upload</h3>
        </div>
        <div class="card-body">
            @if ($availableMedia->isEmpty())
                <p class="text-muted mb-0">No unapproved public audio uploads are available.</p>
            @else
                <form method="POST" action="{{ route('admin.admincenter.loungeplaylist.store') }}">
                    @csrf
                    <div class="row">
                        <div class="col-md-7">
                            <div class="form-group">
                                <label for="media_file_id">Public upload</label>
                                <select id="media_file_id" name="media_file_id" class="form-control" required>
                                    @foreach ($availableMedia as $file)
                                        <option value="{{ $file->id }}">
                                            {{ $mediaTitle($file) }} - {{ $mediaMeta($file) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label for="sort_order">Order</label>
                                <input id="sort_order" type="number" min="0" name="sort_order" value="0" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <div class="form-group mb-0">
                                <div class="custom-control custom-checkbox mb-3">
                                    <input id="is_featured" type="checkbox" name="is_featured" value="1" class="custom-control-input">
                                    <label for="is_featured" class="custom-control-label">Featured</label>
                                </div>
                                <button type="submit" class="btn btn-primary">Approve Track</button>
                            </div>
                        </div>
                    </div>
                </form>
            @endif
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Live Playlist Order</h3>
            <div class="card-tools text-muted">
                Server time and this order determine the shared live position.
            </div>
        </div>
        <div class="card-body p-0">
            <table class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th style="width: 90px;">Order</th>
                        <th>Track</th>
                        <th>DJ</th>
                        <th style="width: 120px;">Active</th>
                        <th style="width: 130px;">Featured</th>
                        <th style="width: 190px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($tracks as $track)
                        @php($file = $track->mediaFile)
                        <tr>
                            <td colspan="6">
                                <form method="POST" action="{{ route('admin.admincenter.loungeplaylist.update', $track) }}" class="row align-items-center">
                                    @csrf
                                    @method('PUT')
                                    <div class="col-md-1">
                                        <input type="number" min="0" name="sort_order" value="{{ $track->sort_order }}" class="form-control form-control-sm">
                                    </div>
                                    <div class="col-md-4">
                                        <strong>{{ $file ? $mediaTitle($file) : 'Missing media file' }}</strong>
                                        <div class="text-muted small">{{ $file ? $mediaMeta($file) : 'Removed upload' }}</div>
                                    </div>
                                    <div class="col-md-2">{{ $file?->user?->name ?? 'Unknown DJ' }}</div>
                                    <div class="col-md-1">
                                        <div class="custom-control custom-checkbox">
                                            <input id="active_{{ $track->id }}" type="checkbox" name="is_active" value="1" class="custom-control-input" @checked($track->is_active)>
                                            <label for="active_{{ $track->id }}" class="custom-control-label">Yes</label>
                                        </div>
                                    </div>
                                    <div class="col-md-1">
                                        <div class="custom-control custom-checkbox">
                                            <input id="featured_{{ $track->id }}" type="checkbox" name="is_featured" value="1" class="custom-control-input" @checked($track->is_featured)>
                                            <label for="featured_{{ $track->id }}" class="custom-control-label">Yes</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3 text-right">
                                        <button type="submit" class="btn btn-sm btn-primary">Save</button>
                                    </div>
                                </form>
                                <form method="POST" action="{{ route('admin.admincenter.loungeplaylist.destroy', $track) }}" class="mt-2 text-right" onsubmit="return confirm('Remove this track from the Lounge Playlist?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Remove</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-muted">No tracks are approved for DJ Lounge Live yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
