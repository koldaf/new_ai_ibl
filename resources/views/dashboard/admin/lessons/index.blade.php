@extends('layout.app')

@section('content')
<div class="container">
    <h1>Lessons</h1>
    <a href="{{ route('admin.lessons.create') }}" class="btn btn-primary mb-3">Create New Lesson</a>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <table class="table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Title</th>
                <th>Teacher</th>
                <th>Subject</th>
                <th>Grade Level</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @php $i = 0; @endphp
            @foreach($lessons as $lesson)
            <tr>
                <td>{{ ++$i }}</td>
                <td>{{ $lesson->title }}</td>
                <td>{{ $lesson->teacher?->name ?? 'Unassigned' }}</td>
                <td>{{ $lesson->subject }}</td>
                <td>{{ $lesson->grade_level }}</td>
                <td>
                    <a href="{{ route('admin.lessons.edit', $lesson) }}" class="btn btn-sm btn-primary">Edit</a>
                    <form action="{{ route('admin.lessons.destroy', $lesson) }}" method="POST" class="d-inline">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Delete lesson?')">Delete</button>
                    </form>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    {{ $lessons->links() }}
</div>
@endsection