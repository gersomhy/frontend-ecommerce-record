<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Benchmark Toggle
    |--------------------------------------------------------------------------
    | Set BENCHMARK_ENABLED=true di .env untuk mengaktifkan pencatatan metrik.
    | Nonaktifkan di production agar tidak ada overhead pada pengunjung nyata.
    */
    'enabled' => env('BENCHMARK_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Nama Branch / Skenario
    |--------------------------------------------------------------------------
    | Diisi sesuai branch yang sedang diuji.
    | Contoh: BENCHMARK_BRANCH=lazy-nocache atau BENCHMARK_BRANCH=eager-redis
    */
    'branch' => env('BENCHMARK_BRANCH', 'unknown'),
];
