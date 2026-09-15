<x-app-layout>
    {{-- ===== BANNER HERO (bagian atas halaman) ===== --}}
    @if($heroBanners->isNotEmpty())
        <div class="relative mb-12 shadow-sm group select-none" data-track-section="hero_banner" data-track-label="Hero Banner"
            x-data="{
                {{-- slideAktif boleh mencapai totalSlide, yaitu posisi slide kembar --}}
                slideAktif: 0,
                totalSlide: {{ $heroBanners->count() }},
                autoplayMs: 12000,
                durasiGeserMs: 1400,
                pakaiTransisi: true,
                progress: 0,
                isPaused: false,
                isDragging: false,
                dragStartX: 0,
                dragDeltaX: 0,
                timer: null,

                {{-- Slide yang sedang tampil berisi video? --}}
                slideVideo: false,

                {{-- Dipakai penjaga kemacetan di bawah untuk mengenali video
                     yang berhenti maju sama sekali. --}}
                waktuTerakhir: -1,
                diamSejak: 0,

                init() {
                    // Pewaktu berdetak tiap 80 milidetik, bukan 40.
                    const tickMs = 80;
                    this.timer = setInterval(() => {
                        if (this.isPaused || this.isDragging || this.totalSlide <= 1) return;

                        {{-- Slide video tidak dihitung mundur oleh pewaktu. --}}
                        if (this.slideVideo) {
                            const v = this.videoSlide(this.slideAktif);

                            if (v && isFinite(v.duration) && v.duration > 0) {
                                this.progress = Math.min(100, (v.currentTime / v.duration) * 100);
                            }

                            // Penjaga kemacetan: deteksi video paused ATAU buffering/loading stuck.
                            const vBerjalan = v && !v.paused && !v.ended && v.currentTime > this.waktuTerakhir;
                            if (!vBerjalan) {
                                if (this.diamSejak === 0) this.diamSejak = Date.now();

                                // Toleransi 4 detik — jika video masih macet, langsung skip.
                                if (Date.now() - this.diamSejak > 4000) {
                                    this.slideVideo = false;
                                    this.diamSejak = 0;
                                    this.next();
                                    return;
                                }
                            } else {
                                this.diamSejak = 0;
                            }

                            if (v) this.waktuTerakhir = v.currentTime;

                            return;
                        }

                        this.progress += (tickMs / this.autoplayMs) * 100;
                        if (this.progress >= 100) {
                            this.progress = 0;
                            this.next();
                        }
                    }, tickMs);

                    {{-- Setiap pergantian slide menyiapkan videonya. --}}
                    this.$watch('slideAktif', () => this.siapkanSlide());
                    this.$nextTick(() => this.siapkanSlide());
                },

                {{-- Video di dalam slide ke-i, bila ada. --}}
                videoSlide(i) {
                    {{-- Slidenya dicari dengan membandingkan atributnya, bukan --}}
                    const slides = this.$el.querySelectorAll('[data-slide]');

                    for (const s of slides) {
                        if (s.getAttribute('data-slide') === String(i)) {
                            return s.querySelector('[data-banner-video]');
                        }
                    }

                    return null;
                },

                // Menyiapkan slide yang baru tampil.
                siapkanSlide() {
                    this.$el.querySelectorAll('[data-banner-video]').forEach((v) => {
                        v.pause();
                        try { v.currentTime = 0; } catch (e) { /* metadata belum siap */ }
                    });

                    const v = this.videoSlide(this.slideAktif);
                    this.slideVideo = !!v;
                    this.progress = 0;

                    if (! v) return;

                    this.waktuTerakhir = -1;
                    this.diamSejak = 0;

                    {{-- Sedang dijeda pembaca: videonya disiapkan di detik nol --}}
                    if (this.isPaused) return;

                    // Videonya harus benar-benar diputar sampai habis, jadi penolakan pemutaran otomatis dicoba lagi da...
                    const putar = () => v.play();

                    putar().then(() => {
                        {{-- Pembaca bisa menekan Jeda sebelum play() di atas --}}
                        if (this.isPaused) v.pause();
                    }).catch(() => {
                        v.muted = true;

                        return putar().then(() => {
                            if (this.isPaused) v.pause();
                        });
                    }).catch(() => {
                        {{-- Benar-benar tidak bisa diputar: berkasnya rusak, --}}
                        this.slideVideo = false;
                    });

                    v.onerror = () => { this.slideVideo = false; };
                },

                {{-- Indeks banner yang sedang tampil (klon dihitung sebagai yang pertama) --}}
                get indeksTampil() {
                    return this.slideAktif % this.totalSlide;
                },

                {{-- Matikan transisi, pindahkan posisi, lalu nyalakan lagi. --}}
                lompatDiam(keIndeks, lanjut = null) {
                    this.pakaiTransisi = false;
                    this.slideAktif = keIndeks;

                    requestAnimationFrame(() => requestAnimationFrame(() => {
                        this.pakaiTransisi = true;
                        if (lanjut !== null) this.slideAktif = lanjut;
                    }));
                },

                next() {
                    if (this.totalSlide <= 1) return;

                    this.progress = 0;
                    this.slideAktif++;

                    // Sampai di slide kembar? Pindah diam-diam ke slide pertama
                    // setelah animasinya selesai. Dicek ulang saat waktunya tiba,
                    // sebab pengguna bisa saja menekan titik indikator atau panah
                    // di tengah animasi — jangan sampai pilihannya tertimpa.
                    if (this.slideAktif === this.totalSlide) {
                        setTimeout(() => {
                            if (this.slideAktif === this.totalSlide) this.lompatDiam(0);
                        }, this.durasiGeserMs);
                    }
                },

                prev() {
                    if (this.totalSlide <= 1) return;

                    this.progress = 0;

                    // Dari slide pertama: lompat diam-diam ke posisi klon di ujung,
                    // lalu animasikan mundur satu langkah supaya arahnya terasa wajar.
                    if (this.slideAktif === 0) {
                        this.lompatDiam(this.totalSlide, this.totalSlide - 1);
                    } else {
                        this.slideAktif--;
                    }
                },

                goTo(i) {
                    this.slideAktif = i;
                    this.progress = 0;
                },

                // Menekan cincin waktu: satu tombol, dua kelakuan.
                tekanTitik(i) {
                    if (this.indeksTampil !== i) {
                        // Berpindah banner selalu melanjutkan jalannya kembali;
                        // pengunjung yang memilih banner lain jelas ingin melihat,
                        // bukan membekukannya.
                        this.isPaused = false;
                        this.aturVideo(false);
                        this.goTo(i);
                        return;
                    }

                    this.isPaused = ! this.isPaused;
                    this.aturVideo(this.isPaused);
                },

                // Menahan atau melanjutkan video di slide yang sedang tampil.
                aturVideo(jeda) {
                    const v = this.videoSlide(this.slideAktif);
                    if (! v) return;

                    if (jeda) {
                        v.pause();
                    } else if (this.slideVideo) {
                        v.play().catch(() => {});
                    }
                },

                dragStart(e) {
                    if (this.totalSlide <= 1) return;
                    this.isDragging = true;
                    this.dragStartX = (e.touches ? e.touches[0].clientX : e.clientX);
                    this.dragDeltaX = 0;
                },
                dragMove(e) {
                    if (!this.isDragging) return;
                    const x = (e.touches ? e.touches[0].clientX : e.clientX);
                    this.dragDeltaX = x - this.dragStartX;
                },
                dragEnd() {
                    if (!this.isDragging) return;
                    if (this.dragDeltaX < -60) this.next();
                    else if (this.dragDeltaX > 60) this.prev();
                    this.isDragging = false;
                    this.dragDeltaX = 0;
                }
            }"
            x-init="init()"
            {{-- Kursor yang lewat di atas banner TIDAK lagi menghentikannya. --}}
            @mousedown="dragStart($event)" @mousemove.window="dragMove($event)" @mouseup.window="dragEnd()"
            @touchstart="dragStart($event)" @touchmove="dragMove($event)" @touchend="dragEnd()">

            {{-- Container: overflow:hidden mengkliping track yang meluap --}}
            {{-- Rasio 16:9 = ukuran HD 1920 x 1080 px, sama seperti video YouTube. --}}
            <div class="w-full overflow-hidden bg-gray-950 cursor-grab active:cursor-grabbing" style="aspect-ratio: 16/9;">

                {{-- Track: width 100% dari container, flex, geser dengan translateX. Saat drag, mengikuti jari/kursor langsung tanpa transisi --}}
                <div class="flex w-full h-full"
                    {{-- Transisi dimatikan saat digeser jari maupun saat posisi
                         dipindahkan diam-diam dari slide kembar ke slide pertama --}}
                    :style="'transform: translateX(calc(-' + (slideAktif * 100) + '% + ' + dragDeltaX + 'px)); transition: '
                        + ((isDragging || !pakaiTransisi) ? 'none' : 'transform ' + durasiGeserMs + 'ms cubic-bezier(0.4, 0, 0.2, 1)')">

                    @foreach($heroBanners as $index => $banner)
                            {{-- Setiap slide: 100% lebar container, tidak boleh menyusut --}}
                            <div style="flex: 0 0 100%; width: 100%; height: 100%;" class="overflow-hidden"
                                 data-slide="{{ $index }}">
                                @if($banner->link)
                                    <a href="{{ $banner->link }}" class="block w-full h-full" draggable="false">
                                @else
                                        <div class="w-full h-full">
                                    @endif

                                        @if(Str::endsWith($banner->image, ['.mp4', '.webm', '.ogg', '.mov']))
                                            @php
                                                $bannerExt  = strtolower(pathinfo($banner->image, PATHINFO_EXTENSION));
                                                $bannerMime = match($bannerExt) { 'mp4' => 'video/mp4', 'webm' => 'video/webm', 'ogg' => 'video/ogg', 'mov' => 'video/mp4', default => 'video/mp4' };
                                            @endphp
                                            <video muted playsinline preload="auto" @ended="next()"
                                                data-banner-video
                                                style="width:100%; height:100%; object-fit:cover; display:block;">
                                                <source src="{{ $banner->image_url }}" type="{{ $bannerMime }}">
                                            </video>
                                        @else
                                            {{-- Gambar ditampilkan apa adanya, tanpa efek zoom --}}
                                            <img src="{{ $banner->image_url }}" alt="{{ $banner->title }}" draggable="false"
                                                style="width:100%; height:100%; object-fit:cover; display:block;"
                                                onerror="this.onerror=null;this.src='https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=1400&q=80';">
                                        @endif

                                        @if($banner->link)
                                            </a>
                                        @else
                                    </div>
                                @endif
                        </div>
                    @endforeach

                    {{-- Slide kembar dari banner pertama, dipasang di ujung. --}}
                    @if($heroBanners->count() > 1)
                        @php $awal = $heroBanners->first(); @endphp
                        <div style="flex: 0 0 100%; width: 100%; height: 100%;" class="overflow-hidden"
                             data-slide="{{ $heroBanners->count() }}" aria-hidden="true">
                            @if(Str::endsWith($awal->image, ['.mp4', '.webm', '.ogg', '.mov']))
                                @php
                                    $awalExt  = strtolower(pathinfo($awal->image, PATHINFO_EXTENSION));
                                    $awalMime = match($awalExt) { 'mp4' => 'video/mp4', 'webm' => 'video/webm', 'ogg' => 'video/ogg', 'mov' => 'video/mp4', default => 'video/mp4' };
                                @endphp
                                <video muted playsinline preload="auto" @ended="next()"
                                    data-banner-video
                                    style="width:100%; height:100%; object-fit:cover; display:block;">
                                    <source src="{{ $awal->image_url }}" type="{{ $awalMime }}">
                                </video>
                            @else
                                <img src="{{ $awal->image_url }}" alt="" draggable="false"
                                    style="width:100%; height:100%; object-fit:cover; display:block;"
                                    onerror="this.onerror=null;this.src='https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=1400&q=80';">
                            @endif
                        </div>
                    @endif
            </div>
        </div>

        @if($heroBanners->count() > 1)
            {{-- Panah navigasi kiri/kanan: samar, muncul jelas saat banner dihover --}}
            <button @click="prev()" aria-label="Sebelumnya"
                class="absolute left-3 sm:left-5 top-1/2 -translate-y-1/2 z-20 h-9 w-9 sm:h-11 sm:w-11 rounded-full bg-white/15 hover:bg-white/30 backdrop-blur-sm text-white flex items-center justify-center opacity-60 sm:opacity-0 sm:group-hover:opacity-100 transition-all duration-300 hover:scale-110">
                <svg class="h-4 w-4 sm:h-5 sm:w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" />
                </svg>
            </button>
            <button @click="next()" aria-label="Berikutnya"
                class="absolute right-3 sm:right-5 top-1/2 -translate-y-1/2 z-20 h-9 w-9 sm:h-11 sm:w-11 rounded-full bg-white/15 hover:bg-white/30 backdrop-blur-sm text-white flex items-center justify-center opacity-60 sm:opacity-0 sm:group-hover:opacity-100 transition-all duration-300 hover:scale-110">
                <svg class="h-4 w-4 sm:h-5 sm:w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
                </svg>
            </button>

            {{-- Indikator bulat: lingkaran banner aktif terisi searah jarum jam --}}
            <div class="absolute bottom-4 left-0 right-0 flex justify-center items-center gap-3 z-20 px-4">
                @foreach($heroBanners as $index => $banner)
                    {{-- Ukuran tombolnya tetap 36 piksel untuk semua titik, --}}
                    {{-- $data, bukan sekadar tekanTitik(...). --}}
                    <button @click="$data.tekanTitik({{ $index }})"
                        class="relative flex items-center justify-center w-9 h-9 transition-transform duration-300 hover:scale-110"
                        :aria-label="indeksTampil === {{ $index }}
                            ? (isPaused ? 'Lanjutkan banner' : 'Jeda banner')
                            : 'Ke banner {{ $index + 1 }}'"
                        :title="indeksTampil === {{ $index }}
                            ? (isPaused ? 'Lanjutkan' : 'Jeda')
                            : 'Banner {{ $index + 1 }}'">

                        {{-- Banner tidak aktif: titik kecil polos --}}
                        <span x-show="indeksTampil !== {{ $index }}"
                            class="w-3 h-3 rounded-full bg-white/45 hover:bg-white/70 transition-colors duration-300"></span>

                        {{-- Banner aktif: cincin waktu --}}
                        <svg x-show="indeksTampil === {{ $index }}" class="w-9 h-9 -rotate-90" viewBox="0 0 28 28">
                            {{-- Cincin latar --}}
                            <circle cx="14" cy="14" r="11" fill="none" stroke="rgba(255,255,255,0.3)" stroke-width="2.5" />
                            {{-- Cincin progres: keliling lingkaran = 2 x pi x 11 = 69.12 --}}
                            <circle cx="14" cy="14" r="11" fill="none" stroke="#ffffff" stroke-width="2.5"
                                stroke-linecap="round" stroke-dasharray="69.12"
                                :stroke-dashoffset="69.12 - (69.12 * progress / 100)"
                                :style="isDragging || isPaused ? 'transition: none' : 'transition: stroke-dashoffset 90ms linear'" />

                            {{-- Tengahnya: titik saat berjalan, dua palang saat --}}
                            <circle cx="14" cy="14" r="3.5" fill="#ffffff" x-show="!isPaused" />

                            <g x-show="isPaused" x-cloak class="rotate-90" style="transform-origin: 14px 14px;">
                                <rect x="11" y="10" width="2" height="8" rx="1" fill="#ffffff" />
                                <rect x="15" y="10" width="2" height="8" rx="1" fill="#ffffff" />
                            </g>
                        </svg>
                    </button>
                @endforeach
            </div>
        @endif
        </div>
    @else
        {{-- Tampilan default kalau belum ada banner di database --}}
        <div class="relative overflow-hidden mb-12 shadow-sm">
            <div class="relative w-full bg-gray-950" style="aspect-ratio: 16/9;">
                <a href="{{ route('products.index') }}" class="block w-full h-full">
                    <img src="https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=1400&q=80" alt="Sepatu Record"
                        class="w-full h-full object-cover">
                </a>
            </div>
        </div>
    @endif

    {{-- ===== BARISAN KATEGORI BERJALAN (selebar layar penuh) ===== --}}
    {{-- Diambil langsung dari kategori aktif di database, jadi kategori yang --}}
    @if($categories->isNotEmpty())
        @php
            // Satu grup diulang secukupnya supaya layar lebar tidak berlubang
            // walau kategorinya baru sedikit.
            $ulang = max(1, (int) ceil(10 / $categories->count()));
            // Satu putaran penuh = 45 detik, sesuai permintaan.
            // Catatan: karena dipatok waktu (bukan per kartu), menambah kategori
            // membuat barisannya terasa lebih cepat karena jarak yang ditempuh
            // bertambah dalam waktu yang sama.
            $durasi = 45;
        @endphp

        <section class="w-full pt-4 pb-16" data-track-section="kategori" data-track-label="Kategori Produk">
            <div class="text-center mb-8 px-4">
                <h2 class="text-2xl font-black text-primary uppercase tracking-wider">Kategori</h2>
                <div class="h-1 w-20 bg-accent mx-auto mt-2"></div>
            </div>

            {{-- Pembungkus: menyembunyikan bagian yang meluap + memudar di tepi --}}
            <div class="relative w-full overflow-hidden py-1 group/marquee">

                {{-- Sengaja TIDAK memakai gap-6. --}}
                <div class="marquee-track"
                    style="--durasi: {{ $durasi }}s;">

                    {{-- Dua grup identik. --}}
                    @for ($grup = 0; $grup < 2; $grup++)
                        @for ($k = 0; $k < $ulang; $k++)
                            @foreach($categories as $category)
                                <a href="{{ route('categories.show', $category->slug) }}"
                                    aria-hidden="{{ $grup === 1 ? 'true' : 'false' }}"
                                    tabindex="{{ $grup === 1 ? '-1' : '0' }}"
                                    {{-- Ukuran & jarak ditulis lewat CSS sendiri (lihat blok <style>), --}}
                                    class="kartu-kategori group">

                                    {{-- Lapisan gambar. Efek hover dipisah agar tidak bentrok
                                         dengan transform milik pengaturan posisi gambar --}}
                                    <div class="absolute inset-0 overflow-hidden">
                                        <div class="kategori-zoom w-full h-full">
                                            @if($category->image_url)
                                                {{-- Posisi & perbesaran mengikuti pengaturan admin di Seller Center --}}
                                                <img src="{{ $category->image_url }}" alt="{{ $category->name }}"
                                                    loading="lazy" draggable="false"
                                                    class="w-full h-full"
                                                    style="{{ $category->image_style }}">
                                            @else
                                                {{-- Kategori tanpa gambar: inisial besar, terlihat disengaja --}}
                                                <div class="kategori-kosong">
                                                    <span>{{ mb_strtoupper(mb_substr($category->name, 0, 1)) }}</span>
                                                </div>
                                            @endif
                                        </div>
                                    </div>

                                    {{-- Peneduh: pekat di bawah, bening di atas — teks tetap terbaca
                                         di atas foto seramai apa pun --}}
                                    <div class="kategori-peneduh"></div>

                                    {{-- Isi kartu. --}}
                                    <div class="kategori-isi">
                                        {{-- Garis aksen tipis, memanjang saat kursor lewat --}}
                                        <span class="kategori-garis"></span>

                                        <h3 class="kategori-nama">{{ $category->name }}</h3>

                                        <div class="kategori-baris">
                                            {{-- Kategori yang belum berisi produk tidak menampilkan
                                                 angka 0, cukup ajakan netral --}}
                                            <span class="kategori-jumlah">
                                                @if($category->active_products_count > 0)
                                                    {{ $category->active_products_count }} Produk
                                                @else
                                                    Lihat Koleksi
                                                @endif
                                            </span>

                                            <span class="kategori-lihat">
                                                Lihat
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
                                                </svg>
                                            </span>
                                        </div>
                                    </div>
                                </a>
                            @endforeach
                        @endfor
                    @endfor
                </div>

                {{-- Pudar di kiri & kanan supaya kartu masuk/keluar tidak terpotong kaku --}}
                <div class="pointer-events-none absolute inset-y-0 left-0 w-16 sm:w-28 z-10"
                    style="background: linear-gradient(to right, var(--color-bg, #fff), transparent);"></div>
                <div class="pointer-events-none absolute inset-y-0 right-0 w-16 sm:w-28 z-10"
                    style="background: linear-gradient(to left, var(--color-bg, #fff), transparent);"></div>
            </div>
        </section>

        @once
            @push('styles')
                <style>
                    /* ── Kartu kategori ────────────────────────────────────── Ditulis sebagai CSS biasa, bukan kelas T... */
                    .kartu-kategori {
                        position: relative;
                        flex: 0 0 auto;
                        width: 260px;
                        aspect-ratio: 1 / 1;   /* persegi, semua kartu sama */
                        margin-right: 20px;    /* jarak antar kartu */
                        overflow: hidden;
                        border-radius: 16px;
                        background: #0f172a;
                        box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
                        transition: box-shadow 300ms ease;
                    }
                    @media (min-width: 640px) {
                        .kartu-kategori { width: 300px; }
                    }
                    .kartu-kategori:hover {
                        box-shadow: 0 20px 25px -5px rgb(0 0 0 / 0.2), 0 8px 10px -6px rgb(0 0 0 / 0.2);
                    }

                    /* Foto membesar halus saat kursor lewat */
                    .kategori-zoom {
                        transition: transform 700ms cubic-bezier(0.22, 1, 0.36, 1);
                    }
                    .kartu-kategori:hover .kategori-zoom { transform: scale(1.05); }

                    /* Kategori yang belum punya foto: gradasi + inisial besar */
                    .kategori-kosong {
                        width: 100%;
                        height: 100%;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        background: linear-gradient(135deg, #334155 0%, #1e293b 50%, #0f172a 100%);
                    }
                    .kategori-kosong span {
                        font-size: 72px;
                        font-weight: 900;
                        line-height: 1;
                        color: rgb(255 255 255 / 0.10);
                        user-select: none;
                    }

                    /* Peneduh gelap agar teks terbaca di atas foto seramai apa pun */
                    .kategori-peneduh {
                        position: absolute;
                        inset: 0;
                        background: linear-gradient(to top,
                            rgb(2 6 23 / 0.96) 0%,
                            rgb(2 6 23 / 0.55) 38%,
                            rgb(2 6 23 / 0) 72%);
                    }

                    .kategori-isi {
                        position: absolute;
                        left: 0; right: 0; bottom: 0;
                        padding: 20px;
                    }

                    .kategori-garis {
                        display: block;
                        height: 3px;
                        width: 36px;
                        margin-bottom: 12px;
                        border-radius: 9999px;
                        background: var(--color-accent, #DC2626);
                        transition: width 300ms ease;
                    }
                    .kartu-kategori:hover .kategori-garis { width: 64px; }

                    .kategori-nama {
                        color: #fff;
                        font-weight: 900;
                        font-size: 18px;
                        line-height: 1.3;
                        text-transform: uppercase;
                        letter-spacing: 0.02em;
                        /* Nama panjang dipotong 2 baris agar tinggi kartu tetap seragam */
                        display: -webkit-box;
                        -webkit-line-clamp: 2;
                        -webkit-box-orient: vertical;
                        overflow: hidden;
                    }

                    .kategori-baris {
                        display: flex;
                        align-items: center;
                        justify-content: space-between;
                        gap: 12px;
                        margin-top: 8px;
                    }

                    .kategori-jumlah {
                        font-size: 11px;
                        font-weight: 600;
                        letter-spacing: 0.02em;
                        color: rgb(255 255 255 / 0.6);
                    }

                    .kategori-lihat {
                        display: inline-flex;
                        align-items: center;
                        gap: 4px;
                        font-size: 11px;
                        font-weight: 700;
                        color: #fff;
                        opacity: 0;
                        transform: translateX(-8px);
                        transition: opacity 300ms ease, transform 300ms ease;
                        white-space: nowrap;
                    }
                    .kategori-lihat svg { height: 14px; width: 14px; }
                    .kartu-kategori:hover .kategori-lihat {
                        opacity: 1;
                        transform: translateX(0);
                    }

                    /* ── Barisan berjalan ──────────────────────────────────── Bergulir dari kanan ke kiri tanpa henti. */
                    @keyframes marquee-ke-kiri {
                        from { transform: translate3d(0, 0, 0); }
                        to   { transform: translate3d(-50%, 0, 0); }
                    }

                    .marquee-track {
                        display: flex;
                        /* max-content wajib: lebar track harus mengikuti isinya, karena animasi menggeser tepat 50% dari le... */
                        width: max-content;
                        animation: marquee-ke-kiri var(--durasi, 40s) linear infinite;
                        will-change: transform;
                    }

                    /* Berhenti sejenak saat kursor di atasnya agar mudah diklik */
                    .group\/marquee:hover .marquee-track {
                        animation-play-state: paused;
                    }

                    /* Hormati pengguna yang mematikan animasi di perangkatnya */
                    @media (prefers-reduced-motion: reduce) {
                        .marquee-track {
                            animation: none;
                            transform: none;
                        }
                    }
                </style>
            @endpush
        @endonce
    @endif

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-16 pb-16">

        {{-- ===== KOLEKSI UNGGULAN ===== --}}
        @if($featuredProducts->isNotEmpty())
            <section data-track-section="our_collection" data-track-label="Koleksi Unggulan (Our Collection)">
                <div class="text-center mb-8">
                    <h2 class="text-2xl font-black text-primary uppercase tracking-wider">Our Collection</h2>
                    <div class="h-1 w-20 bg-accent mx-auto mt-2"></div>
                </div>

                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3 sm:gap-6">
                    @foreach($featuredProducts as $product)
                        <x-product-card :product="$product" />
                    @endforeach
                </div>
            </section>
        @endif

        {{-- ═══════════ PROGRAM AFFILIATE ═══════════ --}}
        @php
            $persenDiskon = (int) config('referal.persen_diskon', 3);
            $persenKomisi = (int) config('referal.persen_komisi', 3);
        @endphp

        <section class="afil" aria-labelledby="afil-judul" data-track-section="affiliate_home" data-track-label="Program Affiliate (Beranda)">
            {{-- Lapisan dekoratif; tidak dibacakan pembaca layar --}}
            <span class="afil-cahaya afil-cahaya-1" aria-hidden="true"></span>
            <span class="afil-cahaya afil-cahaya-2" aria-hidden="true"></span>
            <span class="afil-kilau" aria-hidden="true"></span>

            <div class="afil-isi">

                {{-- ── Ajakan ── --}}
                <div class="afil-kepala">
                    <span class="afil-lencana">
                        <span class="afil-titik"></span>
                        Program Affiliate
                    </span>

                    <h3 id="afil-judul" class="afil-judul">
                        Belanja Sekali, Cuan Berkali-kali
                    </h3>

                    <p class="afil-sub">
                        Setiap pesananmu yang selesai menghasilkan satu kode referal.
                        Bagikan ke teman — mereka hemat, kamu dapat komisi.
                        Tanpa modal, tanpa stok, tanpa repot kirim barang.
                    </p>
                </div>

                {{-- ── Tiga langkah ── --}}
                <ol class="afil-langkah">
                    <li class="afil-langkah-item">
                        <span class="afil-nomor">1</span>
                        <div>
                            <p class="afil-langkah-judul">Belanja &amp; Bayar</p>
                            <p class="afil-langkah-teks">
                                Checkout seperti biasa, lalu selesaikan pembayarannya.
                            </p>
                        </div>
                    </li>

                    <li class="afil-panah" aria-hidden="true"><i class="fa-solid fa-arrow-right"></i></li>

                    <li class="afil-langkah-item">
                        <span class="afil-nomor">2</span>
                        <div>
                            <p class="afil-langkah-judul">Pesanan Diterima</p>
                            <p class="afil-langkah-teks">
                                Begitu pesanan selesai, kode referalmu langsung terbit.
                            </p>
                        </div>
                    </li>

                    <li class="afil-panah" aria-hidden="true"><i class="fa-solid fa-arrow-right"></i></li>

                    <li class="afil-langkah-item">
                        <span class="afil-nomor">3</span>
                        <div>
                            <p class="afil-langkah-judul">Bagikan Kodenya</p>
                            <p class="afil-langkah-teks">
                                Teman memasukkannya saat checkout, komisi masuk ke R_Pay-mu.
                            </p>
                        </div>
                    </li>
                </ol>

                <a href="{{ route('affiliate') }}" class="afil-tombol">
                    <span>Lihat Panduan Lengkap</span>
                    <i class="fa-solid fa-arrow-right"></i>
                </a>

                <p class="afil-catatan">
                    Kode berlaku selama pesananmu tidak dibatalkan.
                </p>
            </div>
        </section>

        {{-- ===== PRODUK TERBARU ===== --}}
        <section data-track-section="new_arrivals" data-track-label="Produk Terbaru (New Arrivals)">
            <div class="text-center mb-8">
                <h2 class="text-2xl font-black text-primary uppercase tracking-wider">New Arrivals</h2>
                <div class="h-1 w-20 bg-accent mx-auto mt-2"></div>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3 sm:gap-6">
                @forelse($newArrivals as $product)
                    <x-product-card :product="$product" />
                @empty
                    <p class="col-span-full text-center text-text-light py-8">Belum ada produk baru.</p>
                @endforelse
            </div>
        </section>
    </div>

    @push('styles')
    <style>
        /* ══════════ Program Affiliate ══════════ Ditulis sebagai CSS sendiri, bukan kelas Tailwind arbitra... */
        .afil {
            position: relative;
            overflow: hidden;
            border-radius: 20px;
            background: linear-gradient(135deg, #14264a 0%, #1B3A6B 45%, #24528f 100%);
            padding: 40px 24px;
            isolation: isolate;
        }
        @media (min-width: 768px) { .afil { padding: 52px 48px; } }

        /* Dua bola cahaya yang bergerak pelan — memberi kesan hidup tanpa mengganggu keterbacaan teks di at... */
        .afil-cahaya {
            position: absolute;
            border-radius: 50%;
            filter: blur(60px);
            opacity: .5;
            z-index: -1;
            pointer-events: none;
        }
        .afil-cahaya-1 {
            width: 320px; height: 320px;
            background: rgb(233 69 47 / .55);
            top: -120px; right: -80px;
            animation: afil-apung 14s ease-in-out infinite;
        }
        .afil-cahaya-2 {
            width: 260px; height: 260px;
            background: rgb(56 130 246 / .45);
            bottom: -110px; left: -70px;
            animation: afil-apung 18s ease-in-out infinite reverse;
        }
        @keyframes afil-apung {
            0%, 100% { transform: translate(0, 0) scale(1); }
            50%      { transform: translate(28px, -22px) scale(1.12); }
        }

        /* Kilau yang menyapu sekali setiap beberapa detik. */
        .afil-kilau {
            position: absolute; inset: 0; z-index: -1; pointer-events: none;
            background: linear-gradient(105deg,
                transparent 38%, rgb(255 255 255 / .07) 48%,
                rgb(255 255 255 / .13) 52%, transparent 62%);
            background-size: 260% 100%;
            animation: afil-sapu 7s ease-in-out infinite;
        }
        @keyframes afil-sapu {
            0%, 62%  { background-position: 190% 0; }
            100%     { background-position: -70% 0; }
        }

        .afil-isi { position: relative; display: grid; gap: 26px; }

        /* ── Ajakan ── */
        .afil-kepala { text-align: center; max-width: 620px; margin: 0 auto; }

        .afil-lencana {
            display: inline-flex; align-items: center; gap: 7px;
            padding: 6px 14px; border-radius: 999px;
            background: rgb(255 255 255 / .1);
            border: 1px solid rgb(255 255 255 / .18);
            color: #fff; font-size: 10px; font-weight: 900;
            letter-spacing: .12em; text-transform: uppercase;
            backdrop-filter: blur(4px);
        }
        .afil-titik {
            width: 6px; height: 6px; border-radius: 50%;
            background: #4ade80; box-shadow: 0 0 0 0 rgb(74 222 128 / .7);
            animation: afil-denyut 2s ease-out infinite;
        }
        @keyframes afil-denyut {
            0%   { box-shadow: 0 0 0 0 rgb(74 222 128 / .7); }
            70%  { box-shadow: 0 0 0 8px rgb(74 222 128 / 0); }
            100% { box-shadow: 0 0 0 0 rgb(74 222 128 / 0); }
        }

        .afil-judul {
            margin-top: 16px;
            font-size: 24px; font-weight: 900; line-height: 1.2;
            color: #fff; letter-spacing: -.01em;
        }
        @media (min-width: 768px) { .afil-judul { font-size: 32px; } }

        .afil-sub {
            margin-top: 12px;
            font-size: 13px; line-height: 1.75;
            color: rgb(255 255 255 / .72);
        }

        /* ── Tiga langkah ── */
        .afil-langkah {
            list-style: none; margin: 0; padding: 0;
            display: grid; gap: 12px;
            max-width: 940px; margin-inline: auto; width: 100%;
        }
        @media (min-width: 900px) {
            .afil-langkah {
                grid-template-columns: 1fr auto 1fr auto 1fr;
                align-items: stretch; gap: 10px;
            }
        }

        .afil-langkah-item {
            display: flex; gap: 13px; align-items: flex-start;
            padding: 16px 18px; border-radius: 14px;
            background: rgb(255 255 255 / .07);
            border: 1px solid rgb(255 255 255 / .12);
            backdrop-filter: blur(6px);
            transition: background-color 260ms ease, transform 260ms ease, border-color 260ms ease;
        }
        .afil-langkah-item:hover {
            background: rgb(255 255 255 / .12);
            border-color: rgb(255 255 255 / .24);
            transform: translateY(-3px);
        }

        .afil-nomor {
            flex-shrink: 0;
            width: 28px; height: 28px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            background: #E9452F; color: #fff;
            font-size: 12px; font-weight: 900;
            box-shadow: 0 3px 10px rgb(233 69 47 / .45);
        }

        .afil-langkah-judul { font-size: 13px; font-weight: 800; color: #fff; }
        .afil-langkah-teks {
            font-size: 11.5px; line-height: 1.6; margin-top: 3px;
            color: rgb(255 255 255 / .62);
        }

        .afil-panah {
            display: none;
            align-items: center; justify-content: center;
            color: rgb(255 255 255 / .3); font-size: 13px;
        }
        @media (min-width: 900px) {
            .afil-panah { display: flex; animation: afil-geser 1.9s ease-in-out infinite; }
        }
        @keyframes afil-geser {
            0%, 100% { transform: translateX(0); opacity: .3; }
            50%      { transform: translateX(5px); opacity: .65; }
        }

        /* ── Sama-sama untung ── */
        .afil-untung {
            display: grid; gap: 12px; align-items: center;
            max-width: 640px; margin-inline: auto; width: 100%;
            grid-template-columns: 1fr;
        }
        @media (min-width: 560px) {
            .afil-untung { grid-template-columns: 1fr auto 1fr; }
        }

        .afil-kartu {
            text-align: center; padding: 20px 16px; border-radius: 16px;
            background: rgb(255 255 255 / .95);
            box-shadow: 0 10px 26px rgb(0 0 0 / .18);
            transition: transform 300ms cubic-bezier(.2, .8, .3, 1);
        }
        .afil-kartu:hover { transform: translateY(-5px) scale(1.02); }

        .afil-kartu-ikon {
            width: 38px; height: 38px; margin: 0 auto 10px;
            border-radius: 12px; display: flex; align-items: center; justify-content: center;
            background: #fff1ef; color: #E9452F; font-size: 15px;
        }
        .afil-kartu-nilai {
            font-size: 30px; font-weight: 900; line-height: 1;
            color: #1B3A6B; letter-spacing: -.02em;
        }
        .afil-kartu-label {
            font-size: 10.5px; font-weight: 700; line-height: 1.5;
            color: #6b7280; margin-top: 7px;
        }

        .afil-plus {
            display: flex; align-items: center; justify-content: center;
            width: 42px; height: 42px; margin-inline: auto;
            border-radius: 50%; background: rgb(255 255 255 / .12);
            border: 1px solid rgb(255 255 255 / .2);
            color: #fff; font-size: 15px;
            animation: afil-detak 2.6s ease-in-out infinite;
        }
        @keyframes afil-detak {
            0%, 100% { transform: scale(1); }
            50%      { transform: scale(1.11); }
        }

        /* ── Tombol ── */
        .afil-tombol {
            justify-self: center;
            display: inline-flex; align-items: center; gap: 10px;
            padding: 14px 30px; border-radius: 999px;
            background: #E9452F; color: #fff;
            font-size: 12.5px; font-weight: 900;
            letter-spacing: .06em; text-transform: uppercase;
            box-shadow: 0 8px 22px rgb(233 69 47 / .42);
            transition: transform 220ms ease, box-shadow 220ms ease, background-color 220ms ease;
        }
        .afil-tombol:hover {
            background: #d13c28;
            transform: translateY(-2px);
            box-shadow: 0 12px 30px rgb(233 69 47 / .55);
        }
        .afil-tombol:active { transform: translateY(0); }
        .afil-tombol i { transition: transform 220ms ease; }
        .afil-tombol:hover i { transform: translateX(4px); }

        .afil-catatan {
            text-align: center; font-size: 10.5px;
            color: rgb(255 255 255 / .45);
        }

        /* Hormati pengguna yang mematikan animasi di pengaturan sistemnya — gerakan terus-menerus bisa memi... */
        @media (prefers-reduced-motion: reduce) {
            .afil-cahaya, .afil-kilau, .afil-titik, .afil-panah, .afil-plus {
                animation: none;
            }
            .afil-langkah-item, .afil-kartu, .afil-tombol { transition: none; }
        }
    </style>
    @endpush
</x-app-layout>
