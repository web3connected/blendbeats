@extends('admin.layouts.app', [
    'title' => 'Dashboard',
    'heading' => 'Dashboard',
    'subtitle' => 'Administration foundation for BlendBeats management tools.',
])

@section('admin_content')
    <div class="row">
        @include('admin.components.stat-card', [
            'label' => 'Users',
            'value' => 'Module',
            'icon' => 'fas fa-users',
            'theme' => 'bg-info',
            'href' => route('admin.resources.show', 'users'),
        ])
        @include('admin.components.stat-card', [
            'label' => 'Roles & Permissions',
            'value' => 'Access',
            'icon' => 'fas fa-user-shield',
            'theme' => 'bg-success',
            'href' => route('admin.resources.show', 'roles'),
        ])
        @include('admin.components.stat-card', [
            'label' => 'Reports',
            'value' => 'Insights',
            'icon' => 'fas fa-chart-line',
            'theme' => 'bg-warning',
            'href' => route('admin.resources.show', 'reports'),
        ])
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Admin Resource Structure</h3>
        </div>
        <div class="card-body p-0">
            <table class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th>Area</th>
                        <th>Purpose</th>
                        <th class="text-right">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ([
                        'Users' => 'Public account and DJ management',
                        'Roles' => 'Administrative role assignment',
                        'Permissions' => 'Authorization policy capabilities',
                        'Settings' => 'Platform configuration',
                        'Content Management' => 'Pages, media, and editorial workflows',
                        'Reporting' => 'Analytics, moderation queues, and exports',
                    ] as $area => $purpose)
                        <tr>
                            <td>{{ $area }}</td>
                            <td>{{ $purpose }}</td>
                            <td class="text-right"><span class="badge badge-secondary">Scaffolded</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
