@extends('layout.app')

@section('content')
<div class="container-fluid">

    {{-- Breadcrumb --}}
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('teacher.dashboard') }}">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="{{ route('teacher.lessons.show', $lesson) }}">{{ $lesson->title }}</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ $student->name }}</li>
        </ol>
    </nav>

    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 mb-1">{{ $student->name }}</h1>
            <p class="text-muted mb-0">Activity for <strong>{{ $lesson->title }}</strong></p>
        </div>
        <a href="{{ route('teacher.lessons.show', $lesson) }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back to lesson
        </a>
    </div>

    {{-- Overall progress bar --}}
    @php
        $completedCount = collect([
            $progress->engage_completed,
            $progress->explore_completed,
            $progress->explain_completed,
            $progress->elaborate_completed,
            $progress->evaluate_completed,
        ])->filter()->count();
        $overallPct = round(($completedCount / 5) * 100);
    @endphp
    <div class="card mb-4 shadow-sm border-0">
        <div class="card-body">
            <div class="d-flex justify-content-between mb-1">
                <span class="fw-semibold">Overall Lesson Progress</span>
                <span>{{ $completedCount }}/5 stages &mdash; {{ $overallPct }}%</span>
            </div>
            <div class="progress" style="height: 10px;">
                <div class="progress-bar bg-success" style="width: {{ $overallPct }}%"></div>
            </div>
        </div>
    </div>

    <div class="card mb-4 shadow-sm border-0">
        <div class="card-header bg-light fw-semibold">Bloom Question Analytics (This Learner, This Lesson)</div>
        <div class="card-body">
            @php
                $learnerBloomTotal = $learnerBloomStats->sum('count');
            @endphp
            @if($learnerBloomTotal === 0)
                <p class="text-muted mb-0">No Bloom-classified learner questions recorded yet.</p>
            @else
                <div class="d-flex justify-content-end mb-3">
                    <div class="btn-group btn-group-sm" role="group" aria-label="Learner bloom view mode">
                        <button type="button" class="btn btn-primary" id="learner-bloom-toggle-chart">Chart</button>
                        <button type="button" class="btn btn-outline-primary" id="learner-bloom-toggle-table">Table</button>
                    </div>
                </div>

                <div id="learner-bloom-chart-wrap" class="mb-3" style="height: 320px;">
                    <canvas id="learnerBloomClusteredChart"></canvas>
                </div>

                <div class="d-flex flex-wrap gap-2 mb-3">
                    @foreach($learnerBloomStats as $stat)
                        @if($stat['count'] > 0)
                            <span class="badge text-bg-light border">
                                {{ ucfirst($stat['level']) }}: {{ $stat['count'] }}
                                @if(!is_null($stat['avg_confidence']))
                                    ({{ (int) round($stat['avg_confidence'] * 100) }}%)
                                @endif
                            </span>
                        @endif
                    @endforeach
                </div>

                <div id="learner-bloom-table-wrap" class="table-responsive d-none">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th>Stage</th>
                                <th class="text-end">Remember</th>
                                <th class="text-end">Understand</th>
                                <th class="text-end">Apply</th>
                                <th class="text-end">Analyze</th>
                                <th class="text-end">Evaluate</th>
                                <th class="text-end">Create</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($stages as $stage)
                                @php
                                    $stageBloom = $learnerBloomByStage->get($stage, collect());
                                @endphp
                                <tr>
                                    <td>{{ ucfirst($stage) }}</td>
                                    <td class="text-end">{{ $stageBloom->get('remember', 0) }}</td>
                                    <td class="text-end">{{ $stageBloom->get('understand', 0) }}</td>
                                    <td class="text-end">{{ $stageBloom->get('apply', 0) }}</td>
                                    <td class="text-end">{{ $stageBloom->get('analyze', 0) }}</td>
                                    <td class="text-end">{{ $stageBloom->get('evaluate', 0) }}</td>
                                    <td class="text-end">{{ $stageBloom->get('create', 0) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <div class="card mb-4 shadow-sm border-0">
        <div class="card-header bg-light fw-semibold">Inquiry Phase Analytics And Reflection</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Stage</th>
                            <th class="text-end">Time (min)</th>
                            <th class="text-end">Questions</th>
                            <th class="text-end">Evidence</th>
                            <th class="text-end">Evaluation Final Score</th>
                            <th class="text-end">Auto Score</th>
                            <th class="text-end">Teacher Score</th>
                            <th class="text-end">Final Score</th>
                            <th style="min-width: 280px;">Reflection</th>
                            <th style="min-width: 220px;">Override</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($stages as $stage)
                            @php $phase = $phaseAnalyticsByStage->get($stage); @endphp
                            <tr>
                                <td class="fw-semibold">{{ ucfirst($stage) }}</td>
                                <td class="text-end">{{ number_format(((int) ($phase?->time_spent_seconds ?? 0)) / 60, 1) }}</td>
                                <td class="text-end">{{ (int) ($phase?->questions_generated ?? 0) }}</td>
                                <td class="text-end">{{ (int) ($phase?->evidence_sources_consulted ?? 0) }}</td>
                                <td class="text-end">{{ is_null($phase?->evaluation_final_score) ? '—' : $phase->evaluation_final_score }}</td>
                                <td class="text-end">{{ is_null($phase?->reflection_quality_auto) ? '—' : $phase->reflection_quality_auto }}</td>
                                <td class="text-end" id="teacher-score-{{ $stage }}">{{ is_null($phase?->reflection_quality_teacher) ? '—' : $phase->reflection_quality_teacher }}</td>
                                <td class="text-end" id="final-score-{{ $stage }}">{{ is_null($phase?->reflection_quality_final) ? '—' : $phase->reflection_quality_final }}</td>
                                <td class="small">
                                    @if(filled($phase?->reflection_text))
                                        {!! nl2br(e($phase->reflection_text)) !!}
                                    @else
                                        <span class="text-muted">No reflection submitted</span>
                                    @endif
                                </td>
                                <td>
                                    <form class="teacher-reflection-score-form" data-stage="{{ $stage }}">
                                        @csrf
                                        <div class="input-group input-group-sm">
                                            <input
                                                type="number"
                                                class="form-control"
                                                name="reflection_quality_teacher"
                                                min="0"
                                                max="100"
                                                step="1"
                                                value="{{ $phase?->reflection_quality_teacher }}"
                                                placeholder="0-100"
                                            >
                                            <button class="btn btn-outline-primary" type="submit">Save</button>
                                        </div>
                                        <div class="small text-muted mt-1" id="override-status-{{ $stage }}"></div>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Stage tabs --}}
    <ul class="nav nav-tabs" id="activityTabs" role="tablist">
        @foreach($stages as $i => $stage)
        <li class="nav-item" role="presentation">
            <button class="nav-link {{ $i === 0 ? 'active' : '' }} {{ $progress->{$stage.'_completed'} ? 'bg-success text-white' : '' }}"
                    id="{{ $stage }}-tab"
                    data-bs-toggle="tab"
                    data-bs-target="#activity-{{ $stage }}"
                    type="button"
                    role="tab">
                {{ ucfirst($stage) }}
                @if($progress->{$stage.'_completed'})
                    <i class="fas fa-check-circle ms-1"></i>
                @endif
            </button>
        </li>
        @endforeach
    </ul>

    <div class="tab-content p-3 border border-top-0 bg-white" id="activityTabsContent">

        @foreach($stages as $i => $stage)
        <div class="tab-pane fade {{ $i === 0 ? 'show active' : '' }}"
             id="activity-{{ $stage }}"
             role="tabpanel">

            <div class="d-flex align-items-center gap-2 mb-3 mt-2">
                <h5 class="mb-0">{{ ucfirst($stage) }} Stage</h5>
                @if($progress->{$stage.'_completed'})
                    <span class="badge bg-success">Completed</span>
                @else
                    <span class="badge bg-warning text-dark">In progress</span>
                @endif
            </div>

            {{-- Engage MCQ attempt (only for engage) --}}
            @if($stage === 'engage' && $engageMcqAttempt)
                <div class="card mb-4 border-0 shadow-sm">
                    <div class="card-header bg-light fw-semibold">MCQ Checkpoint Attempt</div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <span class="text-muted small">Selected option</span>
                                <div class="fw-semibold">{{ strtoupper($engageMcqAttempt->selected_option) }}</div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted small">Result</span>
                                <div>
                                    @if($engageMcqAttempt->is_correct)
                                        <span class="badge bg-success">Correct</span>
                                    @else
                                        <span class="badge bg-danger">Incorrect</span>
                                    @endif
                                </div>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted small">Submitted</span>
                                <div class="small">{{ $engageMcqAttempt->created_at?->format('d M Y H:i') }}</div>
                            </div>
                        </div>
                        @if($engageMcqAttempt->resolved_feedback)
                            <div class="alert alert-light border mt-3 mb-0">
                                <span class="text-muted small">Feedback</span>
                                <div>{!! nl2br(e($engageMcqAttempt->resolved_feedback)) !!}</div>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            {{-- AI Chat transcript for this stage --}}
            @php $messages = $chatByStage->get($stage, collect()); @endphp
            @if($messages->isNotEmpty())
                <div class="card mb-4 border-0 shadow-sm">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                        <span class="fw-semibold">
                            @if(in_array($stage, ['explore','explain','elaborate']))
                                Checkpoint Transcript
                            @else
                                AI Discussion Transcript
                            @endif
                        </span>
                        <span class="badge bg-secondary">{{ $messages->count() }} message{{ $messages->count() !== 1 ? 's' : '' }}</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="chat-transcript p-3" style="max-height: 500px; overflow-y: auto;">
                            @foreach($messages as $msg)
                                @if($msg->question && $msg->question !== '__engage_start__' && $msg->question !== '__checkpoint_start__')
                                    <div class="d-flex justify-content-end mb-2">
                                        <div class="chat-bubble student-bubble bg-primary text-white rounded p-2 px-3" style="max-width:75%;">
                                            {!! nl2br(e($msg->question)) !!}
                                        </div>
                                    </div>
                                @endif

                                <div class="d-flex justify-content-start mb-3">
                                    <div class="chat-bubble ai-bubble border bg-white rounded p-2 px-3" style="max-width:75%;">
                                        <div>{!! nl2br(e($msg->answer)) !!}</div>
                                        @if($msg->classification)
                                            <div class="mt-2 pt-2 border-top d-flex flex-wrap gap-2">
                                                <span class="badge bg-light text-dark border">
                                                    <i class="fas fa-tag me-1"></i>
                                                    {{ ucfirst(str_replace('_', ' ', $msg->classification)) }}
                                                    @if(!is_null($msg->confidence))
                                                        ({{ (int) round($msg->confidence * 100) }}%)
                                                    @endif
                                                </span>
                                                @if($msg->engage_status)
                                                    <span class="badge {{ $msg->engage_status === 'complete' ? 'bg-success' : ($msg->engage_status === 'review_needed' ? 'bg-warning text-dark' : 'bg-secondary') }}">
                                                        {{ ucfirst(str_replace('_', ' ', $msg->engage_status)) }}
                                                    </span>
                                                @endif
                                            </div>
                                        @endif
                                        @if($msg->bloom_level)
                                            <div class="mt-2 d-flex flex-wrap gap-2">
                                                <span class="badge bg-info text-dark">
                                                    Bloom: {{ ucfirst($msg->bloom_level) }}
                                                    @if(!is_null($msg->bloom_confidence))
                                                        ({{ (int) round($msg->bloom_confidence * 100) }}%)
                                                    @endif
                                                </span>
                                            </div>
                                        @endif
                                        @if($msg->feedback_text)
                                            <div class="mt-1 text-muted small fst-italic">{{ $msg->feedback_text }}</div>
                                        @endif
                                        <div class="text-muted" style="font-size:.7rem; margin-top:4px;">{{ $msg->created_at?->format('H:i, d M Y') }}</div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @else
                <div class="alert alert-light border">
                    <i class="fas fa-comment-slash me-2 text-muted"></i>
                    No AI interaction recorded for this stage.
                </div>
            @endif

            {{-- Evaluate: quiz attempts --}}
            @if($stage === 'evaluate' && $quizAttempts->isNotEmpty())
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-light fw-semibold">Quiz Attempts</div>
                    <div class="card-body p-0">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Question</th>
                                    <th>Selected</th>
                                    <th>Correct?</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($quizAttempts as $attempt)
                                    <tr>
                                        <td class="text-muted small">{{ $loop->iteration }}</td>
                                        <td>{{ $attempt->question?->question ?? '—' }}</td>
                                        <td><strong>{{ strtoupper($attempt->selected_option) }}</strong></td>
                                        <td>
                                            @if($attempt->is_correct)
                                                <span class="badge bg-success">Yes</span>
                                            @else
                                                <span class="badge bg-danger">No</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

        </div>
        @endforeach

    </div>
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<style>
    .chat-bubble { font-size: .93rem; line-height: 1.5; }
    .chat-transcript { scroll-behavior: smooth; }
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const chartWrap = document.getElementById('learner-bloom-chart-wrap');
    const tableWrap = document.getElementById('learner-bloom-table-wrap');
    const chartBtn = document.getElementById('learner-bloom-toggle-chart');
    const tableBtn = document.getElementById('learner-bloom-toggle-table');

    function activateLearnerBloomView(mode) {
        if (!chartWrap || !tableWrap || !chartBtn || !tableBtn) {
            return;
        }

        const chartMode = mode === 'chart';
        chartWrap.classList.toggle('d-none', !chartMode);
        tableWrap.classList.toggle('d-none', chartMode);
        chartBtn.classList.toggle('btn-primary', chartMode);
        chartBtn.classList.toggle('btn-outline-primary', !chartMode);
        tableBtn.classList.toggle('btn-primary', !chartMode);
        tableBtn.classList.toggle('btn-outline-primary', chartMode);
    }

    chartBtn?.addEventListener('click', function () {
        activateLearnerBloomView('chart');
    });

    tableBtn?.addEventListener('click', function () {
        activateLearnerBloomView('table');
    });

    activateLearnerBloomView('chart');

    document.querySelectorAll('.teacher-reflection-score-form').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            event.preventDefault();

            const stage = form.dataset.stage;
            const scoreInput = form.querySelector('input[name="reflection_quality_teacher"]');
            const status = document.getElementById('override-status-' + stage);
            const value = Number(scoreInput.value);

            if (Number.isNaN(value) || value < 0 || value > 100) {
                status.textContent = 'Enter a score between 0 and 100.';
                return;
            }

            status.textContent = 'Saving...';

            const payload = new FormData();
            payload.append('_token', '{{ csrf_token() }}');
            payload.append('reflection_quality_teacher', String(Math.round(value)));

            fetch('{{ route("teacher.lessons.student-reflection-score.update", ["lesson" => $lesson->id, "student" => $student->id, "stage" => "_stage_"]) }}'.replace('_stage_', stage), {
                method: 'POST',
                body: payload,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('Unable to save score.');
                    }

                    return response.json();
                })
                .then(function (json) {
                    document.getElementById('teacher-score-' + stage).textContent = json.reflection_quality_teacher;
                    document.getElementById('final-score-' + stage).textContent = json.reflection_quality_final;
                    status.textContent = 'Saved.';
                })
                .catch(function () {
                    status.textContent = 'Save failed. Try again.';
                });
        });
    });

    const chartEl = document.getElementById('learnerBloomClusteredChart');

    if (!chartEl || typeof window.Chart === 'undefined') {
        return;
    }

    const labels = ['Remember', 'Understand', 'Apply', 'Analyze', 'Evaluate', 'Create'];
    const stageOrder = ['engage', 'explore', 'explain', 'elaborate', 'evaluate'];
    const stageLabel = {
        engage: 'Engage',
        explore: 'Explore',
        explain: 'Explain',
        elaborate: 'Elaborate',
        evaluate: 'Evaluate'
    };
    const stageColor = {
        engage: '#0d6efd',
        explore: '#198754',
        explain: '#fd7e14',
        elaborate: '#6f42c1',
        evaluate: '#dc3545'
    };

    const bloomByStage = @json($learnerBloomByStage);

    const datasets = stageOrder.map(function (stage) {
        const source = bloomByStage[stage] || {};

        return {
            label: stageLabel[stage],
            data: [
                source.remember || 0,
                source.understand || 0,
                source.apply || 0,
                source.analyze || 0,
                source.evaluate || 0,
                source.create || 0,
            ],
            backgroundColor: stageColor[stage],
            borderRadius: 4,
            maxBarThickness: 28,
        };
    });

    new window.Chart(chartEl, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: datasets,
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    },
                    title: {
                        display: true,
                        text: 'Question Count'
                    }
                }
            }
        }
    });
});
</script>
@endpush
