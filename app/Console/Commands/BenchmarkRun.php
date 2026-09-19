<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Artisan command untuk menjalankan benchmark HTTP.
 *
 * Cara pakai:
 *   php artisan benchmark:run /products/sepatu-record-001 --n=30 --warmup=3
 *
 * Opsi:
 *   --n        Jumlah request yang diukur (default: 20)
 *   --warmup   Jumlah request pemanasan yang tidak dihitung (default: 2)
 *   --branch   Label branch/skenario (default: dari .env BENCHMARK_BRANCH)
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
        $path    = $this->argument('url');
        $n       = (int) $this->option('n');
        $warmup  = (int) $this->option('warmup');
        $branch  = $this->option('branch') ?: config('benchmark.branch', 'unknown');
        $delayMs = (int) $this->option('delay');
        $baseUrl = rtrim(config('app.url'), '/');
        $url     = $baseUrl . '/' . ltrim($path, '/');

        // ── Pastikan middleware aktif ───────────────────────────────────
        if (! config('benchmark.enabled', false)) {
            $this->error('BENCHMARK_ENABLED belum di-set ke true di .env!');
            $this->line('Tambahkan: BENCHMARK_ENABLED=true lalu jalankan: php artisan config:clear');
            return self::FAILURE;
        }

        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info("  BENCHMARK : <fg=yellow>{$branch}</>");
        $this->info("  URL       : {$url}");
        $this->info("  Iterasi   : {$n} request  |  Warmup: {$warmup} request");
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        // ── Warmup ─────────────────────────────────────────────────────
        if ($warmup > 0) {
            $this->line("<fg=gray>Warmup {$warmup} request (tidak dihitung)...</>");
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
            $result    = $this->sendRequest($url);
            $results[] = $result;

            $bar->setMessage(sprintf(
                'rt: %sms | q: %s | mem: %sMB',
                $result['response_time_ms'],
                $result['query_count'],
                $result['memory_peak_mb']
            ));
            $bar->advance();

            if ($i < $n) {
                usleep($delayMs * 1000);
            }
        }

        $bar->setMessage('selesai!');
        $bar->finish();
        $this->newLine(2);

        // ── Tampilkan statistik ────────────────────────────────────────
        $responseTimes = array_column($results, 'response_time_ms');
        $queryCounts   = array_column($results, 'query_count');
        $queryTimes    = array_column($results, 'query_time_ms');
        $memories      = array_column($results, 'memory_peak_mb');
        $loadingTimes  = array_column($results, 'loading_time_ms');

        $this->printStats('RESPONSE TIME (ms)',      $responseTimes);
        $this->printStats('QUERY COUNT',             $queryCounts,  0);
        $this->printStats('QUERY EXEC TIME (ms)',    $queryTimes);
        $this->printStats('MEMORY PEAK (MB)',        $memories,     3);
        $this->printStats('LOADING TIME / TTFB (ms)', $loadingTimes);

        // ── Simpan ke CSV ──────────────────────────────────────────────
        $this->saveSummaryCsv(
            $branch, $path, $n,
            $responseTimes, $queryCounts, $queryTimes, $memories, $loadingTimes
        );

        $this->info("\n✅ Hasil disimpan ke: <fg=cyan>storage/logs/benchmark_summary.csv</>");

        return self::SUCCESS;
    }

    /**
     * Kirim satu HTTP request via cURL dan baca metrik dari response header
     * X-Benchmark-* yang diset oleh BenchmarkMiddleware.
     */
    private function sendRequest(string $url): array
    {
        $responseHeaders = [];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER     => ['Accept: text/html'],
            // Aktifkan penerimaan header dalam response
            CURLOPT_HEADER         => true,
            // Callback untuk setiap baris header yang diterima
            CURLOPT_HEADERFUNCTION => function ($ch, $headerLine) use (&$responseHeaders) {
                $trimmed = trim($headerLine);
                if (str_contains($trimmed, ':')) {
                    [$name, $value] = explode(':', $trimmed, 2);
                    $responseHeaders[strtolower(trim($name))] = trim($value);
                }
                return strlen($headerLine);
            },
        ]);

        $start  = microtime(true);
        curl_exec($ch);
        $end    = microtime(true);
        $info   = curl_getinfo($ch);
        curl_close($ch);

        $totalMs  = round(($end - $start) * 1000, 2);
        $ttfbMs   = round(($info['starttransfer_time'] ?? 0) * 1000, 2);

        // Baca metrik dari header X-Benchmark-* yang diset middleware
        $queryCount   = (int)   ($responseHeaders['x-benchmark-querycount']   ?? 0);
        $queryTimeMs  = (float) ($responseHeaders['x-benchmark-querytime']    ?? 0.0);
        $memoryMb     = (float) ($responseHeaders['x-benchmark-memory']       ?? 0.0);
        $serverRtMs   = (float) ($responseHeaders['x-benchmark-responsetime'] ?? $totalMs);

        return [
            'http_code'        => $info['http_code'] ?? 0,
            'response_time_ms' => $serverRtMs,   // waktu server (dari header middleware)
            'loading_time_ms'  => $ttfbMs,        // TTFB dari perspektif client (cURL)
            'query_count'      => $queryCount,
            'query_time_ms'    => $queryTimeMs,
            'memory_peak_mb'   => $memoryMb,
        ];
    }

    private function printStats(string $label, array $values, int $decimals = 2): void
    {
        if (empty($values)) {
            return;
        }

        $sorted = $values;
        sort($sorted);
        $count = count($sorted);

        $min = round(min($sorted), $decimals);
        $max = round(max($sorted), $decimals);
        $avg = round(array_sum($sorted) / $count, $decimals);
        $p50 = round($sorted[(int) floor($count * 0.50)], $decimals);
        $p90 = round($sorted[(int) floor($count * 0.90)], $decimals);
        $p95 = round($sorted[min((int) floor($count * 0.95), $count - 1)], $decimals);

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

        if (! $fh) {
            return;
        }

        $headers = [
            'timestamp', 'branch', 'url', 'n',
            'rt_min', 'rt_avg', 'rt_p50', 'rt_p90', 'rt_p95', 'rt_max',
            'qc_min', 'qc_avg', 'qc_max',
            'qt_min', 'qt_avg', 'qt_max',
            'mem_min', 'mem_avg', 'mem_max',
            'ttfb_min', 'ttfb_avg', 'ttfb_p50', 'ttfb_p90', 'ttfb_max',
        ];

        if ($isNew) {
            fputcsv($fh, $headers);
        }

        $avg = fn (array $arr) => $arr
            ? round(array_sum($arr) / count($arr), 2)
            : 0;

        $p = function (array $arr, float $pct): float {
            sort($arr);
            return round($arr[min((int) floor(count($arr) * $pct), count($arr) - 1)], 2);
        };

        fputcsv($fh, [
            now()->toIso8601String(), $branch, $path, $n,
            // response time
            round(min($responseTimes), 2), $avg($responseTimes),
            $p($responseTimes, .50), $p($responseTimes, .90), $p($responseTimes, .95),
            round(max($responseTimes), 2),
            // query count
            min($queryCounts), $avg($queryCounts), max($queryCounts),
            // query time
            round(min($queryTimes), 2), $avg($queryTimes), round(max($queryTimes), 2),
            // memory
            round(min($memories), 3), $avg($memories), round(max($memories), 3),
            // TTFB
            round(min($loadingTimes), 2), $avg($loadingTimes),
            $p($loadingTimes, .50), $p($loadingTimes, .90),
            round(max($loadingTimes), 2),
        ]);

        fclose($fh);
    }
}
