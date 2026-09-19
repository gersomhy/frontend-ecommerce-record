<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Artisan command untuk menjalankan benchmark HTTP sederhana.
 *
 * Cara pakai:
 *   php artisan benchmark:run /products/sepatu-record-001 --n=30 --warmup=3
 *
 * Opsi:
 *   --n        Jumlah request yang dikirim (default: 20)
 *   --warmup   Jumlah request pemanasan yang tidak dihitung (default: 2)
 *   --branch   Label branch untuk output (default: dari .env BENCHMARK_BRANCH)
 *   --delay    Jeda antar request dalam milidetik (default: 100)
 */
class BenchmarkRun extends Command
{
    protected $signature = 'benchmark:run
        {url : Path URL yang akan diuji, mis. /products/sepatu-001}
        {--n=20 : Jumlah request yang diukur}
        {--warmup=2 : Jumlah request pemanasan (tidak dihitung)}
        {--branch= : Label branch / skenario}
        {--delay=100 : Jeda antar request (ms)}';

    protected $description = 'Jalankan benchmark HTTP dan tampilkan ringkasan response time, query, dan memory';

    public function handle(): int
    {
        $path     = $this->argument('url');
        $n        = (int) $this->option('n');
        $warmup   = (int) $this->option('warmup');
        $branch   = $this->option('branch') ?: config('benchmark.branch', 'unknown');
        $delayMs  = (int) $this->option('delay');
        $baseUrl  = rtrim(config('app.url'), '/');
        $url      = $baseUrl . '/' . ltrim($path, '/');

        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->info("  BENCHMARK: <fg=yellow>{$branch}</>");
        $this->info("  URL      : {$url}");
        $this->info("  Iterasi  : {$n} request  |  Warmup: {$warmup} request");
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

        // ── Warmup ─────────────────────────────────────────────────────
        if ($warmup > 0) {
            $this->line("<fg=gray>Warmup {$warmup} request...</>");
            for ($i = 0; $i < $warmup; $i++) {
                $this->sendRequest($url);
                usleep($delayMs * 1000);
            }
            $this->line("<fg=gray>Warmup selesai. Mulai pengukuran...\n</>");
        }

        // ── Pengukuran ─────────────────────────────────────────────────
        $results = [];

        $bar = $this->output->createProgressBar($n);
        $bar->setFormat(" %current%/%max% [%bar%] %percent:3s%% — %message%");
        $bar->setMessage('memulai...');
        $bar->start();

        for ($i = 1; $i <= $n; $i++) {
            $result = $this->sendRequest($url);
            $results[] = $result;

            $bar->setMessage("response: {$result['response_time_ms']}ms | query: {$result['query_count']}");
            $bar->advance();

            if ($i < $n) {
                usleep($delayMs * 1000);
            }
        }

        $bar->setMessage('selesai!');
        $bar->finish();
        $this->newLine(2);

        // ── Hitung statistik ───────────────────────────────────────────
        $responseTimes = array_column($results, 'response_time_ms');
        $queryCounts   = array_column($results, 'query_count');
        $queryTimes    = array_column($results, 'query_time_ms');
        $memories      = array_column($results, 'memory_peak_mb');
        $loadingTimes  = array_column($results, 'loading_time_ms');

        $this->printStats("RESPONSE TIME (ms)", $responseTimes);
        $this->printStats("QUERY COUNT", $queryCounts, 0);
        $this->printStats("QUERY EXEC TIME (ms)", $queryTimes);
        $this->printStats("MEMORY PEAK (MB)", $memories, 3);
        $this->printStats("LOADING TIME / TTFB (ms)", $loadingTimes);

        // ── Simpan ke CSV ──────────────────────────────────────────────
        $this->saveSummaryCsv($branch, $path, $n, $responseTimes, $queryCounts, $queryTimes, $memories, $loadingTimes);

        $this->info("\n✅ Hasil disimpan ke: <fg=cyan>storage/logs/benchmark_summary.csv</>");

        return self::SUCCESS;
    }

