<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\ProductCacheService;
use App\Support\CatatAktivitas;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct(
        private readonly ProductCacheService $cacheService
    ) {}

    /**
     * Tampilkan halaman daftar produk dengan filter dan pencarian.
     * Data produk diambil dari Redis cache; fallback ke DB jika cache miss.
     */
    public function index(Request $request)
    {
        $products = $this->cacheService->getKatalogProduk($request);
        $sort     = $request->get('sort', 'terbaru');

        if ($request->filled('search')) {
            CatatAktivitas::tulisPencarian(
                $request->search,
                $products->total(),
                $request->get('category')
            );
        }

        return view('products.index', compact('products', 'sort'));
    }

    /**
     * Tampilkan halaman detail satu produk.
     *
     * ┌─────────────────────────────────────────────────────────────────┐
     * │  STRATEGI: EAGER LOADING + REDIS CACHE                          │
     * │                                                                 │
     * │  Cache hit  (request ke-2 dst, dalam TTL 10 menit):            │
     * │   • Produk + semua relasi langsung dari Redis — 0 query DB      │
     * │   • Produk terkait dari Redis — 0 query DB                     │
     * │   • Total query DB: 0 (hanya ulasan, karena tidak di-cache)     │
     * │                                                                 │
     * │  Cache miss (request pertama / setelah cache di-flush):         │
     * │   • Route model binding: 1 query (SELECT produk by slug)        │
     * │   • Eager load relasi: 4 query                                  │
     * │     – category, images, variants, activeDiscount                │
     * │   • variants.activeDiscount: sudah termasuk dalam variants load  │
     * │   • Produk terkait: 1 query (dengan eager relasi sekaligus)     │
     * │   • Hasilnya disimpan ke Redis → request berikutnya 0 query     │
     * │                                                                 │
     * │  Ulasan tidak di-cache karena bergantung pada filter bintang    │
     * │  dan paginasi yang unik per user/request.                       │
     * └─────────────────────────────────────────────────────────────────┘
     */
    public function show(Product $product)
    {
        // Eager load semua relasi produk dari Redis (atau DB jika cache miss),
        // lalu simpan hasilnya ke Redis untuk request berikutnya.
        $product = $this->cacheService->getDetailProduk($product);

        CatatAktivitas::tulisProdukView($product);

        // Produk terkait — juga di-cache Redis per product ID.
        $relatedProducts = $this->cacheService->getRelatedProducts($product);

        // ── Ulasan produk ─────────────────────────────────────────────
        // Tidak di-cache karena bergantung pada filter bintang dan
        // paginasi yang bervariasi per user.
        $saringBintang = (int) request()->query('bintang', 0);
        if ($saringBintang < 1 || $saringBintang > 5) {
            $saringBintang = 0;
        }

        $ulasan = $product->reviewsTampil()
            ->with(['user:id,name', 'orderItem:id,variant_info'])
            ->when($saringBintang > 0, fn ($q) => $q->where('rating', $saringBintang))
            ->latest()
            ->paginate(8, ['*'], 'ulasan');

        // Sebaran bintang — sengaja TIDAK ikut disaring (lihat komentar di bawah).
        // Angka sebaran adalah menu pilihan itu sendiri; jika ikut tersaring,
        // menu menyusut dan pembeli tidak bisa berpindah ke bintang lain.
        $sebaran = $product->reviewsTampil()
            ->selectRaw('rating, COUNT(*) as jumlah')
            ->groupBy('rating')
            ->pluck('jumlah', 'rating');

        $jumlahUlasan = (int) $sebaran->sum();

        // Rata-rata bintang dihitung di PHP dari $sebaran yang sudah ada
        // (bukan query tambahan) — map() meneruskan nilai DAN kuncinya.
        $bintangRata = $jumlahUlasan > 0
            ? round($sebaran->map(fn ($jumlah, $bintang) => $jumlah * $bintang)->sum() / $jumlahUlasan, 1)
            : 0.0;

        return view('products.show', compact(
            'product', 'relatedProducts', 'ulasan', 'sebaran',
            'jumlahUlasan', 'bintangRata', 'saringBintang'
        ));
    }
}

