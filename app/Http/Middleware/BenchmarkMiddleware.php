<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware Benchmark — mencatat metrik performa ke CSV.
 *
 * Metrik yang dicatat:
 *   timestamp      – waktu request (ISO 8601)
 *   branch         – nilai env BENCHMARK_BRANCH (mis. "lazy-nocache")
 *   url            – path yang diakses
 *   method         – HTTP method
 *   status         – HTTP status code
 *   response_time  – waktu total server memproses request (ms)
 *   query_count    – jumlah query DB yang dieksekusi
 *   query_time_ms  – total waktu eksekusi semua query (ms)
 *   memory_peak_mb – penggunaan memory PHP tertinggi selama request (MB)
 *
 * Aktifkan dengan menambahkan di .env:
 *   BENCHMARK_ENABLED=true
 *   BENCHMARK_BRANCH=lazy-nocache   (atau eager-redis)
 *
 * Output CSV: storage/logs/benchmark.csv
 */
class BenchmarkMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // Hanya aktif jika BENCHMARK_ENABLED=true
        if (! config('benchmark.enabled', false)) {
            return $next($request);
        }

        // Catat waktu mulai dan baseline memory sebelum request diproses
        $startTime   = defined('LARAVEL_START') ? LARAVEL_START : microtime(true);
        $queryLog    = [];

        // Aktifkan query logging
        DB::enableQueryLog();

        $response = $next($request);

        // Kumpulkan data setelah response dibuat
        $queryLog      = DB::getQueryLog();
        $endTime       = microtime(true);
        $responseTime  = round(($endTime - $startTime) * 1000, 2);   // ms
        $queryCount    = count($queryLog);
        $queryTotalMs  = round(array_sum(array_column($queryLog, 'time')), 2);
        $memoryPeakMb  = round(memory_get_peak_usage(true) / 1024 / 1024, 3); // MB

        DB::disableQueryLog();

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
        $path    = storage_path('logs/benchmark.csv');
        $isNew   = ! file_exists($path);
        $handle  = fopen($path, 'a');

        if (! $handle) {
            return;
        }

        // Tulis header hanya jika file baru
        if ($isNew) {
            fputcsv($handle, array_keys($data));
        }

        fputcsv($handle, array_values($data));
        fclose($handle);
    }
}
