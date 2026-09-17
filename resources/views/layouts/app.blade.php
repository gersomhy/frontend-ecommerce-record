<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ isset($title) ? $title . ' - ' : '' }}Record - LANGKAHPENUHGAYA</title>

        {{-- Favicon / Icon Tab Browser --}}
        <link rel="icon" type="image/png" href="{{ asset('images/favicon-record.png?v=2') }}">
        <link rel="shortcut icon" type="image/png" href="{{ asset('images/favicon-record.png?v=2') }}">
        <link rel="apple-touch-icon" href="{{ asset('images/favicon-record.png?v=2') }}">

        {{-- Font Inter dari Google --}}
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

        {{-- Ikon Font Awesome --}}
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

        {{-- CSS & JS dikompilasi oleh Vite --}}
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        {{-- ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¢Ãƒâ€šÃ‚ÂÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¢Ãƒâ€šÃ‚ÂÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¢Ãƒâ€šÃ‚ÂÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¢Ãƒâ€šÃ‚ÂÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¢Ãƒâ€šÃ‚ÂÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¢Ãƒâ€šÃ‚ÂÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¢Ãƒâ€šÃ‚ÂÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¢Ãƒâ€šÃ‚ÂÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¢Ãƒâ€šÃ‚ÂÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¢Ãƒâ€šÃ‚Â Kenyamanan di layar sentuh ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¢Ãƒâ€šÃ‚ÂÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¢Ãƒâ€šÃ‚ÂÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¢Ãƒâ€šÃ‚ÂÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¢Ãƒâ€šÃ‚ÂÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¢Ãƒâ€šÃ‚ÂÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¢Ãƒâ€šÃ‚ÂÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¢Ãƒâ€šÃ‚ÂÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¢Ãƒâ€šÃ‚ÂÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¢Ãƒâ€šÃ‚ÂÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¢Ãƒâ€šÃ‚Â --}}
        <style>
            @media (max-width: 640px) {
                /* Bar atas: dua tautan kecil yang berdempetan. */
                .bar-atas-tautan {
                    display: inline-flex; align-items: center;
                    min-height: 34px; padding: 0 4px;
                }

                /* Tautan footer disusun menurun dalam daftar, jadi yang ditambah cukup ruang atas-bawahnya. */
                footer li > a {
                    display: block;
                    padding: 7px 0;
                    min-height: 34px;
                }

                /* Ikon media sosial di footer: lingkarannya sudah cukup besar, tetapi jarak antar ikon dirapatkan o... */
                footer .rounded-full { min-width: 38px; min-height: 38px; }

                /* Tautan teks kecil yang bertebaran di kepala kartu dan kepala halaman: "Dashboard Saya", "Lihat Se... */
                a.text-xs.font-bold,
                button.text-xs.font-bold,
                a.text-\[11px\].font-bold,
                button.text-\[11px\].font-bold {
                    padding-block: 9px;
                }

                /* Bintang di kartu produk ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â sekaligus tautan ke ulasannya. */
                .kartu-bintang { min-height: 30px; padding: 4px 0; }

                /* Tanda "Belum Dinilai" di riwayat pesanan. */
                .belum-nilai { min-height: 32px; padding: 6px 11px; }

                /* Baris sebaran bintang yang berfungsi sebagai penyaring. */
                .ulasan-baris-klik { min-height: 36px; }

                /* Tautan menuju detail di halaman lacak pesanan. */
                .lacak-detail {
                    display: inline-flex; align-items: center;
                    min-height: 34px;
                }
            }
        </style>

        {{-- Gaya tambahan khusus halaman, dikirim lewat @push('styles') --}}
                @stack('styles')

        <!-- Meta Pixel Code -->
        <script>
        !function(f,b,e,v,n,t,s)
        {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
        n.callMethod.apply(n,arguments):n.queue.push(arguments)};
        if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
        n.queue=[];t=b.createElement(e);t.async=!0;
        t.src=v;s=b.getElementsByTagName(e)[0];
        s.parentNode.insertBefore(t,s)}(window, document,'script',
        'https://connect.facebook.net/en_US/fbevents.js');
        fbq('init', '1385264673475050');
        fbq('track', 'PageView');
        </script>
        <noscript><img height="1" width="1" style="display:none"
        src="https://www.facebook.com/tr?id=1385264673475050&ev=PageView&noscript=1"
        /></noscript>
        <!-- End Meta Pixel Code -->
    </head>
    <body class="font-sans antialiased text-text bg-bg">
        <div class="min-h-screen flex flex-col justify-between">
            <div>
                {{-- Bar pengumuman di paling atas --}}
                <x-top-bar />

                {{-- Navbar / Menu navigasi --}}
                <x-navbar />

                {{-- Notifikasi berhasil (misal setelah tambah ke keranjang) --}}
                @if (session('success'))
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-4" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)">
                        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-sm p-4 text-sm flex justify-between items-center shadow-sm">
                            <div class="flex items-center gap-2">
                                <svg class="h-5 w-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <span>{{ session('success') }}</span>
                            </div>
                            <button @click="show = false" class="text-emerald-500 hover:text-emerald-700 font-bold text-lg leading-none">×</button>
                        </div>
                    </div>
                @endif

                {{-- Notifikasi error --}}
                @if (session('error'))
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-4" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)">
                        <div class="bg-rose-50 border border-rose-200 text-rose-800 rounded-sm p-4 text-sm flex justify-between items-center shadow-sm">
                            <div class="flex items-center gap-2">
                                <svg class="h-5 w-5 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <span>{{ session('error') }}</span>
                            </div>
                            <button @click="show = false" class="text-rose-500 hover:text-rose-700 font-bold text-lg leading-none">×</button>
                        </div>
                    </div>
                @endif

                {{-- Konten utama halaman --}}
                <main class="py-4">
                    {{ $slot }}
                </main>
            </div>

            {{-- Footer di bagian bawah --}}
            <x-footer />
        </div>

        {{-- Toast Notifikasi Pembelian Otomatis (Fake Purchase Toast) --}}
        <x-pembelian-terbaru :pembelian="$pembelianTerbaru ?? []" />




        {{-- ═══════════════════════════════════════════════════════════
             Dwell Tracker — Web Evaluation System
             Mencatat berapa lama pengunjung berada di tiap seksi halaman.
             Data dikirim ke /track/dwell setiap 45 detik atau saat
             meninggalkan halaman. Minimum 3 detik untuk menghindari noise.
        ═══════════════════════════════════════════════════════════════ --}}
        <script>
        (function () {
            'use strict';

            var ENDPOINT   = '/track/dwell';
            var MIN_SECS   = 3;
            var FLUSH_SECS = 20; // Flush setiap 20 detik secara realtime
            var THRESHOLD  = 0.4; // 40 % terlihat

            var dwellMap  = {};   // section_key => { label, seconds, lastIn }
            var pageTitle = document.title;
            var pageUrl   = window.location.pathname;
            var tabActive = document.visibilityState === 'visible';

            // -- Deteksi brand device via Client Hints API ----------------
            // Chrome 90+ Android bisa mengembalikan model asli HP (SM-A135F,
            // Redmi Note 11, dll) yang tersembunyi di UA string modern.
            var deviceHints = { brand: '', model: '', platform: '', mobile: false };
            if (navigator.userAgentData && navigator.userAgentData.getHighEntropyValues) {
                navigator.userAgentData.getHighEntropyValues(['model', 'platform', 'platformVersion', 'brands', 'mobile'])
                    .then(function (hints) {
                        var real = (hints.brands || []).filter(function (b) {
                            return !/not.?a/i.test(b.brand) && !/chromium/i.test(b.brand);
                        });
                        deviceHints = {
                            brand   : real.length ? real[0].brand : '',
                            model   : hints.model || '',
                            platform: hints.platform || '',
                            mobile  : hints.mobile || false,
                        };
                    }).catch(function () {});
            }

            // ── Kirim data ke server ───────────────────────────────────
            function flush(final) {
                var payload = [];

                Object.keys(dwellMap).forEach(function (key) {
                    var d = dwellMap[key];

                    // Bila halaman masih aktif dan timer berjalan, hitung sisa
                    if (tabActive && d.lastIn !== null) {
                        d.seconds += Math.round((Date.now() - d.lastIn) / 1000);
                        d.lastIn   = Date.now();
                    }

                    if (d.seconds >= MIN_SECS) {
                        payload.push({
                            section : key,
                            label   : d.label,
                            seconds : d.seconds,
                            page    : pageUrl,
                        });
                    }

                    // Reset hitungan setelah flush berkala (bukan flush akhir)
                    if (!final) d.seconds = 0;
                });

                if (!payload.length) return;

                var body = JSON.stringify({ items: payload, device: deviceHints });

                if (navigator.sendBeacon) {
                    var blob = new Blob([body], { type: 'application/json' });
                    navigator.sendBeacon(ENDPOINT, blob);
                } else {
                    fetch(ENDPOINT, {
                        method    : 'POST',
                        headers   : { 'Content-Type': 'application/json' },
                        body      : body,
                        keepalive : true,
                    }).catch(function () {});
                }
            }

            // ── IntersectionObserver ───────────────────────────────────
            function observeAll() {
                if (!window.IntersectionObserver) return;

                var io = new IntersectionObserver(function (entries) {
                    entries.forEach(function (entry) {
                        var el  = entry.target;
                        var key = el.getAttribute('data-track-section');
                        var lbl = el.getAttribute('data-track-label') || key;

                        if (!dwellMap[key]) {
                            dwellMap[key] = { label: lbl, seconds: 0, lastIn: null };
                        }

                        var d = dwellMap[key];

                        if (entry.isIntersecting && tabActive) {
                            if (d.lastIn === null) d.lastIn = Date.now();
                        } else {
                            if (d.lastIn !== null) {
                                d.seconds += Math.round((Date.now() - d.lastIn) / 1000);
                                d.lastIn   = null;
                            }
                        }
                    });
                }, { threshold: THRESHOLD });

                document.querySelectorAll('[data-track-section]').forEach(function (el) {
                    io.observe(el);
                });
            }

            // ── Visibilitas tab ────────────────────────────────────────
            document.addEventListener('visibilitychange', function () {
                tabActive = document.visibilityState === 'visible';

                Object.keys(dwellMap).forEach(function (key) {
                    var d = dwellMap[key];
                    if (!tabActive && d.lastIn !== null) {
                        d.seconds += Math.round((Date.now() - d.lastIn) / 1000);
                        d.lastIn   = null;
                    }
                });

                if (!tabActive) flush(false);
            });

            // ── Flush saat meninggalkan halaman ───────────────────────
            window.addEventListener('pagehide', function () { flush(true); });
            window.addEventListener('beforeunload', function () { flush(true); });

            // ── Flush berkala setiap 45 detik ─────────────────────────
            setInterval(function () { flush(false); }, FLUSH_SECS * 1000);

            // ── Mulai observasi setelah DOM siap ─────────────────────
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', observeAll);
            } else {
                observeAll();
            }
        }());
        </script>
        {{-- Skrip dari masing-masing halaman. --}}
        @stack('scripts')
    </body>
</html>
