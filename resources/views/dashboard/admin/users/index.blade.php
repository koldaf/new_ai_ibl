@extends('layout.app')

@section('title', 'Users')
@section('page-title', 'User Management')

@section('content')
<div class="container-fluid px-0">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="mb-0" style="font-family:'DM Serif Display',serif; font-size:1.6rem;">Users</h1>
            <p class="text-muted mb-0 small">Manage students and teachers</p>
        </div>
        <a href="{{ route('admin.users.create') }}" class="btn btn-primary">
            <i class="bi bi-person-plus me-1"></i>Add User
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Filter bar --}}
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.users.index') }}" class="row g-3 align-items-end">
                <div class="col-md-5">
                    <label for="q" class="form-label">Search</label>
                    <input type="text" id="q" name="q" class="form-control" value="{{ $filters['q'] }}" placeholder="Search by name">
                </div>
                <div class="col-md-3">
                    <label for="role" class="form-label">Role</label>
                    <select id="role" name="role" class="form-select">
                        <option value="">All roles</option>
                        <option value="student" {{ $filters['role'] === 'student' ? 'selected' : '' }}>Student</option>
                        <option value="teacher" {{ $filters['role'] === 'teacher' ? 'selected' : '' }}>Teacher</option>
                    </select>
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Apply</button>
                    <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    {{-- Table --}}
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Name</th>
                            <th>Role</th>
                            <th>Assigned Lessons</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($users as $i => $user)
                        <tr>
                            <td class="text-muted small">{{ $users->firstItem() + $i }}</td>
                            <td class="fw-semibold">{{ $user->name }}</td>
                            <td>
                                @if($user->role === 'teacher')
                                    <span class="badge text-bg-primary">Teacher</span>
                                @else
                                    <span class="badge text-bg-secondary">Student</span>
                                @endif
                            </td>
                            <td>
                                @if($user->role === 'teacher')
                                    <span class="text-muted small">{{ $user->assigned_lessons_count }} lesson{{ $user->assigned_lessons_count !== 1 ? 's' : '' }}</span>
                                @else
                                    <span class="text-muted small">—</span>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-sm btn-outline-primary me-1">Edit</a>
                                <form action="{{ route('admin.users.destroy', $user) }}" method="POST" class="d-inline">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger"
                                        onclick="return confirm('Delete user {{ addslashes($user->name) }}? This cannot be undone.')">
                                        Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">No users match the selected filters.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($users->hasPages())
            <div class="card-footer bg-white">
                {{ $users->links() }}
            </div>
        @endif
    </div>

</div>
@endsection
