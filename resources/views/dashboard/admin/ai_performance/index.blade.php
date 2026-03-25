@extends('layout.app')

@section('page-title', 'AI Monitor')

@push('styles')
<style>
    /* ── Metric Cards ─────────────────────────────────── */
    .metric-card {
        border: none;
        border-radius: 12px;
        background: var(--bg-card);
        box-shadow: 0 2px 8px rgba(0,0,0,.07);
        padding: 1.25rem 1.5rem;
        position: relative;
        overflow: hidden;
    }
    .metric-card .metric-icon {
        width: 48px; height: 48px;
        border-radius: 12px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.4rem;
        flex-shrink: 0;
    }
    .metric-card .metric-value { font-size: 1.85rem; font-weight: 700; color: var(--text-base); }
    .metric-card .metric-label { font-size: 0.78rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: .05em; }
    .metric-card .metric-sub   { font-size: 0.80rem; color: var(--text-muted); margin-top: .15rem; }

    /* ── Resource bars ────────────────────────────────── */
    .resource-bar  { height: 10px; border-radius: 6px; }
    .resource-label{ font-size: 0.82rem; color: var(--text-muted); }
    .resource-value{ font-size: 0.85rem; font-weight: 600; color: var(--text-base); }

    /* ── Section titles ───────────────────────────────── */
    .section-title {
        font-size: .7rem; font-weight: 600; text-transform: uppercase;
        letter-spacing: .08em; color: var(--text-muted);
        border-left: 3px solid var(--bo);
        padding-left: .6rem; margin-bottom: 1rem;
    }

    /* ── Table tweaks ─────────────────────────────────── */
    .perf-table { font-size: .82rem; }
    .perf-table th { font-size: .72rem; text-transform: uppercase; letter-spacing: .05em; color: var(--text-muted); font-weight: 600; border-bottom-width: 1px; }
    .caller-badge { font-size: .72rem; font-weight: 500; border-radius: 6px; padding: .2em .55em; }
    .caller-rag_query      { background: #FFF3E0; color: #E65100; }
    .caller-engage_decision{ background: #E8F5E9; color: #1B5E20; }
    .caller-general_classify{ background: #E3F2FD; color: #0D47A1; }
    .caller-stream_query   { background: #F3E5F5; color: #4A148C; }

    /* ── Charts ───────────────────────────────────────── */
    .chart-card { border: none; border-radius: 12px; background: var(--bg-card); box-shadow: 0 2px 8px rgba(0,0,0,.07); padding: 1.25rem 1.5rem; }

    /* ── Live badge ───────────────────────────────────── */
    .live-dot { width: 8px; height: 8px; border-radius: 50%; background: #22C55E; display: inline-block; animation: pulse 2s infinite; }
    @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.35} }

    /* ── Model pill ───────────────────────────────────── */
    .model-pill {
        display: inline-flex; align-items: center; gap: .4rem;
        background: rgba(204,85,0,.1); color: var(--bo);
        border-radius: 20px; padding: .3rem .8rem;
        font-size: .8rem; font-weight: 500;
    }
    .ollama-model-row { font-size: .83rem; }
</style>
@endpush

@section('content')
<div class="content-inner">

    {{-- ══════════════════════════════════════════════════════════
         TOP: Summary cards
    ══════════════════════════════════════════════════════════ --}}
    <div class="row g-3 mb-4">

        {{-- Today's calls --}}
        <div class="col-6 col-md-3">
            <div class="metric-card d-flex align-items-center gap-3">
                <div class="metric-icon" style="background:rgba(204,85,0,.1); color:var(--bo);">
                    <i class="bi bi-lightning-charge"></i>
                </div>
                <div>
                    <div class="metric-value">{{ number_format($todayCount) }}</div>
                    <div class="metric-label">Queries Today</div>
                </div>
            </div>
        </div>

        {{-- Avg response time --}}
        <div class="col-6 col-md-3">
            <div class="metric-card d-flex align-items-center gap-3">
                <div class="metric-icon" style="background:rgba(59,130,246,.1); color:#3B82F6;">
                    <i class="bi bi-hourglass-split"></i>
                </div>
                <div>
                    <div class="metric-value">{{ $avgResponse }}<small style="font-size:.9rem">ms</small></div>
                    <div class="metric-label">Avg Response Time</div>
                    <div class="metric-sub">wall clock — today</div>
                </div>
            </div>
        </div>

        {{-- Avg TPS --}}
        <div class="col-6 col-md-3">
            <div class="metric-card d-flex align-items-center gap-3">
                <div class="metric-icon" style="background:rgba(34,197,94,.1); color:#16A34A;">
                    <i class="bi bi-speedometer2"></i>
                </div>
                <div>
                    <div class="metric-value">{{ $avgTps }}<small style="font-size:.9rem"> t/s</small></div>
                    <div class="metric-label">Avg Tokens / Sec</div>
                    <div class="metric-sub">today — all callers</div>
                </div>
            </div>
        </div>

        {{-- Avg TTFT --}}
        <div class="col-6 col-md-3">
            <div class="metric-card d-flex align-items-center gap-3">
                <div class="metric-icon" style="background:rgba(168,85,247,.1); color:#7C3AED;">
                    <i class="bi bi-stopwatch"></i>
                </div>
                <div>
                    <div class="metric-value">{{ $avgTtft }}<small style="font-size:.9rem">ms</small></div>
                    <div class="metric-label">Avg TTFT</div>
                    <div class="metric-sub">time-to-first-token</div>
                </div>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════
         MIDDLE ROW: Live system stats + Caller breakdown
    ══════════════════════════════════════════════════════════ --}}
    <div class="row g-3 mb-4">

        {{-- Live System Resources ─────────────────────────── --}}
        <div class="col-12 col-lg-5">
            <div class="chart-card h-100">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div class="section-title mb-0">System Resources</div>
                    <span class="d-flex align-items-center gap-2" style="font-size:.78rem; color:var(--text-muted);">
                        <span class="live-dot"></span> Live · refreshes every 30 s
                    </span>
                </div>

                {{-- CPU --}}
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="resource-label"><i class="bi bi-cpu me-1"></i>CPU Usage</span>
                        <span class="resource-value" id="cpu-pct">—</span>
                    </div>
                    <div class="progress resource-bar">
                        <div id="cpu-bar" class="progress-bar" style="background:var(--bo); width:0%; transition:width .5s"></div>
                    </div>
                    <div style="font-size:.75rem; color:var(--text-muted); margin-top:.25rem;">
                        Load avg (1/5/15 min): <span id="cpu-load">…</span>
                        &nbsp;·&nbsp; <span id="cpu-cores">—</span> cores
                    </div>
                </div>

                {{-- RAM --}}
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="resource-label"><i class="bi bi-memory me-1"></i>RAM</span>
                        <span class="resource-value" id="ram-pct">—</span>
                    </div>
                    <div class="progress resource-bar">
                        <div id="ram-bar" class="progress-bar" style="background:#3B82F6; width:0%; transition:width .5s"></div>
                    </div>
                    <div style="font-size:.75rem; color:var(--text-muted); margin-top:.25rem;" id="ram-detail">…</div>
                </div>

                {{-- Swap --}}
                <div class="mb-3" id="swap-block" style="display:none">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="resource-label"><i class="bi bi-hdd me-1"></i>Swap</span>
                        <span class="resource-value" id="swap-pct">—</span>
                    </div>
                    <div class="progress resource-bar">
                        <div id="swap-bar" class="progress-bar" style="background:#F59E0B; width:0%; transition:width .5s"></div>
                    </div>
                    <div style="font-size:.75rem; color:var(--text-muted); margin-top:.25rem;" id="swap-detail">…</div>
                </div>

                <hr class="my-3">

                {{-- Ollama Models --}}
                <div class="section-title">Configured LLM</div>
                <div class="mb-2">
                    <span class="model-pill">
                        <i class="bi bi-robot"></i>
                        <span id="config-model">…</span>
                    </span>
                    &nbsp;<small class="text-muted" id="ollama-url-badge">…</small>
                </div>

                <div class="section-title mt-3">Loaded in Ollama (in-memory)</div>
                <div id="loaded-models-list">
                    <span class="text-muted" style="font-size:.82rem">Checking…</span>
                </div>

                <div class="section-title mt-3">Available Models (pulled)</div>
                <div id="available-models-list">
                    <span class="text-muted" style="font-size:.82rem">Checking…</span>
                </div>
            </div>
        </div>

        {{-- Caller breakdown ──────────────────────────────── --}}
        <div class="col-12 col-lg-7">
            <div class="chart-card h-100">
                <div class="section-title">Per-Caller Performance Summary</div>
                <div class="table-responsive">
                    <table class="table table-sm perf-table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Caller</th>
                                <th class="text-end">Calls</th>
                                <th class="text-end">Avg Time</th>
                                <th class="text-end">Avg TPS</th>
                                <th class="text-end">Avg TTFT</th>
                                <th class="text-end">Avg Tokens</th>
                                <th class="text-end">Errors</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($callerStats as $cs)
                            <tr>
                                <td>
                                    <span class="caller-badge caller-{{ $cs->caller }}">
                                        {{ str_replace('_', ' ', $cs->caller) }}
                                    </span>
                                </td>
                                <td class="text-end">{{ number_format($cs->total_calls) }}</td>
                                <td class="text-end">{{ $cs->avg_response_ms }} ms</td>
                                <td class="text-end">{{ $cs->avg_tps ?? '—' }}</td>
                                <td class="text-end">{{ $cs->avg_ttft_ms ? $cs->avg_ttft_ms . ' ms' : '—' }}</td>
                                <td class="text-end">{{ $cs->avg_tokens ?? '—' }}</td>
                                <td class="text-end">
                                    @if($cs->error_count > 0)
                                        <span class="badge bg-danger">{{ $cs->error_count }}</span>
                                    @else
                                        <span class="text-muted">0</span>
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    No AI calls recorded yet. Make some queries to start seeing data.
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════
         CHARTS ROW
    ══════════════════════════════════════════════════════════ --}}
    <div class="row g-3 mb-4">
        <div class="col-12 col-lg-8">
            <div class="chart-card">
                <div class="section-title">Response Time &amp; TPS — Last 24 Hours</div>
                <canvas id="timelineChart" height="100"></canvas>
            </div>
        </div>
        <div class="col-12 col-lg-4">
            <div class="chart-card">
                <div class="section-title">Avg TPS by Caller</div>
                <canvas id="tpsBarChart" height="200"></canvas>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════
         DETAIL LOG TABLE
    ══════════════════════════════════════════════════════════ --}}
    <div class="chart-card mb-4">
        <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
            <div class="section-title mb-0">Recent Query Log</div>
            <span style="font-size:.78rem; color:var(--text-muted);">Showing {{ $logs->firstItem() }}–{{ $logs->lastItem() }} of {{ $logs->total() }} entries</span>
        </div>
        <div class="table-responsive">
            <table class="table table-sm perf-table align-middle">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Caller</th>
                        <th>Stage</th>
                        <th>Model</th>
                        <th class="text-end">Response</th>
                        <th class="text-end">TTFT</th>
                        <th class="text-end">TPS</th>
                        <th class="text-end">Prompt tk</th>
                        <th class="text-end">Gen tk</th>
                        <th class="text-end">Load</th>
                        <th class="text-end">Chunks</th>
                        <th>Question</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                    <tr>
                        <td style="white-space:nowrap; font-size:.75rem;">
                            {{ $log->created_at->format('d M H:i:s') }}
                        </td>
                        <td>
                            <span class="caller-badge caller-{{ $log->caller }}">
                                {{ str_replace('_', ' ', $log->caller) }}
                            </span>
                        </td>
                        <td>{{ $log->stage ?? '—' }}</td>
                        <td style="font-size:.75rem; max-width:110px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                            {{ $log->model_name }}
                        </td>
                        <td class="text-end">{{ $log->response_time_ms }} ms</td>
                        <td class="text-end">{{ $log->ttft_ms ? round($log->ttft_ms) . ' ms' : '—' }}</td>
                        <td class="text-end">{{ $log->tokens_per_second ?? '—' }}</td>
                        <td class="text-end">{{ $log->prompt_tokens ?? '—' }}</td>
                        <td class="text-end">{{ $log->tokens_generated ?? '—' }}</td>
                        <td class="text-end" style="font-size:.75rem;">
                            {{ $log->load_duration_ms ? round($log->load_duration_ms) . ' ms' : '—' }}
                        </td>
                        <td class="text-end">{{ $log->context_chunks ?? '—' }}</td>
                        <td style="max-width:200px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-size:.78rem;"
                            title="{{ $log->question_snippet }}">
                            {{ $log->question_snippet ?? '—' }}
                        </td>
                        <td>
                            @if($log->error)
                                <span class="badge bg-danger" title="{{ $log->error }}">Error</span>
                            @else
                                <span class="badge bg-success">OK</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="13" class="text-center text-muted py-5">
                            <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                            No AI calls recorded yet.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        <div class="d-flex justify-content-end mt-3">
            {{ $logs->links() }}
        </div>
    </div>

