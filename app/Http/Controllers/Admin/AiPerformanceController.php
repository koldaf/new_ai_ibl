<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiPerformanceLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiPerformanceController extends Controller
{
    /**
     * Main dashboard view.
     */
    public function index(Request $request)
    {
        // ── Summary cards (today) ──────────────────────────────────────────────
        $today = now()->startOfDay();

        $todayCount   = AiPerformanceLog::where('created_at', '>=', $today)->count();
        $avgResponse  = AiPerformanceLog::where('created_at', '>=', $today)->avg('response_time_ms');
        $avgTps       = AiPerformanceLog::where('created_at', '>=', $today)->avg('tokens_per_second');
        $avgTtft      = AiPerformanceLog::where('created_at', '>=', $today)->avg('ttft_ms');

        // ── Caller breakdown (all time) ────────────────────────────────────────
        $callerStats = AiPerformanceLog::select(
            'caller',
            DB::raw('COUNT(*) as total_calls'),
            DB::raw('ROUND(AVG(response_time_ms), 0) as avg_response_ms'),
            DB::raw('ROUND(AVG(tokens_per_second), 2) as avg_tps'),
            DB::raw('ROUND(AVG(ttft_ms), 2) as avg_ttft_ms'),
            DB::raw('ROUND(AVG(tokens_generated), 0) as avg_tokens'),
            DB::raw('SUM(CASE WHEN error IS NOT NULL THEN 1 ELSE 0 END) as error_count')
        )
        ->groupBy('caller')
        ->orderByDesc('total_calls')
        ->get();

        // ── Recent log entries (paginated) ────────────────────────────────────
        $logs = AiPerformanceLog::latest()
            ->paginate(20, ['*'], 'page', $request->get('page', 1));

        return view('dashboard.admin.ai_performance.index', [
            'todayCount'  => $todayCount,
            'avgResponse' => round($avgResponse ?? 0),
            'avgTps'      => round($avgTps ?? 0, 1),
            'avgTtft'     => round($avgTtft ?? 0),
            'callerStats' => $callerStats,
            'logs'        => $logs,
        ]);
    }

    /**
     * Live system stats — polled every 30 s via AJAX.
     */
    public function liveStats(): JsonResponse
    {
        return response()->json([
            'memory'       => $this->getMemoryStats(),
            'cpu'          => $this->getCpuStats(),
            'ollama_models'=> $this->getOllamaModels(),
            'config_model' => config('ollama.llm_model', config('services.ollama.llm_model', 'unknown')),
            'ollama_url'   => config('ollama.base_url', config('services.ollama.url', 'http://localhost:11434')),
        ]);
    }

    /**
     * Chart data — last 100 records grouped into hourly buckets.
     */
    public function chartData(): JsonResponse
    {
        // ── Time-series: response time over last 24 hours ──────────────────────
        $hourlyData = AiPerformanceLog::select(
            DB::raw("DATE_FORMAT(created_at, '%Y-%m-%d %H:00') as hour"),
            DB::raw('ROUND(AVG(response_time_ms), 0) as avg_response_ms'),
            DB::raw('ROUND(AVG(tokens_per_second), 2) as avg_tps'),
            DB::raw('ROUND(AVG(ttft_ms), 0) as avg_ttft_ms'),
            DB::raw('COUNT(*) as call_count')
        )
        ->where('created_at', '>=', now()->subHours(24))
        ->groupBy('hour')
        ->orderBy('hour')
        ->get();

        // ── TPS by caller ──────────────────────────────────────────────────────
        $tpsByCallerRaw = AiPerformanceLog::select(
            'caller',
            DB::raw('ROUND(AVG(tokens_per_second), 2) as avg_tps')
        )
        ->whereNotNull('tokens_per_second')
        ->groupBy('caller')
        ->get();

        // ── Model usage ───────────────────────────────────────────────────────
        $modelUsage = AiPerformanceLog::select(
            'model_name',
            DB::raw('COUNT(*) as total')
        )
        ->groupBy('model_name')
        ->orderByDesc('total')
        ->get();

        return response()->json([
            'hourly'       => $hourlyData,
            'tps_by_caller'=> $tpsByCallerRaw,
            'model_usage'  => $modelUsage,
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  Private helpers
    // ──────────────────────────────────────────────────────────────────────────

    private function getMemoryStats(): array
    {
        $result = [
            'total_mb'     => null,
            'available_mb' => null,
            'used_mb'      => null,
            'used_pct'     => null,
            'swap_total_mb'=> null,
            'swap_used_mb' => null,
            'swap_used_pct'=> null,
        ];

        if (!is_readable('/proc/meminfo')) {
            return $result;
        }

        $lines = file('/proc/meminfo', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $memInfo = [];
        foreach ($lines as $line) {
            if (preg_match('/^(\w+):\s+(\d+)\s*kB$/i', $line, $m)) {
                $memInfo[$m[1]] = (int) $m[2]; // kB
            }
        }

        $totalKb     = $memInfo['MemTotal']     ?? 0;
        $availableKb = $memInfo['MemAvailable'] ?? 0;
        $swapTotalKb = $memInfo['SwapTotal']    ?? 0;
        $swapFreeKb  = $memInfo['SwapFree']     ?? 0;

        if ($totalKb > 0) {
            $usedKb = $totalKb - $availableKb;
            $result['total_mb']     = round($totalKb / 1024, 1);
            $result['available_mb'] = round($availableKb / 1024, 1);
            $result['used_mb']      = round($usedKb / 1024, 1);
            $result['used_pct']     = round(($usedKb / $totalKb) * 100, 1);
        }

        if ($swapTotalKb > 0) {
            $swapUsedKb = $swapTotalKb - $swapFreeKb;
            $result['swap_total_mb'] = round($swapTotalKb / 1024, 1);
            $result['swap_used_mb']  = round($swapUsedKb / 1024, 1);
            $result['swap_used_pct'] = round(($swapUsedKb / $swapTotalKb) * 100, 1);
        }

        return $result;
    }

    private function getCpuStats(): array
    {
        $load = sys_getloadavg();

        // Count logical CPU cores from /proc/cpuinfo
        $cores = 1;
        if (is_readable('/proc/cpuinfo')) {
            $cpuinfo = file_get_contents('/proc/cpuinfo');
            preg_match_all('/^processor\s+:/m', $cpuinfo, $matches);
            $cores = max(1, count($matches[0]));
        }

        // Approximate % usage: (load_1min / cores) * 100 capped at 100
        $usagePct = min(100, round(($load[0] / $cores) * 100, 1));

        return [
            'load_1m'   => round($load[0], 2),
            'load_5m'   => round($load[1], 2),
            'load_15m'  => round($load[2], 2),
            'cores'     => $cores,
            'usage_pct' => $usagePct,
        ];
    }

    private function getOllamaModels(): array
    {
        $ollamaUrl = rtrim(config('ollama.base_url', config('services.ollama.url', 'http://localhost:11434')), '/');

        try {
            // /api/ps returns currently loaded (in-memory) models
            $ps = Http::timeout(3)->get("{$ollamaUrl}/api/ps");
            // /api/tags returns all pulled models
            $tags = Http::timeout(3)->get("{$ollamaUrl}/api/tags");

            $loaded = [];
            if ($ps->successful()) {
                foreach ($ps->json('models') ?? [] as $m) {
                    $loaded[] = [
                        'name'      => $m['name']            ?? 'unknown',
                        'size_gb'   => isset($m['size'])     ? round($m['size'] / 1_073_741_824, 2) : null,
                        'vram_gb'   => isset($m['size_vram'])? round($m['size_vram'] / 1_073_741_824, 2) : null,
                        'expires'   => $m['expires_at']      ?? null,
                        'loaded'    => true,
                    ];
                }
            }

            $available = [];
            if ($tags->successful()) {
                foreach ($tags->json('models') ?? [] as $m) {
                    $available[] = [
                        'name'    => $m['name']         ?? 'unknown',
                        'size_gb' => isset($m['size'])  ? round($m['size'] / 1_073_741_824, 2) : null,
                        'family'  => $m['details']['family'] ?? null,
                        'params'  => $m['details']['parameter_size'] ?? null,
                    ];
                }
            }

            return ['loaded' => $loaded, 'available' => $available];

        } catch (\Throwable $e) {
            Log::debug('[AiMonitor] Could not query Ollama models', ['error' => $e->getMessage()]);
            return ['loaded' => [], 'available' => [], 'error' => 'Ollama unreachable'];
        }
    }
}
