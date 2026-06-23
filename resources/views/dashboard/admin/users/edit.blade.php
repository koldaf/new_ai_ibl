@extends('layout.app')

@section('title', 'Edit User — ' . $user->name)
@section('page-title', 'Edit User')

@section('content')
<div class="container" style="max-width:700px;">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="mb-0" style="font-family:'DM Serif Display',serif; font-size:1.6rem;">{{ $user->name }}</h1>
            <p class="text-muted mb-0 small">Edit account details and lesson assignments</p>
        </div>
        <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">Back to users</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.users.update', $user) }}" id="editUserForm">
        @csrf @method('PATCH')

        @if($errors->any())
            <div class="alert alert-danger mb-3">
                <ul class="mb-0 ps-3">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Account details --}}
        <div class="card mb-4">
            <div class="card-header fw-semibold">Account Details</div>
            <div class="card-body">

                <div class="mb-3">
                    <label for="name" class="form-label">Full Name <span class="text-danger">*</span></label>
                    <input type="text" id="name" name="name"
                        class="form-control @error('name') is-invalid @enderror"
                        value="{{ old('name', $user->name) }}" required>
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-3">
                    <label for="role" class="form-label">Role <span class="text-danger">*</span></label>
                    <select id="role" name="role"
                        class="form-select @error('role') is-invalid @enderror" required>
                        <option value="student" {{ old('role', $user->role) === 'student' ? 'selected' : '' }}>Student</option>
                        <option value="teacher" {{ old('role', $user->role) === 'teacher' ? 'selected' : '' }}>Teacher</option>
                    </select>
                    @error('role')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <hr class="my-3">

                <p class="small text-muted mb-2">Leave password fields blank to keep the current password.</p>

                <div class="mb-3">
                    <label for="password" class="form-label">New Password</label>
                    <input type="password" id="password" name="password"
                        class="form-control @error('password') is-invalid @enderror">
                    @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-0">
                    <label for="password_confirmation" class="form-label">Confirm New Password</label>
                    <input type="password" id="password_confirmation" name="password_confirmation"
                        class="form-control">
                </div>

            </div>
        </div>

        {{-- Lesson Assignment (teacher only) --}}
        <div class="card mb-4" id="lessonAssignmentPanel" style="{{ old('role', $user->role) === 'teacher' ? '' : 'display:none;' }}">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-semibold">Lesson Assignments</span>
                <span class="small text-muted" id="selectedCount">
                    {{ count($assignedIds) }} selected
                </span>
            </div>
            <div class="card-body">
                @if($lessons->isEmpty())
                    <p class="text-muted mb-0">No lessons exist yet. <a href="{{ route('admin.lessons.create') }}">Create one</a>.</p>
                @else
                    <p class="small text-muted mb-3">Check the lessons to assign to this teacher. Unchecked lessons will be removed from this teacher's assignment.</p>

                    <div class="row g-2" id="lessonCheckboxes">
                        @foreach($lessons as $lesson)
                        <div class="col-md-6">
                            <div class="form-check border rounded p-2 ps-4 lesson-check-item {{ in_array($lesson->id, $assignedIds) ? 'border-primary bg-body-secondary' : '' }}">
                                <input class="form-check-input lesson-checkbox" type="checkbox"
                                    name="lessons[]"
                                    value="{{ $lesson->id }}"
                                    id="lesson_{{ $lesson->id }}"
                                    {{ in_array($lesson->id, $assignedIds) ? 'checked' : '' }}>
                                <label class="form-check-label small w-100" for="lesson_{{ $lesson->id }}" style="cursor:pointer;">
                                    <span class="fw-semibold d-block">{{ $lesson->title }}</span>
                                    @if($lesson->subject || $lesson->grade_level)
                                        <span class="text-muted">
                                            {{ implode(' · ', array_filter([$lesson->subject, $lesson->grade_level])) }}
                                        </span>
                                    @endif
                                </label>
                            </div>
                        </div>
                        @endforeach
                    </div>

                    <div class="d-flex gap-2 mt-3">
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="selectAllLessons">Select all</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="clearAllLessons">Clear all</button>
                    </div>
                @endif
            </div>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">Save Changes</button>
            <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>

    </form>

</div>
@endsection

@push('scripts')
<script>
(function () {
    const roleSelect  = document.getElementById('role');
    const panel       = document.getElementById('lessonAssignmentPanel');
    const countEl     = document.getElementById('selectedCount');
    const checkboxes  = () => document.querySelectorAll('.lesson-checkbox');

    function updateCount() {
        const n = document.querySelectorAll('.lesson-checkbox:checked').length;
        if (countEl) countEl.textContent = n + ' selected';
    }

    function updateHighlight(cb) {
        const item = cb.closest('.lesson-check-item');
        if (!item) return;
        item.classList.toggle('border-primary', cb.checked);
        item.classList.toggle('bg-body-secondary', cb.checked);
    }

    function togglePanel() {
        if (roleSelect.value === 'teacher') {
            panel.style.display = '';
        } else {
            panel.style.display = 'none';
        }
    }

    roleSelect?.addEventListener('change', togglePanel);

    document.getElementById('selectAllLessons')?.addEventListener('click', function () {
        checkboxes().forEach(cb => { cb.checked = true; updateHighlight(cb); });
        updateCount();
    });

    document.getElementById('clearAllLessons')?.addEventListener('click', function () {
        checkboxes().forEach(cb => { cb.checked = false; updateHighlight(cb); });
        updateCount();
    });

    checkboxes().forEach(cb => {
        cb.addEventListener('change', function () {
            updateHighlight(cb);
            updateCount();
        });
    });
})();
</script>
@endpush
