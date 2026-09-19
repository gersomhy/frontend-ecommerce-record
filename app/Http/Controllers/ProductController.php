<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use App\Support\CatatAktivitas;

class ProductController extends Controller
{
    /**
     * Tampilkan halaman daftar produk dengan filter dan pencarian.
     */
    public function index(Request $request)
    {
        // Lazy loading: data produk diambil langsung tanpa eager loading (with).
        // Relasi (category, variants, activeDiscount, ulasan) dimuat secara lazy (N+1 query) saat diakses di view.
        $query = Product::active();

        // Cari produk berdasarkan kata kunci
        if ($request->filled('search')) {
            $query->search($request->search);
        }

        // Saring berdasarkan kategori
        if ($request->filled('category')) {
            $query->whereHas('category', function ($q) use ($request) {
                $q->where('slug', $request->category);
            });
        }

        // Urutan tampil produk
        $sort = $request->get('sort', 'terbaru');
        $query = match ($sort) {
            'termurah' => $query->orderBy('price', 'asc'),
            'termahal' => $query->orderBy('price', 'desc'),
            'terlaris' => $query->orderBy('stock', 'asc'),
            default => $query->latest(),
        };

        $products = $query->paginate(12)->withQueryString();
        $categories = Category::active()->ordered()->get();

        if ($request->filled('search')) {
            CatatAktivitas::tulisPencarian($request->search, $products->total(), $request->get('category'));
        }

        return view('products.index', compact('products', 'categories', 'sort'));
    }

    /**
     * Tampilkan halaman detail satu produk.
     *
     * ┌─────────────────────────────────────────────────────────────────┐
     * │  STRATEGI: LAZY LOADING + NO CACHE                              │
     * │  Tidak ada ->load(), ->with(), maupun cache apapun di sini.     │
     * │  Setiap relasi dimuat oleh Eloquent secara otomatis (lazy)      │
     * │  pada saat pertama kali diakses di view — satu query per relasi  │
     * │  per produk (N+1 pattern).                                      │
     * │                                                                 │
     * │  Relasi yang akan di-lazy load saat view dirender:              │
     * │   • $product->images        → query images                      │
     * │   • $product->activeDiscount → query discounts                  │
     * │   • $product->variants      → query product_variants            │
     * │   • $product->category      → query categories                  │
     * │   • $product->available_colors → query variants (baru lagi)     │
     * │   • $product->available_sizes  → query variants (baru lagi)     │
     * │   • $product->bintang_rata  → query avg(rating) dari reviews    │
     * │   • $product->jumlah_ulasan → query count reviews               │
     * │  ────────────────────────────────────────────────────────────── │
     * │  Untuk $relatedProducts (tiap kartu produk di view):            │
     * │   • $related->activeDiscount → 1 query × N produk terkait       │
     * │   • $related->variants       → 1 query × N produk terkait       │
     * │   • $related->category       → 1 query × N produk terkait       │
     * └─────────────────────────────────────────────────────────────────┘
     */
    public function show(Product $product)
    {
        // Catat aktivitas view — mengakses $product->id (kolom, bukan relasi, aman).
        CatatAktivitas::tulisProdukView($product);

        // Produk terkait: diambil tanpa ->with() agar relasi juga lazy.
        // Setiap $related di view akan memicu query terpisah untuk
        // category, variants, dan activeDiscount (N+1 disengaja).
        $relatedProducts = Product::active()
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->take(4)
            ->get();

        // ── Ulasan produk ─────────────────────────────────────────────
        // Saringan bintang dari query string.
        $saringBintang = (int) request()->query('bintang', 0);
        if ($saringBintang < 1 || $saringBintang > 5) {
            $saringBintang = 0;
        }

        // Ulasan menggunakan ->with() untuk relasi user dan orderItem agar
        // identik dengan branch eager-redis — sehingga variabel yang diukur
        // hanya strategi loading produk utama, bukan loading ulasan.
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
