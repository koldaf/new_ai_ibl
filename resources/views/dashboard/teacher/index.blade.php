@extends('layout.app')

@section('title', 'Teacher Dashboard')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="mb-1">Teacher Dashboard</h1>
            <p class="text-muted mb-0">Tracked progress for lessons assigned to {{ $teacher->name }}.</p>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('teacher.dashboard') }}" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label for="q" class="form-label">Search lesson</label>
                    <input type="text" id="q" name="q" value="{{ $filters['q'] }}" class="form-control" placeholder="Title or description">
                </div>
                <div class="col-md-3">
                    <label for="subject" class="form-label">Subject</label>
                    <select id="subject" name="subject" class="form-select">
                        <option value="">All subjects</option>
                        @foreach($subjects as $subject)
                            <option value="{{ $subject }}" {{ $filters['subject'] === $subject ? 'selected' : '' }}>{{ $subject }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="grade_level" class="form-label">Grade level</label>
                    <select id="grade_level" name="grade_level" class="form-select">
                        <option value="">All grade levels</option>
                        @foreach($gradeLevels as $gradeLevel)
                            <option value="{{ $gradeLevel }}" {{ $filters['grade_level'] === $gradeLevel ? 'selected' : '' }}>{{ $gradeLevel }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button class="btn btn-primary w-100" type="submit">Apply</button>
                    <a href="{{ route('teacher.dashboard') }}" class="btn btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="stat-card h-100">
                <div class="stat-icon orange"><i class="bi bi-book"></i></div>
                <div>
                    <div class="stat-label">Owned Lessons</div>
                    <div class="stat-value">{{ $overviewStats['lesson_count'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card h-100">
                <div class="stat-icon blue"><i class="bi bi-people"></i></div>
                <div>
                    <div class="stat-label">Tracked Learners</div>
                    <div class="stat-value">{{ $overviewStats['tracked_learners'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card h-100">
                <div class="stat-icon green"><i class="bi bi-check2-circle"></i></div>
                <div>
                    <div class="stat-label">Explore Completion Avg</div>
                    <div class="stat-value">{{ $overviewStats['avg_explore_completion'] }}%</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card h-100">
                <div class="stat-icon purple"><i class="bi bi-bar-chart"></i></div>
                <div>
                    <div class="stat-label">Overall Completion Avg</div>
                    <div class="stat-value">{{ $overviewStats['avg_overall_completion'] }}%</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">Lesson Progress Summary</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>Lesson</th>
                            <th>Learners</th>
                            <th>Overall Completion</th>
                            <th>Stage Percentages</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($lessonSummaries as $summary)
                            <tr>
                                <td>
                                    <strong>{{ $summary['lesson']->title }}</strong>
                                    <div class="text-muted small">
                                        {{ $summary['lesson']->subject ?: 'No subject set' }}
                                        @if($summary['lesson']->grade_level)
                                            • {{ $summary['lesson']->grade_level }}
                                        @endif
                                    </div>
                                </td>
                                <td>{{ $summary['student_count'] }}</td>
                                <td>
                                    <div class="small fw-semibold mb-1">{{ $summary['completion_rate'] }}%</div>
                                    <div class="progress" style="height: 8px;">
                                        <div class="progress-bar" role="progressbar" style="width: {{ $summary['completion_rate'] }}%;" aria-valuenow="{{ $summary['completion_rate'] }}" aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                    <div class="small text-muted mt-1">{{ $summary['completed_lessons'] }} / {{ $summary['student_count'] }} completed</div>
                                </td>
                                <td>
                                    <div class="small d-flex flex-wrap gap-1">
                                        <span class="badge text-bg-light">En {{ $summary['stage_rates']['engage'] }}%</span>
                                        <span class="badge text-bg-light">Ex {{ $summary['stage_rates']['explore'] }}%</span>
                                        <span class="badge text-bg-light">Xp {{ $summary['stage_rates']['explain'] }}%</span>
                                        <span class="badge text-bg-light">El {{ $summary['stage_rates']['elaborate'] }}%</span>
                                        <span class="badge text-bg-light">Ev {{ $summary['stage_rates']['evaluate'] }}%</span>
                                    </div>
                                    <div class="small text-muted mt-1">Explore activities: {{ $summary['total_explore_activities'] }}</div>
                                </td>
                                <td>
                                    <a href="{{ route('teacher.lessons.show', $summary['lesson']) }}" class="btn btn-sm btn-primary">View report</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">No lessons are assigned to you yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection