@extends('layout.app')

@section('title', 'Review AI Grading')
@section('page-title', 'Review AI Grading')

@push('styles')
<style>
    .metric-card {
        border: none;
        border-radius: 12px;
        background: var(--bg-card);
        box-shadow: 0 2px 8px rgba(0,0,0,.07);
        padding: 1.1rem 1.4rem;
    }
    .metric-card .metric-value { font-size: 1.7rem; font-weight: 700; color: var(--text-base); }
    .metric-card .metric-label { font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: .05em; }
    .chart-card { border: none; border-radius: 12px; background: var(--bg-card); box-shadow: 0 2px 8px rgba(0,0,0,.07); padding: 1.25rem 1.5rem; }
    .review-table { font-size: .82rem; }
    .review-table th { font-size: .72rem; text-transform: uppercase; letter-spacing: .05em; color: var(--text-muted); font-weight: 600; border-bottom-width: 1px; }
    .class-badge { font-size: .72rem; font-weight: 500; border-radius: 6px; padding: .2em .55em; }
    .class-correct { background: #E8F5E9; color: #1B5E20; }
    .class-partial { background: #FFF8E1; color: #8D6E00; }
    .class-misconception { background: #FFEBEE; color: #B71C1C; }
    .class-off_topic { background: #ECEFF1; color: #37474F; }
    .answer-cell { max-width: 320px; }
</style>
@endpush

@section('content')
<div class="content-inner">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="mb-0" style="font-family:'DM Serif Display',serif; font-size:1.6rem;">Review AI Grading</h1>
            <p class="text-muted mb-0 small">
                Mark whether the AI's classification of each student answer was actually right. This builds the
                verified dataset needed before any fine-tuning is worth attempting.
            </p>
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

    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="metric-card">
                <div class="metric-value">{{ number_format($totalClassified) }}</div>
                <div class="metric-label">Total Classified</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="metric-card">
                <div class="metric-value">{{ number_format($reviewedCount) }}</div>
                <div class="metric-label">Reviewed</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="metric-card">
                <div class="metric-value">{{ number_format($needsReviewCount) }}</div>
                <div class="metric-label">Needs Review</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="metric-card">
                <div class="metric-value">{{ number_format($disagreeCount) }}</div>
                <div class="metric-label">Marked Incorrect</div>
            </div>
        </div>
    </div>

    <div class="chart-card">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="section-title mb-0" style="font-size:.7rem; font-weight:600; text-transform:uppercase; letter-spacing:.08em; color:var(--text-muted); border-left:3px solid var(--bo); padding-left:.6rem;">
                {{ $filter === 'reviewed' ? 'Reviewed' : 'Needs Review' }}
            </div>
            <div class="btn-group btn-group-sm">
                <a href="{{ route('admin.classification-reviews.index', ['filter' => 'unreviewed']) }}"
                   class="btn btn-outline-secondary {{ $filter !== 'reviewed' ? 'active' : '' }}">Needs Review</a>
                <a href="{{ route('admin.classification-reviews.index', ['filter' => 'reviewed']) }}"
                   class="btn btn-outline-secondary {{ $filter === 'reviewed' ? 'active' : '' }}">Reviewed</a>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-sm review-table align-middle mb-0">
                <thead>
                    <tr>
                        <th>When</th>
                        <th>Student / Lesson</th>
                        <th>Question</th>
                        <th>Student Answer</th>
                        <th>AI Feedback</th>
                        <th>AI Verdict</th>
                        <th>Review</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($messages as $message)
                    <tr>
                        <td class="text-muted small">{{ $message->created_at?->format('d M, H:i') }}</td>
                        <td>
                            <div>{{ $message->user->name ?? 'Unknown' }}</div>
                            <div class="text-muted small">{{ $message->lesson->title ?? '—' }} · {{ $message->stage }}</div>
                        </td>
                        <td>&nbsp;</td>
                        <td>{{ \Illuminate\Support\Str::limit($message->question, 160) }}</td>
                        <td class="answer-cell">{{ \Illuminate\Support\Str::limit($message->answer, 160) }}</td>
                        <td>
                            <span class="class-badge class-{{ $message->classification }}">{{ str_replace('_', ' ', $message->classification) }}</span>
                            <div class="text-muted small mt-1">{{ $message->confidence !== null ? round($message->confidence * 100) . '%' : '—' }}</div>
                        </td>
                        <td>
                            @if($message->reviewed_at)
                                <span class="badge {{ $message->review_verdict === 'correct' ? 'bg-success' : 'bg-danger' }}">
                                    {{ $message->review_verdict === 'correct' ? 'Confirmed correct' : 'Marked incorrect' }}
                                </span>
                                @if($message->corrected_classification)
                                    <div class="text-muted small mt-1">Should be: {{ str_replace('_', ' ', $message->corrected_classification) }}</div>
                                @endif
                            @else
                                <form method="POST" action="{{ route('admin.classification-reviews.review', $message) }}" class="d-inline">
                                    @csrf
                                    <input type="hidden" name="verdict" value="correct">
                                    <button type="submit" class="btn btn-sm btn-outline-success">Correct</button>
                                </form>
                                <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="collapse" data-bs-target="#incorrect-{{ $message->id }}">
                                    Incorrect
                                </button>

                                <div class="collapse mt-2" id="incorrect-{{ $message->id }}">
                                    <form method="POST" action="{{ route('admin.classification-reviews.review', $message) }}">
                                        @csrf
                                        <input type="hidden" name="verdict" value="incorrect">
                                        <select name="corrected_classification" class="form-select form-select-sm mb-2" required>
                                            <option value="">Should have been…</option>
                                            @foreach($classificationValues as $value)
                                                <option value="{{ $value }}">{{ str_replace('_', ' ', $value) }}</option>
                                            @endforeach
                                        </select>
                                        <textarea name="notes" class="form-control form-control-sm mb-2" rows="2" placeholder="Optional note (why is this wrong?)"></textarea>
                                        <button type="submit" class="btn btn-sm btn-danger">Save correction</button>
                                    </form>
                                </div>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">Nothing here yet.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-3">
            {{ $messages->links() }}
        </div>
    </div>

</div>
@endsection