    /**
     * Kirim HTTP request dan ukur metriknya menggunakan cURL.
     */
    private function sendRequest(string $url): array
    {
        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER     => ['Accept: text/html'],
            // Catat waktu dari perspektif client (TTFB = Time To First Byte)
            CURLINFO_HEADER_OUT    => true,
        ]);

        $start    = microtime(true);
        $body     = curl_exec($ch);
        $end      = microtime(true);
        $info     = curl_getinfo($ch);

        curl_close($ch);

        $totalMs   = round(($end - $start) * 1000, 2);
        $ttfbMs    = round($info['starttransfer_time'] * 1000, 2);  // TTFB
        $httpCode  = $info['http_code'] ?? 0;

        // Coba baca metrik dari response header X-Benchmark-* yang diset middleware
        $memoryMb  = 0.0;
        $queryCount = 0;
        $queryTimeMs = 0.0;

        // Parse header dari curl untuk baca X-Benchmark-* header
        if (isset($info['request_header'])) {
            // Header response tidak tersedia langsung dari curl_getinfo untuk CURLOPT_RETURNTRANSFER
            // Kita parse dari body jika ada meta tag benchmark, atau baca dari CSV log
        }

        // Baca baris terakhir dari CSV log yang ditulis middleware
        $csvPath = storage_path('logs/benchmark.csv');
        if (file_exists($csvPath)) {
            $lines = file($csvPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if (count($lines) > 1) {
                $last = str_getcsv(end($lines));
                // header: timestamp, branch, url, method, status, response_time, query_count, query_time_ms, memory_peak_mb
                if (count($last) >= 9) {
                    $queryCount   = (int)   $last[6];
                    $queryTimeMs  = (float) $last[7];
                    $memoryMb     = (float) $last[8];
                }
            }
        }

        return [
            'http_code'        => $httpCode,
            'response_time_ms' => $totalMs,
            'loading_time_ms'  => $ttfbMs,  // TTFB dari perspektif client
            'query_count'      => $queryCount,
            'query_time_ms'    => $queryTimeMs,
            'memory_peak_mb'   => $memoryMb,
        ];
    }

    private function printStats(string $label, array $values, int $decimals = 2): void
    {
        if (empty($values)) return;

        sort($values);
        $count  = count($values);
        $min    = round(min($values), $decimals);
        $max    = round(max($values), $decimals);
        $avg    = round(array_sum($values) / $count, $decimals);
        $p50    = round($values[(int) floor($count * 0.50)], $decimals);
        $p90    = round($values[(int) floor($count * 0.90)], $decimals);
        $p95    = round($values[(int) floor($count * 0.95)], $decimals);

        $this->line("<fg=cyan;options=bold>  {$label}</>");
        $this->table(
            ['Min', 'Avg', 'P50 (Median)', 'P90', 'P95', 'Max'],
            [[$min, $avg, $p50, $p90, $p95, $max]]
        );
    }

    private function saveSummaryCsv(
        string $branch,
        string $path,
        int    $n,
        array  $responseTimes,
        array  $queryCounts,
        array  $queryTimes,
        array  $memories,
        array  $loadingTimes
    ): void {
        $file  = storage_path('logs/benchmark_summary.csv');
        $isNew = ! file_exists($file);
        $fh    = fopen($file, 'a');

        if (! $fh) return;

        $headers = [
            'timestamp', 'branch', 'url', 'n',
            'rt_min', 'rt_avg', 'rt_p50', 'rt_p90', 'rt_p95', 'rt_max',
            'qc_min', 'qc_avg', 'qc_max',
            'qt_min', 'qt_avg', 'qt_max',
            'mem_min', 'mem_avg', 'mem_max',
            'ttfb_min', 'ttfb_avg', 'ttfb_p50', 'ttfb_p90', 'ttfb_max',
        ];

        if ($isNew) fputcsv($fh, $headers);

        $avg = fn($arr) => $arr ? round(array_sum($arr) / count($arr), 2) : 0;
        $p   = function (array $arr, float $pct): float {
            sort($arr);
            return round($arr[(int) floor(count($arr) * $pct)], 2);
        };

        fputcsv($fh, [
            now()->toIso8601String(), $branch, $path, $n,
            round(min($responseTimes), 2), $avg($responseTimes), $p($responseTimes, .50), $p($responseTimes, .90), $p($responseTimes, .95), round(max($responseTimes), 2),
            min($queryCounts), $avg($queryCounts), max($queryCounts),
            round(min($queryTimes), 2), $avg($queryTimes), round(max($queryTimes), 2),
            round(min($memories), 3), $avg($memories), round(max($memories), 3),
            round(min($loadingTimes), 2), $avg($loadingTimes), $p($loadingTimes, .50), $p($loadingTimes, .90), round(max($loadingTimes), 2),
        ]);

        fclose($fh);
    }
}
