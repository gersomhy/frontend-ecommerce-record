<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'description',
        'details',
        'price',
        'original_price',
        'stock',
        'image',
        'is_featured',
        'status',
    ];

    protected $casts = [
        'details' => 'array',
        'price' => 'decimal:2',
        'original_price' => 'decimal:2',
        'is_featured' => 'boolean',
    ];

    /**
     * Get the category that owns the product.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get the images for the product.
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    /**
     * Get the variants for the product.
     */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    /**
     * Get order items for the product.
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /** Semua ulasan, termasuk yang disembunyikan admin. */
    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class);
    }

    /**
 * Hanya ulasan yang boleh dilihat pengunjung.
 */
    public function reviewsTampil(): HasMany
    {
        return $this->hasMany(ProductReview::class)->where('is_hidden', false);
    }

    /**
     * Active discount (valid date + is_active).
     */
    public function activeDiscount(): HasOne
    {
        return $this->hasOne(Discount::class)
            ->whereNull('product_variant_id')
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            })
            ->orderByDesc('discount_percentage');
    }

    /**
     * Check if the product has a discount (DB discount OR original_price fallback).
     */
    public function hasDiscount(): bool
    {
        // Check active DB discount
        if ($this->activeDiscount !== null) {
            return $this->activeDiscount->discount_percentage > 0;
        }
        // Fallback: original_price field
        return $this->original_price !== null && $this->original_price > $this->price;
    }

    /**
     * Get effective discount percentage.
     */
    public function getDiscountPercentageAttribute(): int
    {
        if ($this->activeDiscount !== null) {
            return (int) round($this->activeDiscount->discount_percentage);
        }
        if (!$this->hasDiscount()) return 0;
        return (int) round((($this->original_price - $this->price) / $this->original_price) * 100);
    }

    /**
     * Get the final effective price (after discount).
     */
    public function getEffectivePriceAttribute(): float
    {
        if ($this->activeDiscount !== null) {
            return round((float)$this->price * (1 - $this->activeDiscount->discount_percentage / 100), 0);
        }
        // Fallback: price column is already the discounted price when original_price is set
        return (float) $this->price;
    }

    /**
     * Get the base/original price before discount.
     */
    public function getBasePriceAttribute(): float
    {
        if ($this->activeDiscount !== null) {
            return (float) $this->price;
        }
        return $this->original_price ? (float)$this->original_price : (float)$this->price;
    }

    /**
     * Get formatted effective/discounted price.
     */
    public function getFormattedPriceAttribute(): string
    {
        return 'Rp ' . number_format($this->effective_price, 0, ',', '.');
    }

    /**
     * Get formatted base/original price.
     */
    public function getFormattedOriginalPriceAttribute(): string
    {
        return 'Rp ' . number_format($this->base_price, 0, ',', '.');
    }

    /**
     * Get available sizes (unique from variants).
     */
    public function getAvailableSizesAttribute(): array
    {
        return $this->variants()->distinct()->pluck('size')->sort()->values()->toArray();
    }

    /**
     * Get available colors (unique from variants).
     */
    public function getAvailableColorsAttribute(): array
    {
        return $this->variants()
            ->select('color', 'color_hex')
            ->distinct()
            ->get()
            ->toArray();
    }

    /**
     * Check if product is in stock.
     *
     * Jika relasi variants sudah di-load (eager loading), cek stok dari varian.
     * Jika produk tidak punya varian, fallback ke stok produk utama.
     */
    public function isInStock(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        // Jika relasi variants sudah di-load, gunakan data varian
        if ($this->relationLoaded('variants') && $this->variants->isNotEmpty()) {
            return $this->variants->contains(fn($v) => $v->stock > 0);
        }

        // Fallback: produk tanpa varian pakai stok kolom utama
        return $this->stock > 0;
    }

    /**
     * Scope: only active products.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope: only featured products.
     */
    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    /**
     * Scope: search by name.
     */
    public function scopeSearch($query, ?string $search)
    {
        if (blank($search)) {
            return $query;
        }

        $kata = trim($search);

        // Dicari pada nama produk ATAU kode SKU variannya.
        return $query->where(function ($q) use ($kata) {
            $q->where('name', 'like', "%{$kata}%")
              ->orWhereHas('variants', fn ($v) => $v->where('sku', 'like', "%{$kata}%"));
        });
    }

    /**
     * Get the route key name for route model binding.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Build a URL for any storage path using the backend storage base.
     * Falls back to a placeholder if path is empty.
     */
    public static function storageUrl(?string $path): string
    {
        if (empty($path)) {
            return 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=700&q=80';
        }
        $base = rtrim(env('BACKEND_STORAGE_URL', 'https://admin.recordshoes.com/storage'), '/');
        return $base . '/' . ltrim($path, '/');
    }

    /**
     * Main product image URL (cover).
     */
    public function getImageUrlAttribute(): string
    {
        return self::storageUrl($this->image);
    }

    /**
     * Rating bintang rata-rata (fallback lazy-loading jika tidak di-eager load via withAvg).
     */
    public function getBintangRataAttribute(): float
    {
        if (array_key_exists('bintang_rata', $this->attributes)) {
            return (float) $this->attributes['bintang_rata'];
        }

        return (float) ($this->reviewsTampil()->avg('rating') ?? 0.0);
    }

    /**
     * Jumlah ulasan tampil (fallback lazy-loading jika tidak di-eager load via withCount).
     */
    public function getJumlahUlasanAttribute(): int
    {
        if (array_key_exists('jumlah_ulasan', $this->attributes)) {
            return (int) $this->attributes['jumlah_ulasan'];
        }

        return (int) $this->reviewsTampil()->count();
    }
}
