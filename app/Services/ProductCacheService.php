<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Service terpusat untuk Redis caching data produk dan kategori.
 *
 * Strategi cache:
 *  - Semua key produk diberi tag "products" → mudah di-flush saat ada perubahan produk.
 *  - Semua key kategori diberi tag "categories" → mudah di-flush saat ada perubahan kategori.
 *  - TTL produk: 10 menit  (data berubah lebih sering: stok, diskon, dll.)
 *  - TTL kategori: 30 menit (data jarang berubah)
 *
 * Catatan: Cache::tags() hanya bekerja dengan driver yang mendukung tagging
 * (Redis, Memcached). Driver "database" atau "file" tidak mendukung tags.
 */
class ProductCacheService
{
    /** TTL untuk data produk (detik). */
    public const TTL_PRODUK = 600;      // 10 menit

    /** TTL untuk data kategori (detik). */
    public const TTL_KATEGORI = 1800;   // 30 menit

    /** TTL untuk halaman katalog produk (filter + pagination). */
    public const TTL_KATALOG = 300;     // 5 menit

    // ──────────────────────────────────────────────────────────────────────
    // Kategori
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Ambil semua kategori aktif (dengan jumlah produk aktif).
     * Dipakai oleh View Composer di AppServiceProvider.
     */
    public function getKategoriAktif(): Collection
    {
        return Cache::store('redis')->tags(['categories'])->remember(
            'categories.aktif',
            self::TTL_KATEGORI,
            fn () => Category::active()
                ->withCount('activeProducts')
                ->ordered()
                ->get()
        );
    }

    /**
     * Ambil semua kategori aktif (tanpa withCount — dipakai sidebar katalog).
     */
    public function getKategoriSidebar(): Collection
    {
        return Cache::store('redis')->tags(['categories'])->remember(
            'categories.sidebar',
            self::TTL_KATEGORI,
            fn () => Category::active()->ordered()->get()
        );
    }

    // ──────────────────────────────────────────────────────────────────────
    // Produk Unggulan & Produk Terbaru (Homepage)
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Ambil produk unggulan (featured) untuk homepage.
     */
    public function getFeaturedProducts(int $limit = 8): Collection
    {
        return Cache::store('redis')->tags(['products'])->remember(
            "products.featured.{$limit}",
            self::TTL_PRODUK,
            fn () => Product::active()
                ->featured()
                ->with(['category', 'activeDiscount', 'variants'])
                ->withAvg('reviewsTampil as bintang_rata', 'rating')
                ->withCount('reviewsTampil as jumlah_ulasan')
                ->take($limit)
                ->get()
        );
    }

    /**
     * Ambil produk terbaru untuk homepage.
     */
    public function getNewArrivals(int $limit = 8): Collection
    {
        return Cache::store('redis')->tags(['products'])->remember(
            "products.new_arrivals.{$limit}",
            self::TTL_PRODUK,
            fn () => Product::active()
                ->with(['category', 'activeDiscount', 'variants'])
                ->withAvg('reviewsTampil as bintang_rata', 'rating')
                ->withCount('reviewsTampil as jumlah_ulasan')
                ->latest()
                ->take($limit)
                ->get()
        );
    }

    // ──────────────────────────────────────────────────────────────────────
    // Halaman Katalog (filter, sort, pagination)
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Ambil daftar produk terpaginasi untuk halaman katalog.
     * Cache key menyertakan semua parameter filter sehingga setiap kombinasi
     * filter (search, category, sort, page) di-cache secara independen.
     */
    public function getKatalogProduk(Request $request, int $perPage = 12): LengthAwarePaginator
    {
        $cacheKey = $this->buildKatalogKey($request);

        return Cache::store('redis')->tags(['products', 'catalog'])->remember(
            $cacheKey,
            self::TTL_KATALOG,
            function () use ($request, $perPage) {
                $query = Product::active()
                    ->with(['category', 'activeDiscount', 'variants'])
                    ->withAvg('reviewsTampil as bintang_rata', 'rating')
                    ->withCount('reviewsTampil as jumlah_ulasan');

                // Filter pencarian
                if ($request->filled('search')) {
                    $query->search($request->search);
                }

                // Filter kategori
                if ($request->filled('category')) {
                    $query->whereHas('category', function ($q) use ($request) {
                        $q->where('slug', $request->category);
                    });
                }

                // Urutan
                $sort = $request->get('sort', 'terbaru');
                $query = match ($sort) {
                    'termurah' => $query->orderBy('price', 'asc'),
                    'termahal' => $query->orderBy('price', 'desc'),
                    'terlaris' => $query->orderBy('stock', 'asc'),
                    default    => $query->latest(),
                };

                return $query->paginate($perPage)->withQueryString();
            }
        );
    }

