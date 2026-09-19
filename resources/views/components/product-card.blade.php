@props([
    'product',
    'eager' => false,   // Gunakan true untuk produk pertama/above-fold (percepat LCP)
])

@php
    // Ambil ends_at dari activeDiscount (jika ada dan ada tanggal berakhir)
    $discountEndsAt = $product->activeDiscount?->ends_at;
    $discountEndsAtTimestamp = $discountEndsAt ? $discountEndsAt->timestamp : null;

    // Strategi loading gambar: eager untuk kartu pertama, lazy untuk sisanya
    $imgLoading       = $eager ? 'eager' : 'lazy';
    $imgFetchPriority = $eager ? 'high'  : 'auto';
@endphp

<div class="bg-white border border-border rounded-sm hover:shadow-md transition-all duration-300 flex flex-col group relative p-3 sm:p-4">
    <!-- Badge Status (SOLD / DISKON) & Countdown di Pojok Kiri Atas -->
    <div class="kartu-badge-wrap">
        @if(!$product->isInStock())
            <span class="bg-warning text-white text-[9px] sm:text-[10px] font-bold px-2 py-0.5 uppercase tracking-wider rounded-sm shadow-sm">
                SOLD
            </span>
        @elseif($product->hasDiscount())
            <span class="bg-accent text-white text-[9px] sm:text-[10px] font-bold px-2 py-0.5 uppercase tracking-wider rounded-sm shadow-sm">
                DISKON {{ $product->discount_percentage }}%
            </span>
            {{-- Countdown diskon warna merah & berlabel jelas --}}
            @if($discountEndsAtTimestamp)
                <span
                    x-data="discountCountdown({{ $discountEndsAtTimestamp }})"
                    x-init="init()"
                    x-show="!expired"
                    x-cloak
                    class="kartu-countdown-badge"
                    title="Diskon berakhir {{ $discountEndsAt->translatedFormat('d M Y') }}"
                >
                    <svg class="kartu-countdown-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span class="kartu-countdown-label">Diskon <span class="kartu-countdown-label-desktop">berakhir:</span></span>
                    <span class="kartu-countdown-time" x-text="display"></span>
                </span>
            @endif
        @endif
    </div>

    <!-- Quick Add to Cart icon di Pojok Kanan Atas (Ukuran responsif mobile lebih kecil) -->
    <div class="kartu-cart-wrap" x-data="{ adding: false, added: false }">
        <form
            @submit.prevent="
                if (adding) return;
                adding = true;
                const form = $event.target;
                const imgEl = form.closest('.group')?.querySelector('img');
                fetch(form.action, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json' },
                    body: new FormData(form),
                })
                    .then((r) => r.json())
                    .then((data) => {
                        adding = false;
                        added = true;
                        if (imgEl) window.flyToCart(imgEl);
                        window.dispatchEvent(new CustomEvent('cart-count-updated', { detail: { count: data.cart_count } }));
                        setTimeout(() => (added = false), 1500);
                    })
                    .catch(() => { adding = false; });
            "
            action="{{ route('cart.store') }}" method="POST">
            @csrf
            <input type="hidden" name="product_id" value="{{ $product->id }}">
            <input type="hidden" name="quantity" value="1">

            @if($product->variants->isNotEmpty())
                <input type="hidden" name="product_variant_id" value="{{ $product->variants->first()->id }}">
            @endif

            <button type="submit" :disabled="adding"
                class="kartu-cart-btn"
                :class="added ? 'bg-emerald-500 border-emerald-500 text-white scale-110' : 'bg-white text-gray-600 hover:text-primary hover:scale-110 border-border'"
                title="Masukkan Keranjang">
                <svg x-show="!added" :class="adding ? 'animate-pulse' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                </svg>
                <svg x-show="added" x-transition.scale.duration.300ms fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                </svg>
            </button>
        </form>
    </div>

    <!-- Product Image -->
    <a href="{{ route('products.show', $product->slug) }}" class="block overflow-hidden mb-3 aspect-square bg-gray-50 rounded-sm">
        <img src="{{ $product->image_url }}"
            alt="{{ $product->name }}"
            class="object-cover w-full h-full group-hover:scale-105 transition-transform duration-300"
            loading="{{ $imgLoading }}"
            fetchpriority="{{ $imgFetchPriority }}"
            onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=400&q=80';">
    </a>

    <!-- Product Info -->
    <div class="flex-grow flex flex-col justify-between text-center">
        <div>
            <!-- Category name -->
            <p class="text-[9px] sm:text-[10px] uppercase font-bold tracking-wider text-text-light mb-1">
                {{ $product->category->name }}
            </p>

            <!-- Product Title -->
            <a href="{{ route('products.show', $product->slug) }}" class="block">
                <h4 class="text-xs font-bold text-text hover:text-primary line-clamp-2 min-h-[32px] leading-tight mb-2">
                    {{ $product->name }}
                </h4>
            </a>

            {{-- Bintang rata-rata --}}
            @isset($product->jumlah_ulasan)
                @if($product->jumlah_ulasan > 0)
                    <a href="{{ route('products.show', $product->slug) }}#ulasan" class="kartu-bintang">
                        @for($b = 1; $b <= 5; $b++)
                            <i class="fa-solid fa-star {{ $b <= round($product->bintang_rata) ? 'kb-isi' : 'kb-kosong' }}"></i>
                        @endfor
                        <span class="kartu-bintang-jml">({{ $product->jumlah_ulasan }})</span>
                    </a>
                @endif
            @endisset
        </div>

        <div>
            <!-- Price Section — nowrap agar tidak stack ke bawah di mobile -->
            <div class="kartu-harga">
                @if($product->hasDiscount())
                    <span class="kartu-harga-coret">{{ $product->formatted_original_price }}</span>
                    <span class="kartu-harga-diskon">{{ $product->formatted_price }}</span>
                @else
                    <span class="kartu-harga-normal">{{ $product->formatted_price }}</span>
                @endif
            </div>

            <!-- "BELI SEKARANG" Button -->
            @if(!$product->isInStock())
                <button disabled class="w-full bg-gray-200 text-gray-400 text-xs font-bold py-2 sm:py-2.5 rounded-sm uppercase tracking-wide cursor-not-allowed">
                    STOK HABIS
                </button>
            @else
                <a href="{{ route('products.show', $product->slug) }}"
                    class="block w-full bg-accent hover:bg-accent-light text-white text-xs font-bold py-2 sm:py-2.5 rounded-sm transition uppercase tracking-wide text-center">
                    BELI SEKARANG
                </a>
            @endif
        </div>
    </div>
