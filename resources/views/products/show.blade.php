<x-app-layout>
    <x-slot name="title">
        {{ $product->name }}
    </x-slot>

    @php
        // Galeri umum: foto utama + foto tanpa penanda warna
        $galeriUmum = collect();
        if (! empty($product->image_url)) {
            $galeriUmum->push($product->image_url);
        }
        foreach ($product->images->whereNull('color') as $img) {
            if (! empty($img->image_url)) {
                $galeriUmum->push($img->image_url);
            }
        }
        $galeriUmum = $galeriUmum->unique()->values();

        // Foto khusus tiap warna, dipakai untuk mengganti galeri saat
        // pembeli memilih warna tertentu
        $galeriWarna = $product->images
            ->whereNotNull('color')
            ->groupBy('color')
            ->map(fn ($grup) => $grup->pluck('image_url')->filter()->unique()->values()->all())
            ->all();
    
        // Countdown diskon: ambil ends_at dari activeDiscount produk
        $discountEndsAt          = $product->activeDiscount?->ends_at;
        $discountEndsAtTimestamp = $discountEndsAt ? $discountEndsAt->timestamp : null;
    @endphp

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8" x-data="{
            galeriUmum: {{ Js::from($galeriUmum) }},
            {{-- Dijadikan objek agar pencarian galeriWarna[warna] selalu
                 bekerja, termasuk saat produk belum punya foto per warna --}}
            galeriWarna: {{ Js::from((object) $galeriWarna) }},

            {{-- Foto warna yang sedang dipilih (bisa kosong) --}}
            get fotoWarnaTerpilih() {
                const khusus = this.galeriWarna[this.selectedColor];
                return (khusus && khusus.length > 0) ? khusus : [];
            },

            {{-- Daftar foto yang tampil: foto warna terpilih ditaruh paling --}}
            get galeri() {
                return [...this.fotoWarnaTerpilih, ...this.galeriUmum];
            },

            {{-- Menandai thumbnail mana yang merupakan foto warna --}}
            fotoIniWarna(url) {
                return this.fotoWarnaTerpilih.includes(url);
            },

            {{-- Foto besar melompat ke foto warna begitu warna berganti --}}
            segarkanGaleri() {
                const khusus = this.fotoWarnaTerpilih;

                if (khusus.length > 0) {
                    this.activeImage = khusus[0];
                    return;
                }

                // Warna tanpa foto khusus: pastikan yang tampil masih ada di daftar
                if (this.galeriUmum.length > 0 && ! this.galeriUmum.includes(this.activeImage)) {
                    this.activeImage = this.galeriUmum[0];
                }
            },

            activeImage: '{{ $product->image_url }}',
            selectedSize: '',
            selectedColor: '',
            quantity: 1,
            maxStock: {{ $product->stock }},
            variants: {{ json_encode($product->variants) }},
            variantId: '',
            variantStock: 0,

            // Price display state
            addingToCart: false,
            addedToCart: false,

            baseProductPrice: {{ (float) $product->price }},
            currentPrice: {{ (float) $product->effective_price }},
            currentOriginalPrice: {{ (float) $product->base_price }},
            currentDiscountPct: {{ (int) $product->discount_percentage }},

            // Per-variant discount data keyed by variant id
            variantDiscounts: {
                @foreach($product->variants as $v)
                    {{ $v->id }}: {
                        effectivePct: {{ $v->effective_discount_pct }},
                        basePrice: {{ (float) $v->final_price }},
                        effectivePrice: {{ (float) $v->effective_price }},
                    },
                @endforeach
            },

            // Product-level discount fallback
            productDiscountPct: {{ (int) $product->discount_percentage }},
            productBasePrice: {{ (float) $product->base_price }},
            productEffectivePrice: {{ (float) $product->effective_price }},

            // Formatted price display helpers
            formatRp(n) {
                return 'Rp ' + Math.round(n).toLocaleString('id-ID');
            },

            // Ketersediaan stok per pilihan.
            stokUkuran(ukuran) {
                return this.variants
                    .filter(v => String(v.size) === String(ukuran)
                        && (!this.selectedColor || v.color === this.selectedColor))
                    .reduce((jumlah, v) => jumlah + Number(v.stock || 0), 0);
            },

            stokWarna(warna) {
                return this.variants
                    .filter(v => v.color === warna)
                    .reduce((jumlah, v) => jumlah + Number(v.stock || 0), 0);
            },

            get stokTotal() {
                if (this.variants.length === 0) return Number(this.maxStock);

                return this.variants.reduce((jumlah, v) => jumlah + Number(v.stock || 0), 0);
            },

            // Tombol beli hanya hidup bila kombinasi yang dipilih benar-benar
            // ada stoknya — bukan sekadar karena variannya sudah terpilih.
            get siapBeli() {
                if (this.variants.length === 0) return Number(this.maxStock) > 0;

                return Boolean(this.variantId) && Number(this.variantStock) > 0;
            },

            // Ukuran yang tadinya dipilih bisa saja habis pada warna yang baru,
            // jadi pilihannya dilepas supaya tidak tertinggal dalam keadaan
            // terpilih tetapi tidak bisa dibeli.
            pilihWarna() {
                if (this.selectedSize && this.stokUkuran(this.selectedSize) === 0) {
                    this.selectedSize = '';
                }

                this.updateVariant();
            },

            // Method to update variant info when size/color changes
            updateVariant() {
                this.variantId = '';
                this.variantStock = 0;

                // Foto ikut menyesuaikan warna yang dipilih
                this.segarkanGaleri();

                if (this.selectedSize && this.selectedColor) {
                    const match = this.variants.find(v => v.size === this.selectedSize && v.color === this.selectedColor);
                    if (match) {
                        this.variantId = match.id;
                        this.variantStock = match.stock;
                        this.maxStock = match.stock;
                        if (this.quantity > this.maxStock) {
                            this.quantity = this.maxStock;
                        }

                        // Update price based on variant discount
                        const vd = this.variantDiscounts[match.id];
                        if (vd) {
                            this.currentPrice = vd.effectivePrice;
                            this.currentOriginalPrice = vd.basePrice;
                            this.currentDiscountPct = vd.effectivePct;
                        }
                    }
                } else if (this.selectedColor) {
                    // Fallback to max stock of this color
                    const matchColor = this.variants.filter(v => v.color === this.selectedColor);
                    this.maxStock = matchColor.reduce((acc, curr) => acc + curr.stock, 0);
                    // Reset to product-level price
                    this.currentPrice = this.productEffectivePrice;
                    this.currentOriginalPrice = this.productBasePrice;
                    this.currentDiscountPct = this.productDiscountPct;
                } else {
                    this.maxStock = {{ $product->stock }};
                    this.currentPrice = this.productEffectivePrice;
                    this.currentOriginalPrice = this.productBasePrice;
                    this.currentDiscountPct = this.productDiscountPct;
                }
            }
        }">

        <!-- Breadcrumbs -->
        <nav class="flex text-xs font-semibold text-text-light uppercase tracking-wider mb-6">
            <a href="{{ route('home') }}" class="hover:text-primary transition">Beranda</a>
            <span class="mx-2 text-gray-400">/</span>
            <a href="{{ route('categories.show', $product->category->slug) }}"
                class="hover:text-primary transition">{{ $product->category->name }}</a>
            <span class="mx-2 text-gray-400">/</span>
            <span class="text-primary truncate">{{ $product->name }}</span>
        </nav>

        <div
            class="grid grid-cols-1 lg:grid-cols-12 gap-12 bg-white border border-border p-6 sm:p-8 rounded-sm shadow-sm">
            <!-- Bagian Kiri: Galeri (lg:col-span-7) -->
            <div class="lg:col-span-7 space-y-4" data-track-section="product_gallery" data-track-label="Galeri & Foto Produk">
                <!-- Tampilkan gambar utama (Full container box) -->
                <div
                    class="border border-gray-200 bg-white flex items-center justify-center rounded-lg aspect-square overflow-hidden shadow-sm relative group p-1 sm:p-2">
                    <img :src="activeImage" alt="{{ $product->name }}" data-product-main-image
                        class="object-contain w-full h-full rounded-md transition-all duration-300 transform group-hover:scale-105"
                        onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=700&q=80';">
                </div>

                <!-- Thumbnail: foto warna terpilih di depan, lalu foto katalog -->
                <div class="grid grid-cols-5 gap-2.5 pt-1">
                    <template x-for="(imgUrl, index) in galeri" :key="imgUrl">
                        <button type="button" @click="activeImage = imgUrl"
                            class="thumb-produk"
                            :class="activeImage === imgUrl ? 'thumb-aktif' : ''">
                            <img :src="imgUrl" :alt="'Foto ' + (index + 1)"
                                onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=100&q=80';">

                            {{-- Penanda kecil bahwa ini foto warna, bukan foto katalog --}}
                            <span class="thumb-tanda" x-show="fotoIniWarna(imgUrl)" x-cloak>
                                <i class="fa-solid fa-palette"></i>
                            </span>
                        </button>
                    </template>
                </div>

                {{-- Keterangan foto yang sedang tampil --}}
                <p class="thumb-info">
                    <template x-if="fotoIniWarna(activeImage)">
                        <span>
                            <i class="fa-solid fa-palette"></i>
                            Foto warna <strong x-text="selectedColor"></strong>
                        </span>
                    </template>
                    <template x-if="!fotoIniWarna(activeImage)">
                        <span>
                            <i class="fa-regular fa-images"></i>
                            Foto katalog produk
                            <template x-if="selectedColor && fotoWarnaTerpilih.length > 0">
                                <span>— klik thumbnail bertanda <i class="fa-solid fa-palette"></i> untuk melihat warna <strong x-text="selectedColor"></strong></span>
                            </template>
                        </span>
                    </template>
                </p>

                @push('styles')
                    <style>
                        /* Thumbnail galeri produk */
                        .thumb-produk {
                            position: relative;
                            aspect-ratio: 1 / 1;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            padding: 4px;
                            background: #fff;
                            border: 2px solid #e5e7eb;
                            border-radius: 8px;
                            overflow: hidden;
                            cursor: pointer;
                            opacity: 0.8;
                            transition: border-color 180ms ease, opacity 180ms ease, transform 180ms ease;
                        }
                        .thumb-produk:hover { opacity: 1; border-color: rgb(27 58 107 / 0.5); }
                        .thumb-produk img {
                            width: 100%;
                            height: 100%;
                            object-fit: contain;
                            border-radius: 5px;
                        }
                        .thumb-aktif {
                            opacity: 1;
                            border-color: var(--color-primary, #1B3A6B);
                            box-shadow: 0 0 0 3px rgb(27 58 107 / 0.15);
                            transform: scale(1.05);
                        }

                        /* Tanda pembeda foto warna */
                        .thumb-tanda {
                            position: absolute;
                            top: 3px;
                            left: 3px;
                            width: 16px;
                            height: 16px;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            font-size: 8px;
                            color: #fff;
                            background: var(--color-primary, #1B3A6B);
                            border-radius: 4px;
                        }

                        .thumb-info {
                            padding-top: 6px;
                            font-size: 11px;
                            text-align: center;
                            color: #6b7280;
                        }
                        .thumb-info i { color: #9ca3af; margin-right: 4px; }
                        .thumb-info strong { color: var(--color-primary, #1B3A6B); }
                    </style>
                @endpush
            </div>

            <!-- Bagian Kanan: Detail & Pemesanan (lg:col-span-5) -->
            <div class="lg:col-span-5 space-y-6" data-track-section="product_variants" data-track-label="Pilihan Varian & Pemesanan">
                <div>
                    <span
                        class="bg-primary/10 text-primary text-[10px] font-black px-2.5 py-1 uppercase rounded-sm tracking-wider">
                        {{ $product->category->name }}
                    </span>
                    <h1 class="text-2xl font-black text-primary uppercase mt-3 leading-tight">
                        {{ $product->name }}
                    </h1>
                </div>

                <!-- Tampilkan harga (reactive) -->
                <div class="flex items-center gap-3 border-y border-border py-4">
                    {{-- Show discount when there is one --}}
                    <template x-if="currentDiscountPct > 0">
                        <div class="flex items-center gap-3">
                            <span class="text-sm text-text-light line-through"
                                x-text="formatRp(currentOriginalPrice)"></span>
                            <span class="text-2xl font-black text-accent" x-text="formatRp(currentPrice)"></span>
                            <span class="bg-accent/10 text-accent text-[10px] font-bold px-2 py-0.5 rounded-sm"
                                x-text="'HEMAT ' + currentDiscountPct + '%'"></span>
                        </div>
                    </template>
                    <template x-if="currentDiscountPct <= 0">
                        <span class="text-2xl font-black text-text" x-text="formatRp(currentPrice)"></span>
                    </template>
                </div>

                {{-- Countdown berakhirnya diskon — hanya tampil jika diskon punya ends_at --}}
                @if($discountEndsAtTimestamp && $product->hasDiscount())
                    <div
                        x-data="discountCountdown({{ $discountEndsAtTimestamp }})"
                        x-init="init()"
                        x-show="!expired && currentDiscountPct > 0"
                        x-cloak
                        class="flex flex-wrap items-center gap-2 px-3.5 py-2.5 rounded-md bg-red-50 border border-red-200 mb-4"
                    >
                        <div class="flex items-center gap-1.5 shrink-0">
                            <svg class="w-4 h-4 text-red-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <span class="text-xs font-bold text-red-700">Diskon berakhir:</span>
                            <span class="font-mono font-black text-xs sm:text-sm text-red-700 bg-red-100/80 px-2 py-0.5 rounded" x-text="display"></span>
                        </div>
                        <span class="text-[11px] text-red-400 ml-auto">s/d {{ $discountEndsAt->translatedFormat('d M Y') }}</span>
                    </div>
                @endif

                <!-- Form Tambah ke Keranjang -->
                {{-- Tombol 'Beli Sekarang' tetap submit normal (langsung redirect ke checkout); hanya tombol 'Masukkan Keranjang' yang dicegat lewat AJAX di bawah --}}
                <form action="{{ route('cart.store') }}" method="POST" class="space-y-6"
                    @submit="
                        if ($event.submitter && $event.submitter.name === 'buy_now') return;

                        $event.preventDefault();
                        if (addingToCart) return;
                        addingToCart = true;

                        fetch($event.target.action, {
                            method: 'POST',
                            headers: { 'Accept': 'application/json' },
                            body: new FormData($event.target),
                        })
                            .then((r) => r.json())
                            .then((data) => {
                                addingToCart = false;
                                addedToCart = true;
                                const imgEl = document.querySelector('[data-product-main-image]');
                                if (imgEl) window.flyToCart(imgEl);
                                window.dispatchEvent(new CustomEvent('cart-count-updated', { detail: { count: data.cart_count } }));
                                setTimeout(() => (addedToCart = false), 2000);
                            })
                            .catch(() => { addingToCart = false; });
                    ">
                    @csrf
                    <input type="hidden" name="product_id" value="{{ $product->id }}">
                    <input type="hidden" name="product_variant_id" :value="variantId">

                    <!-- Pemilih Warna: kotak foto kecil + nama warnanya -->
                    <div>
                        <span class="text-xs font-bold uppercase tracking-wider text-primary block mb-3">Pilih
                            Warna:</span>
                        <div class="flex flex-wrap gap-2.5">
                            @foreach($product->available_colors as $color)
                                @php
                                    $namaWarna = $color['color'];
                                    // Foto khusus warna ini; kalau belum ada, pakai foto utama produk
                                    $fotoWarna = $galeriWarna[$namaWarna][0] ?? null;
                                @endphp

                                <label class="pilih-warna"
                                    :class="{
                                        'pilih-warna-aktif': selectedColor === @js($namaWarna),
                                        'pilih-warna-habis': stokWarna(@js($namaWarna)) === 0
                                    }">
                                    <input type="radio" name="color" value="{{ $namaWarna }}" class="sr-only"
                                        x-model="selectedColor" @change="pilihWarna()"
                                        :disabled="stokWarna(@js($namaWarna)) === 0">

                                    {{-- Kotak gambar --}}
                                    <span class="pilih-warna-kotak">
                                        @if($fotoWarna)
                                            <img src="{{ $fotoWarna }}" alt="{{ $namaWarna }}" loading="lazy"
                                                onerror="this.onerror=null; this.style.display='none';">
                                        @elseif(!empty($color['color_hex']))
                                            {{-- Belum ada foto: pakai kode warnanya --}}
                                            <span class="pilih-warna-blok" style="background: {{ $color['color_hex'] }}"></span>
                                        @else
                                            {{-- Belum ada foto & belum ada kode warna: pakai huruf awal --}}
                                            <span class="pilih-warna-huruf">{{ mb_strtoupper(mb_substr($namaWarna, 0, 1)) }}</span>
                                        @endif

                                        {{-- Tanda centang saat terpilih --}}
                                        <span class="pilih-warna-centang" x-show="selectedColor === @js($namaWarna)" x-cloak>
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3.5"
                                                    d="M5 13l4 4L19 7" />
                                            </svg>
                                        </span>
                                    </span>

                                    {{-- Nama warna, disertai keterangan bila stoknya habis --}}
                                    <span class="pilih-warna-teks">
                                        <span class="pilih-warna-nama">{{ $namaWarna }}</span>
                                        <span class="pilih-warna-habis-teks"
                                            x-show="stokWarna(@js($namaWarna)) === 0" x-cloak>Sold Out</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    @push('styles')
                        <style>
                            /* Pemilih warna — CSS sendiri agar tidak bergantung pada hasil build Tailwind. */
                            [x-cloak] { display: none !important; }

                            .pilih-warna {
                                display: inline-flex;
                                align-items: center;
                                gap: 9px;
                                padding: 5px 12px 5px 5px;
                                border: 1.5px solid #e5e7eb;
                                border-radius: 8px;
                                background: #fff;
                                cursor: pointer;
                                transition: border-color 180ms ease, box-shadow 180ms ease, background-color 180ms ease;
                            }
                            .pilih-warna:hover { border-color: #cbd5e1; background: #f9fafb; }
                            .pilih-warna-aktif {
                                border-color: var(--color-primary, #1B3A6B);
                                background: #eff6ff;
                                box-shadow: 0 0 0 3px rgb(27 58 107 / 0.10);
                            }

                            .pilih-warna-kotak {
                                position: relative;
                                flex-shrink: 0;
                                width: 42px;
                                height: 42px;
                                border-radius: 6px;
                                overflow: hidden;
                                background: #f3f4f6;
                                border: 1px solid #e5e7eb;
                                display: block;
                            }
                            .pilih-warna-kotak img {
                                width: 100%;
                                height: 100%;
                                object-fit: cover;
                                display: block;
                            }
                            .pilih-warna-blok { display: block; width: 100%; height: 100%; }
                            .pilih-warna-huruf {
                                display: flex;
                                align-items: center;
                                justify-content: center;
                                width: 100%;
                                height: 100%;
                                font-size: 15px;
                                font-weight: 800;
                                color: #9ca3af;
                            }

                            /* Centang di atas gambar, dengan lapisan gelap tipis supaya tetap terlihat di foto terang maupun gelap */
                            .pilih-warna-centang {
                                position: absolute;
                                inset: 0;
                                display: flex;
                                align-items: center;
                                justify-content: center;
                                background: rgb(27 58 107 / 0.55);
                                color: #fff;
                            }
                            .pilih-warna-centang svg { width: 18px; height: 18px; }

                            .pilih-warna-teks {
                                display: flex;
                                flex-direction: column;
                                gap: 1px;
                            }
                            .pilih-warna-nama {
                                font-size: 12.5px;
                                font-weight: 600;
                                color: #4b5563;
                                white-space: nowrap;
                            }
                            .pilih-warna-aktif .pilih-warna-nama {
                                color: var(--color-primary, #1B3A6B);
                                font-weight: 700;
                            }

                            /* ── Pilihan yang stoknya habis ──────────────── Tetap ditampilkan supaya pembeli tahu warna dan uk... */
                            .pilih-warna-habis,
                            .pilih-ukuran-habis {
                                cursor: not-allowed;
                                background: #f9fafb;
                                border-color: #e5e7eb;
                                border-style: dashed;
                            }
                            .pilih-warna-habis:hover,
                            .pilih-ukuran-habis:hover {
                                border-color: #e5e7eb;
                                background: #f9fafb;
                            }
                            .pilih-warna-habis .pilih-warna-kotak {
                                filter: grayscale(1);
                                opacity: 0.45;
                            }
                            .pilih-warna-habis .pilih-warna-nama {
                                color: #9ca3af;
                                text-decoration: line-through;
                            }

                            .pilih-warna-habis-teks,
                            .pilih-ukuran-habis-teks {
                                font-size: 9px;
                                font-weight: 800;
                                letter-spacing: 0.06em;
                                text-transform: uppercase;
                                color: #9ca3af;
                                white-space: nowrap;
                            }

                            /* ── Pemilih ukuran ────────────────────────── */
                            .pilih-ukuran-baris {
                                display: flex;
                                flex-wrap: wrap;
                                gap: 8px;
                            }

                            .pilih-ukuran {
                                position: relative;
                                display: inline-flex;
                                flex-direction: column;
                                align-items: center;
                                justify-content: center;
                                gap: 1px;
                                min-width: 58px;
                                min-height: 48px;
                                padding: 6px 10px;
                                border: 1.5px solid #e5e7eb;
                                border-radius: 8px;
                                background: #fff;
                                cursor: pointer;
                                user-select: none;
                                transition: border-color 180ms ease, box-shadow 180ms ease, background-color 180ms ease;
                            }
                            .pilih-ukuran:hover { border-color: #cbd5e1; background: #f9fafb; }

                            .pilih-ukuran-angka {
                                font-size: 14px;
                                font-weight: 700;
                                color: #374151;
                                line-height: 1.1;
                            }

                            .pilih-ukuran-aktif {
                                border-color: var(--color-primary, #1B3A6B);
                                background: #eff6ff;
                                box-shadow: 0 0 0 3px rgb(27 58 107 / 0.10);
                            }
                            .pilih-ukuran-aktif .pilih-ukuran-angka {
                                color: var(--color-primary, #1B3A6B);
                            }

                            .pilih-ukuran-habis .pilih-ukuran-angka {
                                color: #9ca3af;
                                text-decoration: line-through;
                            }

                            /* Garis miring tipis, penanda visual yang langsung terbaca meski tulisannya belum sempat dibaca. */
                            .pilih-ukuran-habis::after {
                                content: '';
                                position: absolute;
                                inset: 0;
                                background: linear-gradient(
                                    to top left,
                                    transparent calc(50% - 0.6px),
                                    #e5e7eb calc(50% - 0.6px),
                                    #e5e7eb calc(50% + 0.6px),
                                    transparent calc(50% + 0.6px)
                                );
                                border-radius: 7px;
                                pointer-events: none;
                            }

                            /* Kursor keyboard tetap terlihat demi aksesibilitas */
                            .pilih-ukuran:has(input:focus-visible),
                            .pilih-warna:has(input:focus-visible) {
                                outline: 2px solid var(--color-primary, #1B3A6B);
                                outline-offset: 2px;
                            }

                            .pilih-ukuran-sisa {
                                margin-top: 10px;
                                font-size: 11px;
                                font-weight: 600;
                                color: #b45309;
                            }
                            .pilih-ukuran-sisa i { margin-right: 4px; }
                        </style>
                    @endpush

                    <!-- Pemilih Ukuran: kotak-kotak, yang habis tidak bisa ditekan -->
                    <div>
                        <span class="text-xs font-bold uppercase tracking-wider text-primary block mb-3">Pilih
                            Ukuran:</span>

                        <div class="pilih-ukuran-baris">
                            @foreach($product->available_sizes as $size)
                                @php $nilaiUkuran = (string) $size; @endphp

                                <label class="pilih-ukuran"
                                    :class="{
                                        'pilih-ukuran-aktif': selectedSize === @js($nilaiUkuran),
                                        'pilih-ukuran-habis': stokUkuran(@js($nilaiUkuran)) === 0
                                    }">
                                    <input type="radio" name="size" value="{{ $nilaiUkuran }}" class="sr-only"
                                        x-model="selectedSize" @change="updateVariant()"
                                        :disabled="stokUkuran(@js($nilaiUkuran)) === 0">

                                    <span class="pilih-ukuran-angka">{{ $size }}</span>
                                    <span class="pilih-ukuran-habis-teks"
                                        x-show="stokUkuran(@js($nilaiUkuran)) === 0" x-cloak>Sold Out</span>
                                </label>
                            @endforeach
                        </div>

                        <p class="pilih-ukuran-sisa" x-show="variantId && variantStock > 0 && variantStock <= 5" x-cloak>
                            <i class="fa-solid fa-fire"></i>
                            Tinggal <strong x-text="variantStock"></strong> lagi untuk ukuran ini.
                        </p>
                    </div>

                    <!-- Jumlah -->
                    <div>
                        <span class="text-xs font-bold uppercase tracking-wider text-primary block mb-3">Jumlah:</span>
                        <div class="flex items-center gap-4">
                            <div class="flex border border-border rounded-sm">
                                <button type="button" @click="if(quantity > 1) quantity--"
                                    class="px-3 py-1.5 bg-gray-50 text-gray-600 hover:bg-gray-100 transition">-</button>
                                <input type="number" name="quantity" x-model="quantity" readonly
                                    class="w-12 text-center text-xs font-bold border-none focus:ring-0 py-1.5">
                                <button type="button" @click="if(quantity < maxStock) quantity++"
                                    class="px-3 py-1.5 bg-gray-50 text-gray-600 hover:bg-gray-100 transition">+</button>
                            </div>
                            <span class="text-xs font-semibold text-text-light">
                                Stok: <span x-text="maxStock"></span> pasang
                            </span>
                        </div>
                    </div>

                    <!-- CTA Buttons -->
                    <div class="space-y-3 pt-4">
                        <button type="submit" :disabled="!siapBeli || addingToCart"
                            class="w-full text-white text-xs font-bold py-3.5 rounded-sm transition uppercase tracking-wide disabled:cursor-not-allowed shadow-sm flex items-center justify-center gap-1.5"
                            :class="addedToCart ? 'bg-emerald-500' : 'bg-primary hover:bg-primary-light disabled:bg-gray-200 disabled:text-gray-400'">
                            <template x-if="!addingToCart && !addedToCart">
                                <span>MASUKKAN KERANJANG</span>
                            </template>
                            <template x-if="addingToCart">
                                <span>MEMPROSES...</span>
                            </template>
                            <template x-if="addedToCart">
                                <span class="flex items-center gap-1.5">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                                    </svg>
                                    DITAMBAHKAN!
                                </span>
                            </template>
                        </button>

                        <button type="submit" name="buy_now" value="1" :disabled="!siapBeli"
                            class="w-full bg-accent hover:bg-accent-light text-white text-xs font-bold py-3.5 rounded-sm transition uppercase tracking-wide disabled:bg-gray-200 disabled:text-gray-400 disabled:cursor-not-allowed shadow-sm"
                            x-text="stokTotal === 0 ? 'STOK HABIS' : 'BELI SEKARANG'">
                        </button>
                    </div>
                </form>

                <!-- Daftar detail produk -->
                <div class="border-t border-border pt-6 space-y-4">
                    <div>
                        <h4 class="text-xs font-bold uppercase tracking-wider text-primary mb-2">Details</h4>
                        <ul class="list-disc pl-4 text-xs text-text-light space-y-1">
                            @if(is_array($product->details))
                                @foreach($product->details as $detail)
                                    <li>{{ $detail }}</li>
                                @endforeach
                            @else
                                <li>Dibuat di Indonesia</li>
                                <li>Material Upper Mesh Premium</li>
                                <li>Injeksi anti jebol</li>
                                <li>Ringan dan nyaman untuk dipakai seharian</li>
                            @endif
                        </ul>
                    </div>

                    <div data-track-section="product_description" data-track-label="Deskripsi & Spesifikasi Produk">
                        <h4 class="text-xs font-bold uppercase tracking-wider text-primary mb-2">Deskripsi Produk</h4>
                        <p class="text-xs text-text-light leading-relaxed">
                            {{ $product->description }}
                        </p>
                    </div>

                    <!-- Dummy Size Chart -->
                    <div>
                        <h4 class="text-xs font-bold uppercase tracking-wider text-primary mb-2">Estimasi Sole (Size
                            Chart)</h4>
                        <div
                            class="grid grid-cols-2 sm:grid-cols-4 gap-2 bg-bg-secondary p-3 rounded-sm text-[10px] font-bold text-text-light">
                            <div>Size 33 : 23cm</div>
                            <div>Size 34 : 24cm</div>
                            <div>Size 35 : 25cm</div>
                            <div>Size 36 : 26cm</div>
                            <div>Size 37 : 27cm</div>
                            <div>Size 38 : 28cm</div>
                            <div>Size 39 : 29cm</div>
                            <div>Size 40 : 30cm</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ════════════════════════════════════════════════════════ --}}
        {{-- Ulasan pembeli                                          --}}
        {{-- ════════════════════════════════════════════════════════ --}}
        <section class="mt-16" id="ulasan" data-track-section="product_reviews" data-track-label="Ulasan & Testimoni Pembeli">
            <div class="ulasan-kepala">
                <h2 class="ulasan-tajuk">Ulasan Pembeli</h2>
                <div class="ulasan-garis"></div>
            </div>

            @if($jumlahUlasan === 0)
                {{-- Produk baru belum punya ulasan. Kosongnya dijelaskan, bukan
                     dibiarkan sebagai ruang hampa yang membingungkan. --}}
                <div class="ulasan-kosong">
                    <i class="fa-regular fa-star"></i>
                    <p class="ulasan-kosong-judul">Belum ada ulasan</p>
                    <p class="ulasan-kosong-ket">
                        Produk ini belum pernah dinilai. Ulasan hanya bisa ditulis
                        pembeli yang benar-benar membelinya, jadi setiap bintang di
                        sini datang dari pembelian sungguhan.
                    </p>
                </div>
            @else
                <div class="ulasan-ringkas">
                    {{-- Angka besar di kiri --}}
                    <div class="ulasan-nilai">
                        <p class="ulasan-angka">{{ number_format($bintangRata, 1) }}</p>
                        <div class="ulasan-bintang-besar">
                            @for($b = 1; $b <= 5; $b++)
                                <i class="fa-solid fa-star {{ $b <= round($bintangRata) ? 'ub-isi' : 'ub-kosong' }}"></i>
                            @endfor
                        </div>
                        <p class="ulasan-jumlah">
                            {{ $jumlahUlasan }} {{ $jumlahUlasan === 1 ? 'ulasan' : 'ulasan' }}
                        </p>
                    </div>

                    {{-- Sebaran per bintang, sekaligus penyaringnya. --}}
                    <div class="ulasan-sebaran">
                        @for($b = 5; $b >= 1; $b--)
                            @php
                                $n = (int) ($sebaran[$b] ?? 0);
                                $persen = $jumlahUlasan > 0 ? round($n * 100 / $jumlahUlasan) : 0;
                                $aktif  = $saringBintang === $b;
                                // Menekan saringan yang sedang aktif berarti melepasnya.
                                $tujuan = $aktif
                                    ? request()->url() . '#ulasan'
                                    : request()->fullUrlWithQuery(['bintang' => $b, 'ulasan' => null]) . '#ulasan';
                            @endphp

                            @if($n === 0)
                                {{-- Bintang tanpa ulasan tidak dijadikan tautan:
                                     menyaring ke daftar kosong tidak ada gunanya. --}}
                                <div class="ulasan-baris ulasan-baris-mati">
                                    <span class="ulasan-baris-label">{{ $b }} <i class="fa-solid fa-star"></i></span>
                                    <span class="ulasan-bilah"><span class="ulasan-bilah-isi" style="width: 0%"></span></span>
                                    <span class="ulasan-baris-jumlah">0</span>
                                </div>
                            @else
                                <a href="{{ $tujuan }}"
                                   class="ulasan-baris ulasan-baris-klik {{ $aktif ? 'ulasan-baris-aktif' : '' }}"
                                   @if($aktif) aria-current="true" @endif
                                   title="{{ $aktif ? 'Tampilkan semua ulasan lagi' : 'Lihat ulasan bintang ' . $b . ' saja' }}">
                                    <span class="ulasan-baris-label">{{ $b }} <i class="fa-solid fa-star"></i></span>
                                    <span class="ulasan-bilah">
                                        <span class="ulasan-bilah-isi" style="width: {{ $persen }}%"></span>
                                    </span>
                                    <span class="ulasan-baris-jumlah">{{ $n }}</span>
                                </a>
                            @endif
                        @endfor

                        <p class="ulasan-sebaran-bantu">
                            @if($saringBintang > 0)
                                <i class="fa-solid fa-filter"></i>
                                Menampilkan bintang {{ $saringBintang }} saja.
                                <a href="{{ request()->url() }}#ulasan" class="ulasan-lepas">Tampilkan semua</a>
                            @else
                                Klik salah satu baris untuk menyaring ulasannya.
                            @endif
                        </p>
                    </div>
                </div>

                <div class="ulasan-daftar">
                    {{-- Bisa kosong bila saringannya diketik sendiri di alamat
                         ke bintang yang belum punya ulasan. --}}
                    @if($ulasan->isEmpty())
                        <div class="ulasan-kosong">
                            <i class="fa-regular fa-star"></i>
                            <p class="ulasan-kosong-judul">Belum ada ulasan bintang {{ $saringBintang }}</p>
                            <p class="ulasan-kosong-ket">
                                <a href="{{ request()->url() }}#ulasan" class="ulasan-lepas">Tampilkan semua ulasan</a>
                            </p>
                        </div>
                    @endif

                    @foreach($ulasan as $u)
                        <article class="ulasan-item">
                            <div class="ulasan-item-kepala">
                                <span class="ulasan-avatar" aria-hidden="true">
                                    {{ mb_strtoupper(mb_substr($u->nama_samaran, 0, 1)) }}
                                </span>

                                <div class="ulasan-item-teks">
                                    <p class="ulasan-nama">{{ $u->nama_samaran }}</p>
                                    <div class="ulasan-item-meta">
                                        <span class="ulasan-bintang-kecil">
                                            @for($b = 1; $b <= 5; $b++)
                                                <i class="fa-solid fa-star {{ $b <= $u->rating ? 'ub-isi' : 'ub-kosong' }}"></i>
                                            @endfor
                                        </span>
                                        <span class="ulasan-tanggal">{{ $u->created_at->translatedFormat('d F Y') }}</span>
                                    </div>
                                    @if($u->varian_dibeli)
                                        <p class="ulasan-varian">Varian: {{ $u->varian_dibeli }}</p>
                                    @endif
                                </div>

                                {{-- Setiap ulasan di sini terikat pada satu pembelian --}}
                                <span class="ulasan-sah" title="Ulasan dari pembelian yang terverifikasi">
                                    <i class="fa-solid fa-circle-check"></i>
                                    Pembelian Terverifikasi
                                </span>
                            </div>

                            @if($u->comment)
                                <p class="ulasan-komentar">{{ $u->comment }}</p>
                            @endif

                            @if(count($u->daftar_foto))
                                <div class="ulasan-foto">
                                    @foreach($u->daftar_foto as $foto)
                                        <a href="{{ \App\Models\Product::storageUrl($foto) }}" target="_blank" rel="noopener"
                                           class="ulasan-foto-tautan">
                                            <img src="{{ \App\Models\Product::storageUrl($foto) }}"
                                                 alt="Foto ulasan dari {{ $u->nama_samaran }}"
                                                 loading="lazy">
                                        </a>
                                    @endforeach
                                </div>
                            @endif
                        </article>
                    @endforeach
                </div>

                @if($ulasan->hasPages())
                    <div class="ulasan-halaman">
                        {{ $ulasan->appends(request()->except('ulasan'))->links() }}
                    </div>
                @endif
            @endif
        </section>

        <!-- Related Products Section -->
        <section class="mt-16" data-track-section="related_products" data-track-label="Rekomendasi Produk Serupa">
            <div class="text-center mb-8">
                <h2 class="text-xl font-black text-primary uppercase tracking-wider">Mungkin yang anda suka</h2>
                <div class="h-1 w-20 bg-accent mx-auto mt-2"></div>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 sm:gap-6">
                @foreach($relatedProducts as $related)
                    <x-product-card :product="$related" />
                @endforeach
            </div>
        </section>
    </div>
@push('styles')
<style>
    /* ══════════ Ulasan pembeli ══════════ Ditulis tangan seperti blok lain di proyek ini: hasil build ... */

    .ulasan-kepala { text-align: center; margin-bottom: 26px; }

    .ulasan-tajuk {
        font-size: 20px; font-weight: 900;
        color: #1B3A6B; text-transform: uppercase; letter-spacing: .08em;
    }
    .ulasan-garis {
        width: 80px; height: 4px; margin: 8px auto 0;
        background: #E11D48;
    }

    /* ── Kosong ── */
    .ulasan-kosong {
        padding: 40px 24px; text-align: center;
        border: 1px dashed #d1d5db; border-radius: 12px;
        background: #fafafa;
    }
    .ulasan-kosong i { font-size: 30px; color: #d1d5db; }
    .ulasan-kosong-judul {
        margin-top: 12px;
        font-size: 14px; font-weight: 800; color: #374151;
    }
    .ulasan-kosong-ket {
        margin: 6px auto 0; max-width: 46ch;
        font-size: 12px; line-height: 1.65; color: #6b7280;
    }

    /* ── Ringkasan ── */
    .ulasan-ringkas {
        display: flex; gap: 28px; align-items: center; flex-wrap: wrap;
        padding: 22px 24px; margin-bottom: 22px;
        border: 1px solid #e5e7eb; border-radius: 12px;
        background: #fff;
    }

    .ulasan-nilai { flex: none; text-align: center; min-width: 128px; }

    .ulasan-angka {
        font-size: 44px; font-weight: 900; line-height: 1;
        color: #1B3A6B;
    }
    .ulasan-bintang-besar { margin-top: 7px; font-size: 15px; letter-spacing: 2px; }
    .ulasan-jumlah {
        margin-top: 5px;
        font-size: 11.5px; color: #6b7280;
    }

    .ub-isi    { color: #F59E0B; }
    .ub-kosong { color: #dfe3ea; }

    .ulasan-sebaran { flex: 1 1 260px; display: flex; flex-direction: column; gap: 6px; }

    .ulasan-baris {
        display: flex; align-items: center; gap: 10px;
        padding: 3px 7px; margin: 0 -7px;
        border-radius: 7px;
        text-decoration: none;
    }

    /* Baris yang bisa diklik diberi isyarat: kursor berubah dan latarnya menyala saat disentuh. */
    .ulasan-baris-klik { cursor: pointer; transition: background-color 150ms ease; }
    .ulasan-baris-klik:hover { background: #f4f6fa; }
    .ulasan-baris-klik:hover .ulasan-bilah-isi { background: #D97706; }

    .ulasan-baris-aktif { background: #FFF7ED; box-shadow: inset 0 0 0 1px #FDBA74; }
    .ulasan-baris-aktif .ulasan-baris-label { color: #9A3412; }

    /* Bintang tanpa ulasan: tetap terlihat sebagai baris, tapi jelas mati. */
    .ulasan-baris-mati { opacity: .45; }

    .ulasan-sebaran-bantu {
        margin-top: 9px;
        font-size: 11px; color: #9ca3af;
    }
    .ulasan-sebaran-bantu i { color: #F59E0B; margin-right: 3px; }

    .ulasan-lepas {
        color: #1B3A6B; font-weight: 700; text-decoration: underline;
    }
    .ulasan-lepas:hover { color: #E11D48; }

    .ulasan-baris-label {
        flex: none; width: 34px;
        font-size: 11px; font-weight: 700; color: #4b5563;
    }
    .ulasan-baris-label i { font-size: 9px; color: #F59E0B; }

    .ulasan-bilah {
        flex: 1 1 auto; height: 7px;
        border-radius: 999px; background: #eef1f5; overflow: hidden;
    }
    .ulasan-bilah-isi {
        display: block; height: 100%;
        border-radius: 999px; background: #F59E0B;
    }

    .ulasan-baris-jumlah {
        flex: none; width: 30px; text-align: right;
        font-size: 11px; color: #6b7280;
    }

    /* ── Daftar ulasan ── */
    .ulasan-daftar { display: flex; flex-direction: column; gap: 14px; }

    .ulasan-item {
        padding: 16px 18px;
        border: 1px solid #e5e7eb; border-radius: 12px;
        background: #fff;
    }

    .ulasan-item-kepala { display: flex; gap: 12px; align-items: flex-start; }

    .ulasan-avatar {
        flex: none;
        display: inline-flex; align-items: center; justify-content: center;
        width: 38px; height: 38px;
        border-radius: 50%;
        background: #1B3A6B; color: #fff;
        font-size: 14px; font-weight: 800;
    }

    .ulasan-item-teks { flex: 1 1 auto; min-width: 0; }

    .ulasan-nama { font-size: 13px; font-weight: 800; color: #111827; }

    .ulasan-item-meta {
        display: flex; align-items: center; gap: 9px; flex-wrap: wrap;
        margin-top: 3px;
    }
    .ulasan-bintang-kecil { font-size: 11px; letter-spacing: 1px; }
    .ulasan-tanggal { font-size: 11px; color: #9ca3af; }

    .ulasan-varian { margin-top: 3px; font-size: 11px; color: #6b7280; }

    .ulasan-sah {
        flex: none;
        display: inline-flex; align-items: center; gap: 5px;
        padding: 3px 9px;
        border: 1px solid #a7f3d0; border-radius: 999px;
        background: #ecfdf5; color: #047857;
        font-size: 9.5px; font-weight: 800;
        letter-spacing: .04em; text-transform: uppercase;
        white-space: nowrap;
    }

    .ulasan-komentar {
        margin-top: 11px;
        font-size: 12.5px; line-height: 1.7; color: #374151;
        white-space: pre-line;
    }

    .ulasan-foto {
        display: flex; gap: 8px; flex-wrap: wrap;
        margin-top: 12px;
    }
    .ulasan-foto-tautan {
        display: block; width: 84px; height: 84px;
        border: 1px solid #e5e7eb; border-radius: 9px; overflow: hidden;
        transition: border-color 160ms ease, transform 160ms ease;
    }
    .ulasan-foto-tautan:hover { border-color: #1B3A6B; transform: scale(1.04); }
    .ulasan-foto-tautan img { width: 100%; height: 100%; object-fit: cover; display: block; }

    .ulasan-halaman { margin-top: 20px; }

    @media (max-width: 560px) {
        /* Lencana terverifikasi turun ke bawah nama, daripada memaksa namanya terpotong di layar sempit. */
        .ulasan-item-kepala { flex-wrap: wrap; }
        .ulasan-sah { order: 3; margin-left: 50px; }
        .ulasan-angka { font-size: 36px; }
        .ulasan-foto-tautan { width: 72px; height: 72px; }
    }

    @media (prefers-reduced-motion: reduce) {
        .ulasan-foto-tautan { transition: none; }
        .ulasan-foto-tautan:hover { transform: none; }
    }
</style>
@endpush


<script>
    /**
     * discountCountdown(endsAtTimestamp)
     * Komponen Alpine.js untuk menampilkan countdown berakhirnya diskon.
     * Juga dipakai di product-card.blade.php — fungsi ini harus terdefinisi
     * di halaman mana pun yang menampilkan countdown diskon.
     */
    if (typeof window.discountCountdown === 'undefined') {
        window.discountCountdown = function discountCountdown(endsAtTimestamp) {
            return {
                display: '',
                expired: false,
                _timer: null,

                init() {
                    this._update();
                    this._timer = setInterval(() => this._update(), 1000);
                },

                _update() {
                    const now = Math.floor(Date.now() / 1000);
                    const diff = endsAtTimestamp - now;

                    if (diff <= 0) {
                        this.expired = true;
                        clearInterval(this._timer);
                        return;
                    }

                    const d = Math.floor(diff / 86400);
                    const h = Math.floor((diff % 86400) / 3600);
                    const m = Math.floor((diff % 3600) / 60);
                    const s = diff % 60;

                    const pad = (n) => String(n).padStart(2, '0');

                    if (d > 0) {
                        this.display = d + 'hr ' + pad(h) + ':' + pad(m);
                    } else {
                        this.display = pad(h) + ':' + pad(m) + ':' + pad(s);
                    }
                },
            };
        };
    }
</script>
</x-app-layout>