    /**
     * Ambil daftar produk berdasarkan kategori (dengan pagination).
     */
    public function getKatalogKategori(Category $category, Request $request, int $perPage = 12): LengthAwarePaginator
    {
        $sort     = $request->get('sort', 'terbaru');
        $page     = $request->get('page', 1);
        $cacheKey = "catalog.category.{$category->slug}.sort:{$sort}.page:{$page}";

        return Cache::store('redis')->tags(['products', 'catalog', "category:{$category->slug}"])->remember(
            $cacheKey,
            self::TTL_KATALOG,
            function () use ($category, $request, $sort, $perPage) {
                $query = $category->activeProducts()
                    ->with(['category', 'activeDiscount', 'variants'])
                    ->withAvg('reviewsTampil as bintang_rata', 'rating')
                    ->withCount('reviewsTampil as jumlah_ulasan');

                $query = match ($sort) {
                    'termurah' => $query->orderBy('price', 'asc'),
                    'termahal' => $query->orderBy('price', 'desc'),
                    default    => $query->latest(),
                };

                return $query->paginate($perPage)->withQueryString();
            }
        );
    }

    // ──────────────────────────────────────────────────────────────────────
    // Produk Terkait (halaman detail produk)
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Ambil produk terkait (same category, exclude current product).
     */
    public function getRelatedProducts(Product $product, int $limit = 4): Collection
    {
        $cacheKey = "products.related.{$product->id}.limit:{$limit}";

        return Cache::store('redis')->tags(['products'])->remember(
            $cacheKey,
            self::TTL_PRODUK,
            fn () => Product::active()
                ->where('category_id', $product->category_id)
                ->where('id', '!=', $product->id)
                ->with(['category', 'activeDiscount', 'variants'])
                ->withAvg('reviewsTampil as bintang_rata', 'rating')
                ->withCount('reviewsTampil as jumlah_ulasan')
                ->take($limit)
                ->get()
        );
    }

    /**
     * Ambil data lengkap satu produk (dengan semua relasi) untuk halaman detail.
     *
     * Strategi:
     *  - Cache key: product.detail.{id} — unik per produk.
     *  - Tag: 'products' — saat Observer memflush tag ini (create/update/delete),
     *    cache detail produk yang bersangkutan ikut terhapus otomatis.
     *  - TTL: sama dengan TTL_PRODUK (10 menit).
     *
     * Relasi yang di-eager load dan disimpan ke Redis:
     *  - category          → untuk breadcrumb & label kategori
     *  - images            → untuk galeri foto produk
     *  - variants          → untuk pilihan ukuran & warna
     *  - variants.activeDiscount → untuk harga diskon per varian
     *  - activeDiscount    → untuk diskon level produk & countdown
     *
     * Pada cache hit: tidak ada query DB sama sekali untuk relasi di atas.
     * Pada cache miss: 1 query produk + 4 query relasi (eager), lalu disimpan ke Redis.
     */
    public function getDetailProduk(Product $product): Product
    {
        $cacheKey = "product.detail.{$product->id}";

        return Cache::store('redis')->tags(['products'])->remember(
            $cacheKey,
            self::TTL_PRODUK,
            fn () => $product->load([
                'category',
                'images',
                'variants.activeDiscount',
                'activeDiscount',
            ])
        );
    }


    // ──────────────────────────────────────────────────────────────────────
    // Cache Invalidation
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Hapus semua cache produk (dipanggil saat produk dibuat/diubah/dihapus).
     */
    public function flushProducts(): void
    {
        Cache::store('redis')->tags(['products'])->flush();
    }

    /**
     * Hapus semua cache kategori (dipanggil saat kategori dibuat/diubah/dihapus).
     */
    public function flushCategories(): void
    {
        Cache::store('redis')->tags(['categories'])->flush();
    }

    /**
     * Hapus cache katalog satu kategori tertentu.
     */
    public function flushKategoriTertentu(string $slug): void
    {
        Cache::store('redis')->tags(["category:{$slug}"])->flush();
    }

    /**
     * Hapus semua cache produk dan kategori sekaligus.
     */
    public function flushSemua(): void
    {
        Cache::store('redis')->tags(['products', 'categories', 'catalog'])->flush();
    }

    // ──────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Buat cache key unik untuk halaman katalog berdasarkan parameter request.
     * Key menyertakan: search, category, sort, page — sehingga setiap
     * kombinasi filter di-cache secara terpisah.
     */
    private function buildKatalogKey(Request $request): string
    {
        $params = [
            'search'   => $request->get('search', ''),
            'category' => $request->get('category', ''),
            'sort'     => $request->get('sort', 'terbaru'),
            'page'     => $request->get('page', 1),
        ];

        // Hash MD5 supaya key tidak terlalu panjang di Redis
        return 'catalog.products.' . md5(http_build_query($params));
    }
}