</div>

@once
<style>
    /* Posisi badge kiri atas */
    .kartu-badge-wrap {
        position: absolute;
        top: 8px;
        left: 8px;
        z-index: 10;
        display: flex;
        flex-direction: column;
        gap: 3px;
        align-items: flex-start;
    }

    /* Countdown diskon warna merah & berlabel jelas */
    .kartu-countdown-badge {
        display: inline-flex;
        align-items: center;
        gap: 3px;
        background: #dc2626; /* Merah solid khas promo / diskon */
        color: #ffffff;
        font-size: 8px;
        font-weight: 800;
        padding: 2px 5.5px;
        border-radius: 2px;
        box-shadow: 0 1px 3px rgba(220, 38, 38, 0.35);
        line-height: 1.1;
        letter-spacing: -0.1px;
    }
    .kartu-countdown-icon {
        width: 10px;
        height: 10px;
        flex-shrink: 0;
        color: #fef08a; /* Kuning cerah agar kontras & eye-catching */
        animation: kartu-detak 1.5s ease-in-out infinite;
    }
    .kartu-countdown-label {
        font-weight: 700;
        text-transform: uppercase;
        font-size: 7.5px;
        letter-spacing: 0.2px;
        white-space: nowrap;
    }
    .kartu-countdown-label-desktop {
        display: none;
    }
    .kartu-countdown-time {
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        font-weight: 900;
        white-space: nowrap;
    }

    /* Posisi keranjang kanan atas — pasti di sudut kanan */
    .kartu-cart-wrap {
        position: absolute;
        top: 8px;
        right: 8px;
        z-index: 10;
    }

    /* Tombol keranjang: lebih kecil di mobile agar proporsional */
    .kartu-cart-btn {
        width: 28px;
        height: 28px;
        border-radius: 9999px;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 1px solid var(--color-border, #e2e8f0);
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        transition: all 0.2s ease;
    }
    .kartu-cart-btn svg {
        width: 14px;
        height: 14px;
    }

    @media (min-width: 640px) {
        .kartu-badge-wrap {
            top: 12px;
            left: 12px;
        }
        .kartu-countdown-badge {
            font-size: 9px;
            padding: 2.5px 7px;
            gap: 4px;
        }
        .kartu-countdown-icon {
            width: 11px;
            height: 11px;
        }
        .kartu-countdown-label {
            font-size: 8.5px;
        }
        .kartu-countdown-label-desktop {
            display: inline;
        }
        .kartu-cart-wrap {
            top: 12px;
            right: 12px;
        }
        .kartu-cart-btn {
            width: 36px;
            height: 36px;
        }
        .kartu-cart-btn svg {
            width: 17px;
            height: 17px;
        }
    }

    @keyframes kartu-detak {
        0%, 100% { opacity: 1; transform: scale(1); }
        50% { opacity: 0.65; transform: scale(0.9); }
    }

    .kartu-bintang {
        display: inline-flex; align-items: center; gap: 2px;
        margin-bottom: 8px;
        font-size: 10px; letter-spacing: .5px;
        text-decoration: none;
    }
    .kb-isi    { color: #F59E0B; }
    .kb-kosong { color: #dfe3ea; }

    .kartu-bintang-jml {
        margin-left: 3px;
        font-size: 10px; color: #9ca3af; letter-spacing: 0;
    }
    .kartu-bintang:hover .kartu-bintang-jml { color: #4b5563; }

    /* ── Harga kartu produk ──────────────────────────────────────── */
    /* Selalu dalam satu baris; nowrap mencegah wrap ke bawah di mobile. */
    /* Jarak bawah (margin-bottom) diperlebar agar tidak terlalu dekat dengan BELI SEKARANG */
    .kartu-harga {
        display: flex;
        align-items: baseline;
        justify-content: center;
        flex-wrap: nowrap;
        gap: 3px;
        line-height: 1.2;
        max-width: 100%;
        overflow: hidden;
        margin-bottom: 14px;
    }
    .kartu-harga-coret {
        font-size: 9px;
        color: #9ca3af;
        text-decoration: line-through;
        white-space: nowrap;
        flex-shrink: 1;
        overflow: hidden;
        text-overflow: ellipsis;
        min-width: 0;
    }
    .kartu-harga-diskon {
        font-size: 12px;
        font-weight: 700;
        color: var(--color-accent, #e53e3e);
        white-space: nowrap;
        flex-shrink: 0;
    }
    .kartu-harga-normal {
        font-size: 12px;
        font-weight: 700;
        color: #1a202c;
        white-space: nowrap;
    }
    @media (min-width: 380px) {
        .kartu-harga-coret  { font-size: 10px; }
        .kartu-harga-diskon { font-size: 13px; }
        .kartu-harga-normal { font-size: 13px; }
    }
    @media (min-width: 640px) {
        .kartu-harga {
            gap: 6px;
            margin-bottom: 16px;
        }
        .kartu-harga-coret {
            font-size: 11px;
        }
        .kartu-harga-diskon {
            font-size: 14px;
        }
        .kartu-harga-normal {
            font-size: 14px;
        }
    }
</style>

<script>
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
@endonce