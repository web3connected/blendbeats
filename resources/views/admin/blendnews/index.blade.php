@extends('admin.layouts.app', [
    'title' => 'BlendNews',
    'heading' => 'BlendNews',
    'subtitle' => 'Manage news stories, drafts, review status, sources, categories, and featured coverage.',
])

@section('admin_content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="row mb-3">
        @foreach ($statuses as $statusKey => $statusLabel)
            <div class="col-md-2 col-sm-4 mb-2">
                <div class="small-box bg-dark mb-0">
                    <div class="inner">
                        <h3>{{ $statusCounts[$statusKey] ?? 0 }}</h3>
                        <p>{{ $statusLabel }}</p>
                    </div>
                    <div class="icon"><i class="fas fa-newspaper"></i></div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-list mr-1"></i> Stories
            </h3>
            <div class="card-tools">
                <a href="{{ route('admin.blendnews.create') }}" class="btn btn-primary btn-sm">
                    <i class="fas fa-plus mr-1"></i> Create Story
                </a>
            </div>
        </div>

        <div class="card-body border-bottom">
            <form method="GET" action="{{ route('admin.blendnews.index') }}">
                <div class="form-row">
                    <div class="col-md-4 mb-2">
                        <input
                            type="text"
                            name="search"
                            value="{{ request('search') }}"
                            class="form-control form-control-sm"
                            placeholder="Search title, excerpt, or content"
                        >
                    </div>
                    <div class="col-md-2 mb-2">
                        <select name="status" class="form-control form-control-sm">
                            <option value="">All statuses</option>
                            @foreach ($statuses as $statusKey => $statusLabel)
                                <option value="{{ $statusKey }}" @selected(request('status') === $statusKey)>{{ $statusLabel }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 mb-2">
                        <select name="category_id" class="form-control form-control-sm">
                            <option value="">All categories</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}" @selected((string) request('category_id') === (string) $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 mb-2">
                        <input type="date" name="date" value="{{ request('date') }}" class="form-control form-control-sm">
                    </div>
                    <div class="col-md-1 mb-2">
                        <button class="btn btn-secondary btn-sm btn-block" type="submit">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <div class="card-body p-0">
            <table class="table table-striped table-hover mb-0">
                <thead>
                    <tr>
                        <th>Story</th>
                        <th>Category</th>
                        <th>Source</th>
                        <th>Status</th>
                        <th>Flags</th>
                        <th>Published</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($posts as $post)
                        @php
                            $imagePath = data_get($post->featured_image, 'path');
                            $imageUrl = $imagePath ? asset('media/'.ltrim($imagePath, '/')) : null;
                        @endphp
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="mr-3 bg-black border d-flex align-items-center justify-content-center" style="width: 68px; height: 48px;">
                                        @if ($imageUrl)
                                            <img src="{{ $imageUrl }}" alt="" style="width: 68px; height: 48px; object-fit: cover;">
                                        @else
                                            <i class="fas fa-image text-muted"></i>
                                        @endif
                                    </div>
                                    <div>
                                        <strong>{{ $post->title }}</strong>
                                        <div class="text-muted small">{{ $post->slug }}</div>
                                        @if ($post->excerpt)
                                            <div class="text-muted small">{{ Str::limit($post->excerpt, 90) }}</div>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td>{{ $post->primaryCategory?->name ?? 'Unassigned' }}</td>
                            <td>{{ $post->newsSource?->name ?? 'Internal' }}</td>
                            <td><span class="badge badge-{{ $post->status === 'published' ? 'success' : ($post->status === 'review' ? 'warning' : 'secondary') }}">{{ ucfirst($post->status) }}</span></td>
                            <td>
                                @if ($post->is_breaking)
                                    <span class="badge badge-danger">Breaking</span>
                                @endif
                                @if ($post->is_featured)
                                    <span class="badge badge-warning">Featured</span>
                                @endif
                                @if ($post->is_verified)
                                    <span class="badge badge-info">Verified</span>
                                @endif
                            </td>
                            <td>
                                @if ($post->published_at)
                                    {{ $post->published_at->format('M d, Y') }}
                                    <div class="text-muted small">{{ $post->published_at->format('g:i A') }}</div>
                                @else
                                    <span class="text-muted">Not published</span>
                                @endif
                            </td>
                            <td class="text-right">
                                <a href="{{ route('news.show', ['post' => $post->slug]) }}" class="btn btn-info btn-sm" target="_blank" rel="noopener">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="{{ route('admin.blendnews.edit', $post) }}" class="btn btn-warning btn-sm">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <form method="POST" action="{{ route('admin.blendnews.destroy', $post) }}" class="d-inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Delete this BlendNews story?')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-5">
                                <i class="fas fa-newspaper fa-2x mb-2 d-block"></i>
                                No BlendNews stories found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($posts->hasPages())
            <div class="card-footer">{{ $posts->links() }}</div>
        @endif
    </div>
@endsection
