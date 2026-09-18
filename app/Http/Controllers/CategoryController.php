<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Services\ProductCacheService;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function __construct(
        private readonly ProductCacheService $cacheService
    ) {}

    /**
     * Tampilkan daftar produk berdasarkan kategori tertentu.
     * Data produk diambil dari Redis cache per kombinasi (slug, sort, page).
     */
    public function show(Category $category, Request $request)
    {
        $products   = $this->cacheService->getKatalogKategori($category, $request);
        $categories = $this->cacheService->getKategoriSidebar();
        $sort       = $request->get('sort', 'terbaru');

        return view('products.index', [
            'products'        => $products,
            'categories'      => $categories,
            'currentCategory' => $category,
            'sort'            => $sort,
        ]);
    }
}
