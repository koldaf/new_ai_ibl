@extends('layout.app')

@section('content')
<div class="container">
    <h1>My Lessons</h1>

    <div class="row">
        @foreach($lessons as $lesson)
        <div class="col-md-4 mb-4">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title">{{ $lesson->title }}</h5>
                    <p class="card-text">{!! Str::limit($lesson->description, 100) !!}</p>
                    @php
                        $progress = $lesson->progress->first();
                    @endphp
                    @if($progress)
                        <p class="mb-2">
                            Progress:
                            @if($progress->completed)
                                <span class="badge bg-success">Completed</span>
                            @else
                                @php
                                    $completedStages = 0;
                                    if($progress->engage_completed) $completedStages++;
                                    if($progress->explore_completed) $completedStages++;
                                    if($progress->explain_completed) $completedStages++;
                                    if($progress->elaborate_completed) $completedStages++;
                                    if($progress->evaluate_completed) $completedStages++;
                                    $percentage = round(($completedStages / 5) * 100);
                                @endphp
                                <div class="progress" style="height: 10px;">
                                    <div class="progress-bar" role="progressbar" style="width: {{ $percentage }}%;" aria-valuenow="{{ $percentage }}" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                                <small>{{ $percentage }}% complete</small>
                            @endif
                        </p>
                    @else
                        <p class="text-muted">Not started</p>
                    @endif
                </div>
                <div class="card-footer">
                    <a href="{{ route('student.lessons.show', $lesson) }}" class="btn btn-primary">Start Lesson</a>
                </div>
            </div>
        </div>
        @endforeach
    </div>

    {{ $lessons->links() }}
</div>
@endsection