</div>{{-- /content-inner --}}
@endsection

@push('scripts')
{{-- Chart.js --}}
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>

<script>
// ── Helpers ──────────────────────────────────────────────────────────────────
const isDark = () => document.documentElement.getAttribute('data-bs-theme') === 'dark';
const textColor = () => isDark() ? '#CBD5E1' : '#475569';
const gridColor = () => isDark() ? 'rgba(255,255,255,.06)' : 'rgba(0,0,0,.06)';

// ── Chart instances ───────────────────────────────────────────────────────────
let timelineChart, tpsBarChart;

function buildCharts(data) {
    const hourlyLabels   = data.hourly.map(r => r.hour.slice(11,16)); // HH:mm
    const responseTimes  = data.hourly.map(r => r.avg_response_ms);
    const tpsVals        = data.hourly.map(r => r.avg_tps);
    const callCounts     = data.hourly.map(r => r.call_count);

    // ── Timeline chart (dual y-axis) ──────────────────────────────────────────
    if (timelineChart) timelineChart.destroy();
    timelineChart = new Chart(document.getElementById('timelineChart'), {
        data: {
            labels: hourlyLabels,
            datasets: [
                {
                    type: 'line',
                    label: 'Avg Response (ms)',
                    data: responseTimes,
                    borderColor: '#CC5500',
                    backgroundColor: 'rgba(204,85,0,.12)',
                    fill: true,
                    tension: .35,
                    yAxisID: 'yMs',
                    pointRadius: 3,
                },
                {
                    type: 'line',
                    label: 'Avg TPS',
                    data: tpsVals,
                    borderColor: '#22C55E',
                    backgroundColor: 'rgba(34,197,94,.1)',
                    fill: false,
                    tension: .35,
                    yAxisID: 'yTps',
                    pointRadius: 3,
                    borderDash: [5, 3],
                },
                {
                    type: 'bar',
                    label: 'Calls',
                    data: callCounts,
                    backgroundColor: 'rgba(59,130,246,.25)',
                    yAxisID: 'yCalls',
                    borderRadius: 4,
                    maxBarThickness: 28,
                },
            ]
        },
        options: {
            responsive: true,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { labels: { color: textColor(), boxWidth: 12, font: { size: 11 } } },
                tooltip: { mode: 'index' }
            },
            scales: {
                x: { ticks: { color: textColor(), font: { size: 10 } }, grid: { color: gridColor() } },
                yMs:    { position: 'left',  ticks: { color: '#CC5500', font: { size: 10 } }, grid: { color: gridColor() }, title: { display: true, text: 'ms', color: textColor(), font: { size: 10 } } },
                yTps:   { position: 'right', ticks: { color: '#22C55E', font: { size: 10 } }, grid: { display: false }, title: { display: true, text: 'TPS', color: textColor(), font: { size: 10 } } },
                yCalls: { display: false },
            }
        }
    });

    // ── TPS by caller bar chart ───────────────────────────────────────────────
    const callerColors = {
        rag_query:       '#CC5500',
        engage_decision: '#22C55E',
        general_classify:'#3B82F6',
        stream_query:    '#8B5CF6',
    };
    const callerLabels = data.tps_by_caller.map(r => r.caller.replace(/_/g,' '));
    const callerTps    = data.tps_by_caller.map(r => r.avg_tps);
    const callerBgColors = data.tps_by_caller.map(r => callerColors[r.caller] ?? '#94A3B8');

    if (tpsBarChart) tpsBarChart.destroy();
    tpsBarChart = new Chart(document.getElementById('tpsBarChart'), {
        type: 'bar',
        data: {
            labels: callerLabels,
            datasets: [{
                label: 'Avg TPS',
                data: callerTps,
                backgroundColor: callerBgColors,
                borderRadius: 6,
                maxBarThickness: 40,
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                x: { ticks: { color: textColor(), font: { size: 10 } }, grid: { color: gridColor() } },
                y: { ticks: { color: textColor(), font: { size: 10 } }, grid: { display: false } },
            }
        }
    });
}

