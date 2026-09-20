<?php

namespace App\Http\Controllers;

use App\Models\Banner;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    /**
     * Tampilkan halaman utama (beranda).
     *
     * ┌─────────────────────────────────────────────────────────────────┐
     * │  STRATEGI: LAZY LOADING + NO CACHE                              │
     * │  Tidak ada ->with(), ->withAvg(), ->withCount(), maupun cache   │
     * │  Redis/in-memory apapun di sini.                                │
     * │                                                                 │
     * │  Semua data diambil langsung dari database setiap request.      │
     * │  Setiap relasi pada kartu produk dimuat oleh Eloquent secara    │
     * │  otomatis (lazy loading) saat view 'home' dirender — memicu     │
     * │  pola N+1 Query yang disengaja sebagai pembanding benchmark.    │
     * │                                                                 │
     * │  Pemicu N+1 Query pada komponen <x-product-card>:               │
     * │   • $featuredProducts (8 produk koleksi unggulan):              │
     * │     - $product->activeDiscount  → 1 query discounts × N         │
     * │     - $product->variants        → 1 query product_variants × N  │
     * │     - $product->category        → 1 query categories × N        │
     * │     - $product->jumlah_ulasan   → 1 query count reviews × N     │
     * │     - $product->bintang_rata    → 1 query avg rating × N        │
     * │   • $newArrivals (8 produk terbaru):                            │
     * │     - $product->activeDiscount  → 1 query discounts × N         │
     * │     - $product->variants        → 1 query product_variants × N  │
     * │     - $product->category        → 1 query categories × N        │
     * │     - $product->jumlah_ulasan   → 1 query count reviews × N     │
     * │     - $product->bintang_rata    → 1 query avg rating × N        │
     * │  ────────────────────────────────────────────────────────────── │
     * │  Total query saat halaman beranda dirender: ~95 queries         │
     * │  (dibandingkan dengan ~0-4 queries pada branch eager-redis).    │
     * └─────────────────────────────────────────────────────────────────┘
     */
    public function index()
    {
        // Banner hero & promo: langsung query database tanpa cache
        $heroBanners = Banner::active()->byPosition('hero')->ordered()->take(3)->get();
        $promoBanners = Banner::active()->byPosition('promo')->ordered()->get();

        // Kategori: diambil langsung dari database tanpa cache.
        // Catatan: $categories ini juga dilengkapi oleh View Composer di
        // AppServiceProvider (withCount activeProducts) tanpa caching.
        $categories = Category::active()->ordered()->get();

        // Produk unggulan: diambil tanpa ->with() agar relasi dimuat secara lazy (N+1 disengaja).
        $featuredProducts = Product::active()->featured()
            ->take(8)
            ->get();

        // Produk terbaru: diambil tanpa ->with() agar relasi dimuat secara lazy (N+1 disengaja).
        $newArrivals = Product::active()
            ->latest()
            ->take(8)
            ->get();

        return view('home', compact(
            'heroBanners',
            'promoBanners',
            'categories',
            'featuredProducts',
            'newArrivals'
        ));
    }
}
