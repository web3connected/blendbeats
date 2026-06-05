@extends('adminlte::page')

@section('title', 'User Accounts')

@section('content_header')
    <div class="d-flex align-items-center justify-content-between">
        <h1>User Accounts</h1>
        <a href="{{ route('admin.user-accounts.create') }}" class="btn btn-danger">
            <span class="fas fa-user-plus"></span>
            New User
        </a>
    </div>
@stop

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <x-adminlte-card title="Admin Center" theme="dark" icon="fas fa-users">
        <div class="table-responsive">
            <table class="table table-dark table-striped table-hover mb-0">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Created</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $userAccount)
                        <tr>
                            <td>{{ $userAccount->name }}</td>
                            <td>{{ $userAccount->email }}</td>
                            <td>{{ optional($userAccount->created_at)->format('M j, Y') ?? 'Not available' }}</td>
                            <td class="text-right">
                                <a href="{{ route('admin.user-accounts.edit', $userAccount) }}" class="btn btn-sm btn-outline-light">
                                    Edit
                                </a>
                                <form action="{{ route('admin.user-accounts.destroy', $userAccount) }}" method="post" class="d-inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted">No user accounts found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-3">
            {{ $users->links() }}
        </div>
    </x-adminlte-card>
@stop
