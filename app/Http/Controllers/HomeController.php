<?php

namespace App\Http\Controllers;

use App\Models\Banner;
use App\Models\Category;
use App\Services\ProductCacheService;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function __construct(
        private readonly ProductCacheService $cacheService
    ) {}

    /**
     * Tampilkan halaman utama (beranda).
     *
     * ┌─────────────────────────────────────────────────────────────────┐
     * │  STRATEGI: EAGER LOADING + REDIS CACHE                          │
     * │                                                                 │
     * │  Cache hit  (request ke-2 dst, dalam TTL 10 menit):             │
     * │   • Kategori aktif dari Redis (via View Composer) — 0 query DB  │
     * │   • Produk koleksi unggulan (featured) dari Redis — 0 query DB  │
     * │   • Produk terbaru (new arrivals) dari Redis — 0 query DB       │
     * │   • In-memory relation linking menghubungkan varian ke produk   │
     * │     di memori PHP sehingga bebas query N+1 pada kartu produk.   │
     * │   • Total query DB: ~2 query (hanya hero & promo banners).      │
     * │                                                                 │
     * │  Cache miss (request pertama / setelah cache di-flush):         │
     * │   • Kategori aktif: 1 query (dengan withCount activeProducts)   │
     * │   • Produk unggulan: 1 query produk + 3 eager query relasi      │
     * │     (category, activeDiscount, variants, withAvg, withCount)    │
     * │   • Produk terbaru: 1 query produk + 3 eager query relasi       │
     * │     (category, activeDiscount, variants, withAvg, withCount)    │
     * │   • Disimpan ke Redis (Tag 'products' & 'categories')           │
     * │     sehingga request selanjutnya langsung dilayani dari memori. │
     * │                                                                 │
     * │  Perbandingan: Mengeliminasi ~90+ query N+1 dari skenario       │
     * │  lazy-nocache (95 query → ~2 query pada cache hit).             │
     * └─────────────────────────────────────────────────────────────────┘
     */
    public function index()
    {
        // Maksimal 3 banner hero ditampilkan di slider halaman utama
        $heroBanners  = Banner::active()->byPosition('hero')->ordered()->take(3)->get();
        $promoBanners = Banner::active()->byPosition('promo')->ordered()->get();

        // Catatan: $categories di bawah ini akan ditimpa oleh View Composer di
        // AppServiceProvider yang sudah menggunakan ProductCacheService (Redis).
        $categories = $this->cacheService->getKategoriAktif();

        // Produk unggulan & terbaru diambil dari Redis cache.
        $featuredProducts = $this->cacheService->getFeaturedProducts(8);
        $newArrivals      = $this->cacheService->getNewArrivals(8);

        return view('home', compact(
            'heroBanners',
            'promoBanners',
            'categories',
            'featuredProducts',
            'newArrivals'
        ));
    }
}
