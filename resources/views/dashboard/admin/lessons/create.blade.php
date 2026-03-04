@extends('layout.app')

@section('content')
<div class="container">
    <h1>Create New Lesson</h1>

    <form action="{{ route('admin.lessons.store') }}" method="POST">
        @csrf
        <div class="mb-3">
            <label for="title" class="form-label">Title</label>
            <input type="text" class="form-control @error('title') is-invalid @enderror" id="title" name="title" value="{{ old('title') }}" required>
            @error('title') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="mb-3">
            <label for="subject" class="form-label">Subject</label>
            <input type="text" class="form-control @error('subject') is-invalid @enderror" id="subject" name="subject" value="{{ old('subject') }}">
            @error('subject') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="mb-3">
            <label for="grade_level" class="form-label">Grade Level</label>
            <input type="text" class="form-control @error('grade_level') is-invalid @enderror" id="grade_level" name="grade_level" value="{{ old('grade_level') }}">
            @error('grade_level') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="mb-3">
            <label for="description" class="form-label">Description</label>
            <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description" rows="3">{{ old('description') }}</textarea>
            @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <button type="submit" class="btn btn-primary">Create Lesson</button>
        <a href="{{ route('admin.lessons.index') }}" class="btn btn-secondary">Cancel</a>
    </form>
</div>
@endsection