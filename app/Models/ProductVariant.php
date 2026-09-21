<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ProductVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'size',
        'color',
        'color_hex',
        'stock',
        'price_adjustment',
        'sku',
    ];

    protected $casts = [
        'price_adjustment' => 'decimal:2',
    ];

    /**
     * Sembunyikan relasi product saat varian di-serialize ke array / JSON
     * untuk mencegah circular reference dan relation bloat berulang.
     */
    protected $hidden = [
        'product',
    ];


    /**
     * Get the product that owns the variant.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the cart items for this variant.
     */
    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    /**
     * Get the order items for this variant.
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * All variant-level discounts.
     */
    public function discounts(): HasMany
    {
        return $this->hasMany(Discount::class, 'product_variant_id');
    }

    /**
     * The currently active variant-level discount (if any).
     * Returns null when this variant uses the product-level discount.
     */
    public function activeDiscount(): HasOne
    {
        return $this->hasOne(Discount::class, 'product_variant_id')
            ->where('is_active', true)
            ->where(function ($q) { $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()); })
            ->where(function ($q) { $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()); });
    }

    /**
     * Get the base price (product price + adjustment), without discount.
     */
    public function getFinalPriceAttribute(): float
    {
        return $this->product->price + $this->price_adjustment;
    }

    /**
     * Get the effective discounted price for this variant.
     * Priority: variant-level discount → product-level discount → no discount.
     */
    public function getEffectivePriceAttribute(): float
    {
        $basePrice = $this->final_price;

        // 1. Check variant-specific discount
        if ($this->activeDiscount) {
            $pct = (float) $this->activeDiscount->discount_percentage;
            return round($basePrice * (1 - $pct / 100), 2);
        }

        // 2. Fall back to product-level discount
        if ($this->product && $this->product->activeDiscount) {
            $pct = (float) $this->product->activeDiscount->discount_percentage;
            return round($basePrice * (1 - $pct / 100), 2);
        }

        return $basePrice;
    }

    /**
     * Get the discount percentage that applies to this variant
     * (variant-specific first, then product-level).
     */
    public function getEffectiveDiscountPctAttribute(): float
    {
        if ($this->activeDiscount) {
            return (float) $this->activeDiscount->discount_percentage;
        }
        if ($this->product && $this->product->activeDiscount) {
            return (float) $this->product->activeDiscount->discount_percentage;
        }
        return 0;
    }

    /**
     * Get formatted variant info string.
     */
    public function getVariantInfoAttribute(): string
    {
        return "Size {$this->size} - {$this->color}";
    }

    /**
     * Check if variant is in stock.
     */
    public function isInStock(): bool
    {
        return $this->stock > 0;
    }
}
