<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;

/**
 * Artisan command untuk menjalankan benchmark HTTP.
 *
 * Cara pakai:
 *   php artisan benchmark:run
 *   php artisan benchmark:run /products
 *   php artisan benchmark:run /products/slug-produk --n=30 --warmup=3
 */
class BenchmarkRun extends Command
{
    protected $signature = 'benchmark:run
        {url? : Path URL yang diuji (jika kosong, otomatis menggunakan produk pertama dari DB)}
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

        // Otomatis pilih produk yang ada jika URL tidak diisi
        if (! $path) {
            $product = Product::active()->first() ?? Product::first();
            if ($product) {
                $path = '/products/' . $product->slug;
                $this->comment("ℹ️  URL tidak ditentukan, otomatis menguji produk: <fg=white>{$product->name}</>");
            } else {
                $path = '/products';
                $this->comment("ℹ️  URL tidak ditentukan, otomatis menguji katalog: <fg=white>/products</>");
            }
        }

        $baseUrl = rtrim(config('app.url'), '/');
        $url     = $baseUrl . '/' . ltrim($path, '/');

        // Pastikan middleware aktif
        if (! config('benchmark.enabled', false)) {
            $this->error('BENCHMARK_ENABLED belum di-set ke true di .env!');
            $this->line('Tambahkan: BENCHMARK_ENABLED=true di .env lalu jalankan: php artisan config:clear');
            return self::FAILURE;
        }

        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info("  BENCHMARK : <fg=yellow;options=bold>{$branch}</>");
        $this->info("  URL       : <fg=cyan>{$url}</>");
        $this->info("  Iterasi   : {$n} request  |  Warmup: {$warmup} request");
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        // ── Pengecekan Awal (Probe Request) ───────────────────────────
        $probe = $this->sendRequest($url);
        if ($probe['http_code'] === 404) {
            $this->error("\n❌ HTTP 404 Not Found!");
            $this->warn("Halaman tidak ditemukan di: {$url}");
            $this->line("Karena 404, Controller tidak berjalan sehingga query count = 0.");
            $sample = Product::first();
            if ($sample) {
                $this->line("Contoh URL produk yang tersedia di database:");
                $this->line("  <fg=yellow>php artisan benchmark:run /products/{$sample->slug}</>");
            }
            return self::FAILURE;
        }

        if ($probe['http_code'] !== 200) {
            $this->warn("\n⚠️ Server mengembalikan HTTP {$probe['http_code']} (bukan 200 OK)!");
        }

        // ── Warmup ─────────────────────────────────────────────────────
        if ($warmup > 0) {
            $this->line("<fg=gray>Menjalankan {$warmup} request pemanasan (warmup)...</>");
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

        $this->printStats('RESPONSE TIME (ms)',       $responseTimes);
        $this->printStats('QUERY COUNT',              $queryCounts,  0);
        $this->printStats('QUERY EXEC TIME (ms)',     $queryTimes);
        $this->printStats('MEMORY PEAK (MB)',         $memories,     3);
        $this->printStats('LOADING TIME / TTFB (ms)', $loadingTimes);

        // ── Simpan ke CSV (dengan proteksi jika file sedang dibuka) ────
        $savedFile = $this->saveSummaryCsv(
            $branch, $path, $n,
            $responseTimes, $queryCounts, $queryTimes, $memories, $loadingTimes
        );

        if ($savedFile) {
            $this->info("\n✅ Hasil disimpan ke: <fg=cyan>{$savedFile}</>");
        }

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
            CURLOPT_HEADER         => true,
            CURLOPT_HEADERFUNCTION => function ($ch, $headerLine) use (&$responseHeaders) {
                $trimmed = trim($headerLine);
                if (str_contains($trimmed, ':')) {
                    [$name, $value] = explode(':', $trimmed, 2);
                    $responseHeaders[strtolower(trim($name))] = trim($value);
                }
                return strlen($headerLine);
            },
        ]);

        $start = microtime(true);
        curl_exec($ch);
        $end   = microtime(true);
        $info  = curl_getinfo($ch);
        curl_close($ch);

        $totalMs = round(($end - $start) * 1000, 2);
        $ttfbMs  = round(($info['starttransfer_time'] ?? 0) * 1000, 2);

        $queryCount   = (int)   ($responseHeaders['x-benchmark-querycount']   ?? 0);
        $queryTimeMs  = (float) ($responseHeaders['x-benchmark-querytime']    ?? 0.0);
        $memoryMb     = (float) ($responseHeaders['x-benchmark-memory']       ?? 0.0);
        $serverRtMs   = (float) ($responseHeaders['x-benchmark-responsetime'] ?? $totalMs);

        return [
            'http_code'        => $info['http_code'] ?? 0,
            'response_time_ms' => $serverRtMs,
            'loading_time_ms'  => $ttfbMs,
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
    ): ?string {
        $primaryPath = storage_path('logs/benchmark_summary.csv');
        $targetPath  = $primaryPath;

        // Buka file dengan proteksi file lock Windows (misal dibuka di Excel)
        $fh = @fopen($targetPath, 'a');
        if (! $fh) {
            $targetPath = storage_path('logs/benchmark_summary_' . date('Ymd_His') . '.csv');
            $fh = @fopen($targetPath, 'a');
            if ($fh) {
                $this->warn("\n⚠️ storage/logs/benchmark_summary.csv sedang dibuka di Excel/program lain.");
                $this->line("Hasil dialihkan ke file baru: <fg=yellow>" . basename($targetPath) . "</>");
            } else {
                $this->error("\n❌ Tidak dapat menulis ke direktori storage/logs.");
                return null;
            }
        }

        $headers = [
            'timestamp', 'branch', 'url', 'n',
            'rt_min', 'rt_avg', 'rt_p50', 'rt_p90', 'rt_p95', 'rt_max',
            'qc_min', 'qc_avg', 'qc_max',
            'qt_min', 'qt_avg', 'qt_max',
            'mem_min', 'mem_avg', 'mem_max',
            'ttfb_min', 'ttfb_avg', 'ttfb_p50', 'ttfb_p90', 'ttfb_max',
        ];

        // Tulis header hanya jika ukuran file masih 0
        if (ftell($fh) === 0) {
            fputcsv($fh, $headers);
        }

        $avg = fn (array $arr) => $arr ? round(array_sum($arr) / count($arr), 2) : 0;
        $p   = function (array $arr, float $pct): float {
            sort($arr);
            return round($arr[min((int) floor(count($arr) * $pct), count($arr) - 1)], 2);
        };

        fputcsv($fh, [
            now()->toIso8601String(), $branch, $path, $n,
            round(min($responseTimes), 2), $avg($responseTimes),
            $p($responseTimes, .50), $p($responseTimes, .90), $p($responseTimes, .95),
            round(max($responseTimes), 2),
            min($queryCounts), $avg($queryCounts), max($queryCounts),
            round(min($queryTimes), 2), $avg($queryTimes), round(max($queryTimes), 2),
            round(min($memories), 3), $avg($memories), round(max($memories), 3),
            round(min($loadingTimes), 2), $avg($loadingTimes),
            $p($loadingTimes, .50), $p($loadingTimes, .90),
            round(max($loadingTimes), 2),
        ]);

        fclose($fh);

        return $targetPath;
    }
}
