@extends('layout.app')

@section('title', 'AI Settings')
@section('page-title', 'AI Settings')

@section('content')
<div class="container" style="max-width:780px;">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="mb-0" style="font-family:'DM Serif Display',serif; font-size:1.6rem;">AI Settings</h1>
            <p class="text-muted mb-0 small">Control whether AI can use prior student interactions across lessons.</p>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger mb-3">
            <ul class="mb-0 ps-3">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.settings.update') }}">
        @csrf
        @method('PATCH')

        <div class="card mb-4">
            <div class="card-header fw-semibold">Memory Control</div>
            <div class="card-body">
                <div class="form-check form-switch mb-3">
                    <input
                        class="form-check-input"
                        type="checkbox"
                        role="switch"
                        id="ai_memory_enabled"
                        name="ai_memory_enabled"
                        value="1"
                        {{ $aiMemoryEnabled ? 'checked' : '' }}>
                    <label class="form-check-label fw-semibold" for="ai_memory_enabled">
                        Enable AI memory across lessons
                    </label>
                </div>

                <p class="text-muted small mb-2">
                    When enabled, AI can use a student's prior interactions from other lessons as supporting context.
                </p>
                <p class="text-muted small mb-0">
                    When disabled, AI stays restricted to the current lesson and treats unrelated follow-up questions as out of context.
                </p>
            </div>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">Save Settings</button>
        </div>
    </form>
</div>
@endsection