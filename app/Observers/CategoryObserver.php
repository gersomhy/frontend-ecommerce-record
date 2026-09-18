<?php

namespace App\Observers;

use App\Models\Category;
use App\Services\ProductCacheService;

/**
 * Observer Model Category — menginvalidasi cache Redis secara otomatis
 * setiap kali ada kategori yang dibuat, diubah, atau dihapus.
 */
class CategoryObserver
{
    public function __construct(
        private readonly ProductCacheService $cacheService
    ) {}

    /** Setelah kategori baru dibuat. */
    public function created(Category $category): void
    {
        $this->cacheService->flushCategories();
        $this->cacheService->flushProducts();
    }

    /** Setelah kategori diubah. */
    public function updated(Category $category): void
    {
        $this->cacheService->flushCategories();
        // Produk di kategori ini mungkin terpengaruh (nama/slug berubah)
        $this->cacheService->flushKategoriTertentu($category->slug);
        if ($category->wasChanged('slug')) {
            $original = $category->getOriginal('slug');
            if ($original) {
                $this->cacheService->flushKategoriTertentu($original);
            }
        }
    }

    /** Setelah kategori dihapus. */
    public function deleted(Category $category): void
    {
        $this->cacheService->flushCategories();
        $this->cacheService->flushProducts();
    }
}
