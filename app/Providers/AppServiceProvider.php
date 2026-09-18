<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\Product;
use App\Observers\CategoryObserver;
use App\Observers\ProductObserver;
use App\Services\ProductCacheService;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(\App\Services\Geocoding\Geocoder::class, function () {
            return match (config('geocoding.driver', 'osm')) {
                'google' => new \App\Services\Geocoding\GoogleGeocoder,
                default  => new \App\Services\Geocoding\NominatimGeocoder,
            };
        });

        // Daftarkan ProductCacheService sebagai singleton agar hanya dibuat sekali
        // per request cycle dan dapat di-inject ke Observer maupun Controller.
        $this->app->singleton(ProductCacheService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (app()->environment('production') || str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        // ── Observer ─────────────────────────────────────────────────────
        // Invalidasi cache Redis otomatis setiap kali data produk/kategori berubah.
        Product::observe(ProductObserver::class);
        Category::observe(CategoryObserver::class);

        // ── View Composer ────────────────────────────────────────────────
        // Gunakan ProductCacheService agar data categories dimuat dari Redis,
        // bukan dari query DB setiap request.
        \Illuminate\Support\Facades\View::composer(
            ['layouts.app', 'components.navbar', 'home', 'products.index', 'products.show'],
            function ($view) {
                /** @var ProductCacheService $cacheService */
                $cacheService = app(ProductCacheService::class);

                $view->with('categories', $cacheService->getKategoriAktif());

                $cartService = app(\App\Services\CartService::class);
                $view->with('cartCount', $cartService->getCartCount());

                $view->with('pembelianTerbaru', app(\App\Services\PembelianTerbaruService::class)->ambil());
            }
        );
    }
}
