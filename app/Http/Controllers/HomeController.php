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
