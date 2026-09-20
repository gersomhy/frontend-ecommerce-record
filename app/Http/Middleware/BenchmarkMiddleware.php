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

        $startRusage = function_exists('getrusage') ? getrusage() : null;
        $startTime   = defined('LARAVEL_START') ? LARAVEL_START : microtime(true);

        DB::enableQueryLog();

        $response = $next($request);

        // ── Kumpulkan metrik ───────────────────────────────────────────
        $queryLog     = DB::getQueryLog();
        $endTime      = microtime(true);
        $responseTime = round(($endTime - $startTime) * 1000, 2);
        $queryCount   = count($queryLog);
        $queryTotalMs = round(array_sum(array_column($queryLog, 'time')), 2);
        $memoryPeakMb = round(memory_get_peak_usage(true) / 1024 / 1024, 3);

        $cpuTimeMs = 0.0;
        if ($startRusage && function_exists('getrusage')) {
            $endRusage = getrusage();
            $userCpuMs = (($endRusage['ru_utime.tv_sec'] - $startRusage['ru_utime.tv_sec']) * 1000)
                       + (($endRusage['ru_utime.tv_usec'] - $startRusage['ru_utime.tv_usec']) / 1000);
            $sysCpuMs  = (($endRusage['ru_stime.tv_sec'] - $startRusage['ru_stime.tv_sec']) * 1000)
                       + (($endRusage['ru_stime.tv_usec'] - $startRusage['ru_stime.tv_usec']) / 1000);
            $cpuTimeMs = round($userCpuMs + $sysCpuMs, 2);
        }

        DB::disableQueryLog();

        // ── Sisipkan sebagai response header (dibaca cURL) ─────────────
        $response->headers->set('X-Benchmark-ResponseTime', $responseTime);
        $response->headers->set('X-Benchmark-QueryCount',   $queryCount);
        $response->headers->set('X-Benchmark-QueryTime',    $queryTotalMs);
        $response->headers->set('X-Benchmark-Memory',       $memoryPeakMb);
        $response->headers->set('X-Benchmark-CpuTime',      $cpuTimeMs);

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
            'cpu_time_ms'    => $cpuTimeMs,
        ]);

        return $response;
    }

    private function writeToCsv(array $data): void
    {
        $path = storage_path('logs/benchmark.csv');

        // Pastikan header kompatibel jika file lama sudah ada
        if (file_exists($path) && filesize($path) > 0) {
            $fh = @fopen($path, 'r');
            if ($fh) {
                $firstLine = fgets($fh);
                fclose($fh);
                if ($firstLine && ! str_contains($firstLine, 'cpu_time_ms')) {
                    $lines = file($path);
                    if ($lines && count($lines) > 0) {
                        $lines[0] = rtrim($lines[0], "\r\n") . ",cpu_time_ms\n";
                        for ($i = 1; $i < count($lines); $i++) {
                            $lines[$i] = rtrim($lines[$i], "\r\n") . ",\n";
                        }
                        @file_put_contents($path, implode('', $lines));
                    }
                }
            }
        }

        $handle = @fopen($path, 'a');

        // Jika terkunci oleh Excel di Windows, alihkan ke file alternatif
        if (! $handle) {
            $path   = storage_path('logs/benchmark_' . date('Ymd') . '.csv');
            $handle = @fopen($path, 'a');
        }

        if (! $handle) {
            return;
        }

        // Tulis header jika file baru / kosong
        if (ftell($handle) === 0) {
            fputcsv($handle, array_keys($data));
        }

        fputcsv($handle, array_values($data));
        fclose($handle);
    }
}
