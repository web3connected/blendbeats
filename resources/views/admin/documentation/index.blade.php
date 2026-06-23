@extends('admin.layouts.app', [
    'title' => 'Documentation Center',
    'heading' => 'Documentation Center',
    'subtitle' => 'User documentation inventory, routes, categories, and management foundation.',
])

@section('admin_content')
    @php
        $categoryTitles = $categories->pluck('title', 'slug');
        $statusBadge = fn (string $status): string => match ($status) {
            'active' => 'success',
            'foundation' => 'warning',
            'future' => 'secondary',
            default => 'light',
        };
    @endphp

    <div class="row">
        <div class="col-lg-3 col-md-6">
            <div class="small-box bg-primary">
                <div class="inner">
                    <h3>{{ $stats['categories'] }}</h3>
                    <p>Categories</p>
                </div>
                <div class="icon"><i class="fas fa-layer-group"></i></div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="small-box bg-info">
                <div class="inner">
                    <h3>{{ $stats['articles'] }}</h3>
                    <p>Articles</p>
                </div>
                <div class="icon"><i class="fas fa-book-open"></i></div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="small-box bg-success">
                <div class="inner">
                    <h3>{{ $stats['active'] }}</h3>
                    <p>Active Articles</p>
                </div>
                <div class="icon"><i class="fas fa-check-circle"></i></div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="small-box bg-warning">
                <div class="inner">
                    <h3>{{ $stats['foundation'] + $stats['future'] }}</h3>
                    <p>Foundation / Future</p>
                </div>
                <div class="icon"><i class="fas fa-tools"></i></div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Documentation Foundation</h3>
            <div class="card-tools">
                <a href="/account/docs" class="btn btn-sm btn-primary">
                    <i class="fas fa-external-link-alt mr-1"></i> Open User Docs
                </a>
            </div>
        </div>
        <div class="card-body">
            <p class="mb-2">
                The user-facing Documentation Center is available at <code>/account/docs</code> with searchable static articles and category navigation.
            </p>
            <p class="mb-0 text-muted">
                Content is currently managed in <code>{{ $source }}</code>. A future editor can move these records into database-backed drafts, publishing status, markdown, screenshots, and videos.
            </p>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-4">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Categories</h3>
                </div>
                <div class="card-body p-0">
                    <table class="table table-striped mb-0">
                        <thead>
                            <tr>
                                <th>Category</th>
                                <th class="text-right">Articles</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($categories as $category)
                                <tr>
                                    <td>
                                        <strong>{{ $category['title'] }}</strong>
                                        <div class="small text-muted">{{ $category['description'] }}</div>
                                    </td>
                                    <td class="text-right">{{ $category['article_count'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-xl-8">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Article Inventory</h3>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped mb-0">
                            <thead>
                                <tr>
                                    <th>Article</th>
                                    <th>Category</th>
                                    <th>Status</th>
                                    <th>Route</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($articles as $article)
                                    <tr>
                                        <td>
                                            <strong>{{ $article['title'] }}</strong>
                                            <div class="small text-muted">{{ $article['slug'] }}</div>
                                        </td>
                                        <td>{{ $categoryTitles[$article['category']] ?? $article['category'] }}</td>
                                        <td>
                                            <span class="badge badge-{{ $statusBadge($article['status']) }}">
                                                {{ str($article['status'])->headline() }}
                                            </span>
                                        </td>
                                        <td><code>{{ $article['route'] }}</code></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
