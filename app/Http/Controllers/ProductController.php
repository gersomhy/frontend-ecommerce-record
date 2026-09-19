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
     */
    public function show(Product $product)
    {
        // Lazy loading: tidak memakai $product->load(...) agar relasi dimuat saat diakses
        CatatAktivitas::tulisProdukView($product);

        $relatedProducts = Product::active()
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->take(4)
            ->get();

        // Ulasan produk.
        // Saringan bintang.
        $saringBintang = (int) request()->query('bintang', 0);
        if ($saringBintang < 1 || $saringBintang > 5) {
            $saringBintang = 0;
        }

        $ulasan = $product->reviewsTampil()
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
