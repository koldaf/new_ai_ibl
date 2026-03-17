@extends('layout.app')

@section('title', $lesson->title)

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="mb-1">{{ $lesson->title }}</h1>
            <p class="text-muted mb-0">Learners tracked: {{ $learnerCount }} • Explore activities: {{ $exploreActivities->count() }}</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('teacher.lessons.export', array_merge(['lesson' => $lesson], array_filter($filters))) }}" class="btn btn-outline-success">
                <i class="bi bi-download me-1"></i>Export CSV
            </a>
            <a href="{{ route('teacher.dashboard') }}" class="btn btn-outline-secondary">Back to dashboard</a>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="stat-card h-100">
                <div class="stat-icon green"><i class="bi bi-check2-circle"></i></div>
                <div>
                    <div class="stat-label">Overall Completion</div>
                    <div class="stat-value">{{ $overallCompletionRate }}%</div>
                </div>
            </div>
        </div>
        <div class="col-md-8">
            <div class="card h-100">
                <div class="card-body">
                    <div class="small text-muted mb-2">Stage Completion Rates</div>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="badge text-bg-light">Engage {{ $stagePercentages['engage'] }}%</span>
                        <span class="badge text-bg-light">Explore {{ $stagePercentages['explore'] }}%</span>
                        <span class="badge text-bg-light">Explain {{ $stagePercentages['explain'] }}%</span>
                        <span class="badge text-bg-light">Elaborate {{ $stagePercentages['elaborate'] }}%</span>
                        <span class="badge text-bg-light">Evaluate {{ $stagePercentages['evaluate'] }}%</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">Explore Activity Checklist</div>
        <div class="card-body">
            @if($exploreActivities->isEmpty())
                <p class="text-muted mb-0">This lesson has no tracked Explore activities.</p>
            @else
                <ul class="mb-0">
                    @foreach($exploreActivities as $activity)
                        <li>{{ $activity['title'] }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('teacher.lessons.show', $lesson) }}" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label for="learner" class="form-label">Learner</label>
                    <input type="text" id="learner" name="learner" class="form-control" value="{{ $filters['learner'] }}" placeholder="Search by learner name">
                </div>
                <div class="col-md-3">
                    <label for="status" class="form-label">Status</label>
                    <select id="status" name="status" class="form-select">
                        <option value="">All</option>
                        <option value="completed" {{ $filters['status'] === 'completed' ? 'selected' : '' }}>Completed</option>
                        <option value="in_progress" {{ $filters['status'] === 'in_progress' ? 'selected' : '' }}>In progress</option>
                        <option value="not_started" {{ $filters['status'] === 'not_started' ? 'selected' : '' }}>Not started</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="sort" class="form-label">Sort by</label>
                    <select id="sort" name="sort" class="form-select">
                        <option value="" {{ $filters['sort'] === '' ? 'selected' : '' }}>Latest update</option>
                        <option value="name_asc" {{ $filters['sort'] === 'name_asc' ? 'selected' : '' }}>Learner name</option>
                        <option value="progress_desc" {{ $filters['sort'] === 'progress_desc' ? 'selected' : '' }}>Highest progress</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary w-100">Apply</button>
                    <a href="{{ route('teacher.lessons.show', $lesson) }}" class="btn btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">Learner Progress</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>Learner</th>
                            <th>Overall Progress</th>
                            <th>Stage Status</th>
                            <th>Explore Activity Count</th>
                            <th>Updated</th>
                            <th>Lesson Complete</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($studentRows as $row)
                            <tr>
                                <td class="fw-semibold">{{ $row['user']->name }}</td>
                                <td style="min-width: 170px;">
                                    <div class="small fw-semibold mb-1">{{ $row['overall_progress'] }}%</div>
                                    <div class="progress" style="height: 8px;">
                                        <div class="progress-bar" role="progressbar" style="width: {{ $row['overall_progress'] }}%;" aria-valuenow="{{ $row['overall_progress'] }}" aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                </td>
                                <td style="min-width: 250px;">
                                    <div class="d-flex flex-wrap gap-1 small">
                                        <span class="badge {{ $row['progress']->engage_completed ? 'text-bg-success' : 'text-bg-secondary' }}">Engage</span>
                                        <span class="badge {{ $row['progress']->explore_completed ? 'text-bg-success' : 'text-bg-secondary' }}">Explore</span>
                                        <span class="badge {{ $row['progress']->explain_completed ? 'text-bg-success' : 'text-bg-secondary' }}">Explain</span>
                                        <span class="badge {{ $row['progress']->elaborate_completed ? 'text-bg-success' : 'text-bg-secondary' }}">Elaborate</span>
                                        <span class="badge {{ $row['progress']->evaluate_completed ? 'text-bg-success' : 'text-bg-secondary' }}">Evaluate</span>
                                    </div>
                                </td>
                                <td>
                                    {{ $row['explore_completed_count'] }} / {{ $row['explore_total_count'] }}
                                </td>
                                <td class="text-muted small">{{ optional($row['progress']->updated_at)->diffForHumans() }}</td>
                                <td>
                                    <span class="badge {{ $row['progress']->completed ? 'text-bg-success' : 'text-bg-warning' }}">
                                        {{ $row['progress']->completed ? 'Yes' : 'No' }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">No learner records match the selected filters.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if(method_exists($studentRows, 'links'))
            <div class="card-footer bg-white">
                {{ $studentRows->links() }}
            </div>
        @endif
    </div>
</div>
@endsection