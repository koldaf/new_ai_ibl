@extends('layout.app')

@section('title', 'Load Test')
@section('page-title', 'Load Test')

@push('styles')
<style>
    .metric-card {
        border: none;
        border-radius: 12px;
        background: var(--bg-card);
        box-shadow: 0 2px 8px rgba(0,0,0,.07);
        padding: 1.25rem 1.5rem;
    }
    .metric-card .metric-value { font-size: 1.85rem; font-weight: 700; color: var(--text-base); }
    .metric-card .metric-label { font-size: 0.78rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: .05em; }
    .chart-card { border: none; border-radius: 12px; background: var(--bg-card); box-shadow: 0 2px 8px rgba(0,0,0,.07); padding: 1.25rem 1.5rem; }
    .section-title {
        font-size: .7rem; font-weight: 600; text-transform: uppercase;
        letter-spacing: .08em; color: var(--text-muted);
        border-left: 3px solid var(--bo);
        padding-left: .6rem; margin-bottom: 1rem;
    }
    .perf-table { font-size: .82rem; }
    .perf-table th { font-size: .72rem; text-transform: uppercase; letter-spacing: .05em; color: var(--text-muted); font-weight: 600; border-bottom-width: 1px; }
</style>
@endpush

@section('content')
<div class="content-inner">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="mb-0" style="font-family:'DM Serif Display',serif; font-size:1.6rem;">Load Test</h1>
            <p class="text-muted mb-0 small">
                Fires N simulated students at one question simultaneously, each running the real RAG pipeline
                (vector search + Ollama call) as separate OS processes — a genuine concurrency test of this server.
            </p>
        </div>
    </div>

    @if($errors->any())
        <div class="alert alert-danger mb-3">
            <ul class="mb-0 ps-3">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="chart-card mb-4">
        <div class="section-title">Run a Load Test</div>

        @if($lessons->isEmpty())
            <p class="text-muted small mb-0">
                No lessons are ready for RAG queries yet (none have completed embedding processing). Process a lesson's
                embeddings first, then come back here.
            </p>
        @else
            <form method="POST" action="{{ route('admin.load-test.run') }}">
                @csrf

                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="lesson_id" class="form-label small fw-semibold">Lesson</label>
                        <select class="form-select" id="lesson_id" name="lesson_id" required>
                            @foreach($lessons as $lesson)
                                <option value="{{ $lesson->id }}" {{ (int) old('lesson_id', $formLessonId ?? null) === $lesson->id ? 'selected' : '' }}>
                                    {{ $lesson->title }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label for="concurrency" class="form-label small fw-semibold">Simulated users</label>
                        <select class="form-select" id="concurrency" name="concurrency" required>
                            @foreach([5, 10, 20, 30, 50] as $option)
                                <option value="{{ $option }}" {{ (int) old('concurrency', $formConcurrency ?? 10) === $option ? 'selected' : '' }}>
                                    {{ $option }}
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text">Max {{ $maxConcurrency }}.</div>
                    </div>

                    <div class="col-md-6">
                        <label for="question" class="form-label small fw-semibold">Question every simulated user asks</label>
                        <input type="text" class="form-control" id="question" name="question"
                               maxlength="1000" required
                               value="{{ old('question', $formQuestion ?? 'What is the main idea of this lesson?') }}">
                    </div>
                </div>

                <button type="submit" class="btn btn-primary mt-3" id="run-btn" onclick="document.getElementById('run-btn').disabled=true; document.getElementById('run-btn').innerText='Running — this can take a few minutes...'; this.form.submit();">
                    <i class="bi bi-lightning-charge"></i> Run Load Test
                </button>
                <span class="text-muted small ms-2">
                    Higher concurrency on modest CPU-bound hardware can take several minutes. The page will be
                    unresponsive until the batch finishes — this is expected.
                </span>
            </form>
        @endif
    </div>

    @isset($summary)
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <div class="metric-card">
                    <div class="metric-value">{{ $summary['success_count'] }}/{{ $summary['concurrency'] }}</div>
                    <div class="metric-label">Succeeded</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="metric-card">
                    <div class="metric-value">{{ number_format($summary['batch_wall_ms']) }}<small style="font-size:.9rem">ms</small></div>
                    <div class="metric-label">Total batch wall time</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="metric-card">
                    <div class="metric-value">{{ $summary['aggregate_tps'] }}<small style="font-size:.9rem"> t/s</small></div>
                    <div class="metric-label">Aggregate throughput</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="metric-card">
                    <div class="metric-value">{{ $summary['avg_ttft_ms'] ?? '—' }}<small style="font-size:.9rem">ms</small></div>
                    <div class="metric-label">Avg TTFT (max {{ $summary['max_ttft_ms'] ?? '—' }} ms)</div>
                </div>
            </div>
        </div>

        <div class="chart-card">
            <div class="section-title">Per-User Results</div>
            <div class="table-responsive">
                <table class="table table-sm perf-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Simulated User</th>
                            <th>Status</th>
                            <th class="text-end">Prompt tk</th>
                            <th class="text-end">Gen tk</th>
                            <th class="text-end">TTFT</th>
                            <th class="text-end">TPS</th>
                            <th class="text-end">Wall time</th>
                            <th>Error</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($results as $row)
                        <tr>
                            <td>#{{ $row['user'] }}</td>
                            <td>
                                @if($row['ok'])
                                    <span class="badge bg-success">OK</span>
                                @else
                                    <span class="badge bg-danger">FAILED</span>
                                @endif
                            </td>
                            <td class="text-end">{{ $row['prompt_tokens'] ?? '—' }}</td>
                            <td class="text-end">{{ $row['gen_tokens'] ?? '—' }}</td>
                            <td class="text-end">{{ isset($row['ttft_ms']) ? number_format($row['ttft_ms']) . ' ms' : '—' }}</td>
                            <td class="text-end">{{ $row['tps'] ?? '—' }}</td>
                            <td class="text-end">{{ isset($row['wall_ms']) ? number_format($row['wall_ms']) . ' ms' : '—' }}</td>
                            <td class="text-muted small">{{ $row['error'] ?? '' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endisset

</div>
@endsection
