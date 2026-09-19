<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware Benchmark — mengukur metrik performa dan menyisipkannya
 * sebagai response header X-Benchmark-* DAN mencatatnya ke CSV.
 *
 * Header yang ditambahkan ke setiap response:
 *   X-Benchmark-ResponseTime  – waktu server (ms)
 *   X-Benchmark-QueryCount    – jumlah query DB
 *   X-Benchmark-QueryTime     – total waktu query (ms)
 *   X-Benchmark-Memory        – peak memory PHP (MB)
 *
 * Header ini dibaca langsung oleh BenchmarkRun command via cURL
 * sehingga tidak ada dependency filesystem antar proses.
 *
 * Aktifkan di .env:
 *   BENCHMARK_ENABLED=true
 *   BENCHMARK_BRANCH=lazy-nocache   (atau eager-redis)
 *
 * Log CSV tetap ditulis ke: storage/logs/benchmark.csv
 */
class BenchmarkMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('benchmark.enabled', false)) {
            return $next($request);
        }

        $startTime = defined('LARAVEL_START') ? LARAVEL_START : microtime(true);

        DB::enableQueryLog();

        $response = $next($request);

        // ── Kumpulkan metrik ───────────────────────────────────────────
        $queryLog     = DB::getQueryLog();
        $endTime      = microtime(true);
        $responseTime = round(($endTime - $startTime) * 1000, 2);
        $queryCount   = count($queryLog);
        $queryTotalMs = round(array_sum(array_column($queryLog, 'time')), 2);
        $memoryPeakMb = round(memory_get_peak_usage(true) / 1024 / 1024, 3);

        DB::disableQueryLog();

        // ── Sisipkan sebagai response header (dibaca cURL) ─────────────
        $response->headers->set('X-Benchmark-ResponseTime', $responseTime);
        $response->headers->set('X-Benchmark-QueryCount',   $queryCount);
        $response->headers->set('X-Benchmark-QueryTime',    $queryTotalMs);
        $response->headers->set('X-Benchmark-Memory',       $memoryPeakMb);

        // ── Catat ke CSV (untuk audit manual / browser) ────────────────
        $this->writeToCsv([
            'timestamp'      => now()->toIso8601String(),
            'branch'         => config('benchmark.branch', 'unknown'),
            'url'            => $request->path(),
            'method'         => $request->method(),
            'status'         => $response->getStatusCode(),
            'response_time'  => $responseTime,
            'query_count'    => $queryCount,
            'query_time_ms'  => $queryTotalMs,
            'memory_peak_mb' => $memoryPeakMb,
        ]);

        return $response;
    }

    private function writeToCsv(array $data): void
    {
        $path   = storage_path('logs/benchmark.csv');
        $isNew  = ! file_exists($path);
        $handle = fopen($path, 'a');

        if (! $handle) {
            return;
        }

        if ($isNew) {
            fputcsv($handle, array_keys($data));
        }

        fputcsv($handle, array_values($data));
        fclose($handle);
    }
}
