<?php

namespace App\Observers;

use App\Models\Product;
use App\Services\ProductCacheService;

/**
 * Observer Model Product — menginvalidasi cache Redis secara otomatis
 * setiap kali ada produk yang dibuat, diubah, atau dihapus.
 *
 * Dengan observer ini, tidak perlu memanggil cache flush secara manual
 * di setiap controller atau proses admin.
 */
class ProductObserver
{
    public function __construct(
        private readonly ProductCacheService $cacheService
    ) {}

    /** Setelah produk baru dibuat. */
    public function created(Product $product): void
    {
        $this->cacheService->flushProducts();
        $this->cacheService->flushKategoriTertentu($product->category?->slug ?? '');
    }

    /** Setelah produk diubah. */
    public function updated(Product $product): void
    {
        $this->cacheService->flushProducts();

        // Jika kategori produk berubah, flush cache kategori lama dan baru
        if ($product->wasChanged('category_id')) {
            $this->cacheService->flushCategories();
        } else {
            $this->cacheService->flushKategoriTertentu($product->category?->slug ?? '');
        }
    }

    /** Setelah produk dihapus. */
    public function deleted(Product $product): void
    {
        $this->cacheService->flushProducts();
        $this->cacheService->flushKategoriTertentu($product->category?->slug ?? '');
    }
}
