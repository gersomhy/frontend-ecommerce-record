<?php

// Polyfill untuk server hosting yang belum mengaktifkan ekstensi PHP fileinfo
if (! class_exists('finfo')) {
    if (! defined('FILEINFO_NONE')) {
        define('FILEINFO_NONE', 0);
    }
    if (! defined('FILEINFO_MIME_TYPE')) {
        define('FILEINFO_MIME_TYPE', 16);
    }
    if (! defined('FILEINFO_MIME')) {
        define('FILEINFO_MIME', 1040);
    }

    class finfo
    {
        public function __construct($flags = null, $magicFile = null)
        {
        }

        public function file(string $filename, int $flags = FILEINFO_MIME_TYPE, $context = null): string|false
        {
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            $mimes = [
                'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
                'gif' => 'image/gif', 'webp' => 'image/webp', 'svg' => 'image/svg+xml',
                'pdf' => 'application/pdf', 'zip' => 'application/zip',
                'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'xls' => 'application/vnd.ms-excel', 'csv' => 'text/csv',
                'txt' => 'text/plain', 'json' => 'application/json',
                'mp4' => 'video/mp4', 'mov' => 'video/quicktime',
            ];

            return $mimes[$ext] ?? 'application/octet-stream';
        }

        public function buffer(string $string, int $flags = FILEINFO_MIME_TYPE, $context = null): string|false
        {
            return 'application/octet-stream';
        }
    }
}

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');

        // Catat metrik performa (response time, query count, memory) ke CSV.
        // Hanya aktif saat BENCHMARK_ENABLED=true di .env.
        $middleware->web(append: [
            \App\Http\Middleware\BenchmarkMiddleware::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'midtrans/callback',
            'checkout/midtrans/callback',

            // Webhook Biteship juga datang dari peladen mereka, bukan dari
            // peramban, jadi tidak mungkin membawa token CSRF. Keasliannya
            // diperiksa lewat Custom Header di BiteshipWebhookController.
            'webhook/biteship',

            // Pelacakan durasi atensi seksi (dwell time) dikirim via beacon/fetch keepalive saat exit
            'track/dwell',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if ($e->getStatusCode() !== 419) {
                return null;
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Sesi kamu sudah kedaluwarsa. Muat ulang halaman lalu coba lagi.',
                ], 419);
            }

            $tujuan = $request->is('checkout', 'checkout/*')
                ? route('checkout.index')
                : url()->previous();

            return redirect($tujuan)
                ->withInput($request->except(['password', 'password_confirmation', '_token']))
                ->with('error', 'Sesi halaman sudah kedaluwarsa karena dibiarkan terlalu lama. '
                    . 'Data yang kamu isi masih tersimpan - silakan coba lagi.');
        });
    })->create();