// ── Live stats refresh ────────────────────────────────────────────────────────
function pct(v) { return v !== null ? v + '%' : '—'; }
function mb(v)  { return v !== null ? v + ' MB' : '—'; }

function applyLiveStats(s) {
    // CPU
    const cpu = s.cpu ?? {};
    document.getElementById('cpu-pct').textContent  = pct(cpu.usage_pct);
    document.getElementById('cpu-bar').style.width  = (cpu.usage_pct ?? 0) + '%';
    document.getElementById('cpu-load').textContent = `${cpu.load_1m} / ${cpu.load_5m} / ${cpu.load_15m}`;
    document.getElementById('cpu-cores').textContent= (cpu.cores ?? '?') + ' cores';

    // RAM
    const mem = s.memory ?? {};
    document.getElementById('ram-pct').textContent  = pct(mem.used_pct);
    document.getElementById('ram-bar').style.width  = (mem.used_pct ?? 0) + '%';
    document.getElementById('ram-detail').textContent =
        `${mb(mem.used_mb)} used of ${mb(mem.total_mb)} · ${mb(mem.available_mb)} free`;

    // Swap
    if (mem.swap_total_mb) {
        document.getElementById('swap-block').style.display = '';
        document.getElementById('swap-pct').textContent     = pct(mem.swap_used_pct);
        document.getElementById('swap-bar').style.width     = (mem.swap_used_pct ?? 0) + '%';
        document.getElementById('swap-detail').textContent  = `${mb(mem.swap_used_mb)} used of ${mb(mem.swap_total_mb)}`;
    }

    // Config model
    document.getElementById('config-model').textContent    = s.config_model ?? '—';
    document.getElementById('ollama-url-badge').textContent = s.ollama_url ?? '';

    // Loaded models
    const loadedEl = document.getElementById('loaded-models-list');
    const models   = s.ollama_models ?? {};
    if ((models.loaded ?? []).length === 0) {
        loadedEl.innerHTML = '<span class="text-muted" style="font-size:.82rem">No models currently loaded in RAM</span>';
    } else {
        loadedEl.innerHTML = models.loaded.map(m => `
            <div class="ollama-model-row d-flex align-items-center gap-2 mb-1">
                <i class="bi bi-dot" style="color:#22C55E"></i>
                <strong>${m.name}</strong>
                ${m.size_gb  ? `<span class="badge bg-secondary">${m.size_gb} GB</span>` : ''}
                ${m.vram_gb  ? `<span class="badge" style="background:#7C3AED">${m.vram_gb} GB VRAM</span>` : ''}
                ${m.expires  ? `<span class="text-muted" style="font-size:.73rem">expires ${new Date(m.expires).toLocaleTimeString()}</span>` : ''}
            </div>`).join('');
    }

    // Available (pulled) models
    const availEl = document.getElementById('available-models-list');
    if ((models.available ?? []).length === 0) {
        availEl.innerHTML = models.error
            ? `<span class="text-danger" style="font-size:.82rem"><i class="bi bi-x-circle me-1"></i>${models.error}</span>`
            : '<span class="text-muted" style="font-size:.82rem">None found</span>';
    } else {
        availEl.innerHTML = models.available.map(m => `
            <div class="ollama-model-row d-flex align-items-center gap-2 mb-1">
                <i class="bi bi-box me-1" style="color:var(--text-muted)"></i>
                <span>${m.name}</span>
                ${m.params  ? `<span class="badge bg-secondary">${m.params}</span>` : ''}
                ${m.size_gb ? `<span class="text-muted" style="font-size:.73rem">${m.size_gb} GB</span>` : ''}
            </div>`).join('');
    }
}

// ── Fetch functions ───────────────────────────────────────────────────────────
async function fetchLiveStats() {
    try {
        const r = await fetch('{{ route("admin.ai-performance.live-stats") }}');
        if (!r.ok) return;
        applyLiveStats(await r.json());
    } catch(e) { /* silently ignore — UI already shows — */ }
}

async function fetchChartData() {
    try {
        const r = await fetch('{{ route("admin.ai-performance.chart-data") }}');
        if (!r.ok) return;
        buildCharts(await r.json());
    } catch(e) {}
}

// ── Boot ──────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    fetchLiveStats();
    fetchChartData();
    setInterval(fetchLiveStats, 30_000);
});
</script>
@endpush
