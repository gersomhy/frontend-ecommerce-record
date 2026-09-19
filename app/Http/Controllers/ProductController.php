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
     * Relasi produk di-load langsung (tidak di-cache) karena datanya spesifik
     * per slug dan sudah dipercepat oleh eager loading.
     * Produk terkait di-cache via ProductCacheService.
     */
    public function show(Product $product)
    {
        $product->load(['category', 'images', 'variants.activeDiscount', 'activeDiscount']);
        CatatAktivitas::tulisProdukView($product);

        $relatedProducts = $this->cacheService->getRelatedProducts($product);

        // Ulasan produk — tidak di-cache karena bergantung pada filter bintang
        // dan paginasi yang bervariasi per user.
        $saringBintang = (int) request()->query('bintang', 0);
        if ($saringBintang < 1 || $saringBintang > 5) {
            $saringBintang = 0;
        }

        $ulasan = $product->reviewsTampil()
            ->with(['user:id,name', 'orderItem:id,variant_info'])
            ->when($saringBintang > 0, fn ($q) => $q->where('rating', $saringBintang))
            ->latest()
            ->paginate(8, ['*'], 'ulasan');

        // Sebaran sengaja TIDAK ikut disaring: angkanya adalah menu pilihan
        // itu sendiri, dan menu yang menyusut begitu dipakai membuat
        // pengunjung tidak bisa berpindah ke bintang lain.
        $sebaran = $product->reviewsTampil()
            ->selectRaw('rating, COUNT(*) as jumlah')
            ->groupBy('rating')
            ->pluck('jumlah', 'rating');

        $jumlahUlasan = (int) $sebaran->sum();

        // map() meneruskan nilai DAN kuncinya, jadi bintangnya (kunci) bisa
        // dikalikan jumlahnya (nilai). sum() dengan fungsi hanya menerima
        // nilainya saja, dan di sini kuncinya justru yang dibutuhkan.
        $bintangRata = $jumlahUlasan > 0
            ? round($sebaran->map(fn ($jumlah, $bintang) => $jumlah * $bintang)->sum() / $jumlahUlasan, 1)
            : 0.0;

        return view('products.show', compact(
            'product', 'relatedProducts', 'ulasan', 'sebaran',
            'jumlahUlasan', 'bintangRata', 'saringBintang'
        ));
    }
}
