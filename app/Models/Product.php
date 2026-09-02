<?php

namespace App\Models;

use App\Support\CloudinaryImage;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    private static ?float $defaultMarkup = null;

    protected $fillable = [
        'name', 'unit', 'sku', 'category_id', 'selling_price', 'wholesale_price',
        'wholesale_min_qty', 'wholesale_markup_percent', 'reorder_level', 'description', 'image', 'barcode',
        // The shop reads these to describe a pack; kept fillable so the two
        // apps' models agree about what a product is.
        'has_pack', 'pack_size', 'pack_price',
        'requires_prescription', 'is_featured', 'show_in_shop',
    ];

    protected $casts = [
        'selling_price' => 'decimal:2',
        'wholesale_price' => 'decimal:2',
        'wholesale_markup_percent' => 'decimal:2',
        'requires_prescription' => 'boolean',
        'is_featured' => 'boolean',
        'show_in_shop' => 'boolean',
    ];

    public function getNameAttribute(string $value): string
    {
        return strtoupper($value);
    }

    public function getPriceFor(?Customer $customer, int $qty = 1): float
    {
        $qualifies = ($customer && $customer->type === 'wholesale')
            || ($this->wholesale_min_qty && $qty >= $this->wholesale_min_qty);

        if (! $qualifies) {
            return (float) $this->selling_price;
        }

        // A price typed in by hand was a deliberate decision, so it wins over
        // anything calculated. The rule below only fills the gap where none
        // was set.
        if ($this->wholesale_price) {
            return (float) $this->wholesale_price;
        }

        return $this->calculatedWholesalePrice() ?? (float) $this->selling_price;
    }

    /**
     * The price for whoever is browsing right now.
     *
     * The shop is one catalogue seen by different people at different
     * prices, so views must never read selling_price directly - a customer
     * tagged as wholesale would be shown the shelf price and then charged
     * something else at checkout.
     */
    public function shopPrice(int $qty = 1): float
    {
        $customer = auth('customer')->user();

        return $this->getPriceFor($customer instanceof Customer ? $customer : null, $qty);
    }

    /** True when this viewer is being charged under the shelf price. */
    public function hasWholesaleDiscount(): bool
    {
        return $this->shopPrice() < (float) $this->selling_price;
    }

    /**
     * Wholesale price derived from what the stock actually cost.
     *
     * Null when nothing is in stock: with no cost to work from there is no
     * honest answer, and guessing one risks selling below cost. The caller
     * falls back to the retail price, which is the safe direction to be wrong
     * in.
     */
    public function calculatedWholesalePrice(): ?float
    {
        $cost = $this->highestCostInStock();

        if ($cost === null) {
            return null;
        }

        return round($cost * (1 + $this->wholesaleMarkupPercent() / 100), 2);
    }

    /**
     * The dearest cost among batches still holding stock.
     *
     * Deliberately the highest rather than the latest or the average. Stock is
     * bought in at different prices, and pricing off a cheap old batch sells
     * goods for less than it costs to replace them - a paper profit and a real
     * loss. Pricing off the dearest batch means the wholesale price only falls
     * once the expensive stock is gone.
     */
    public function highestCostInStock(): ?float
    {
        // Use the loaded relation where there is one. This is called once per
        // row on the product list, which already eager-loads batches; querying
        // again would put the page back to a query per product.
        $cost = $this->relationLoaded('batches')
            ? $this->batches->where('quantity', '>', 0)->max('cost_price')
            : $this->batches()->where('quantity', '>', 0)->max('cost_price');

        return $cost === null ? null : (float) $cost;
    }

    /**
     * Per-product override, else the pharmacy-wide default.
     *
     * Null on the product means "use the default"; zero means someone chose to
     * sell at cost, so the two must not be conflated.
     */
    public function wholesaleMarkupPercent(): float
    {
        if ($this->wholesale_markup_percent !== null) {
            return (float) $this->wholesale_markup_percent;
        }

        // Memoised per request: also called once per row on the product list,
        // and the setting cannot change midway through rendering one page.
        return static::$defaultMarkup ??= (float) AppSetting::get('wholesale_markup_percent', 5);
    }

    /** Cleared in tests, where the setting does change between cases. */
    public static function forgetDefaultMarkup(): void
    {
        static::$defaultMarkup = null;
    }

    /**
     * Public URL of the product image, or null.
     *
     * Images are uploaded through the staff app but written into THIS site's
     * storage, so they are served from here.
     */
    public function imageUrl(?string $preset = null): ?string
    {
        return CloudinaryImage::deliver($this->image, $preset);
    }
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * How a single one of this product is described.
     *
     * The list is fixed rather than typed free, so "tablet", "Tablets" and
     * "tabs" cannot all end up on the shop for the same kind of thing.
     */
    public const UNITS = [
        'tablet'      => 'Tablet',
        'capsule'     => 'Capsule',
        'sachet'      => 'Sachet',
        'strip'       => 'Strip',
        'bottle'      => 'Bottle',
        'tube'        => 'Tube',
        'vial'        => 'Vial',
        'ampoule'     => 'Ampoule',
        'suppository' => 'Suppository',
        'piece'       => 'Piece',
        'pair'        => 'Pair',
        'roll'        => 'Roll',
    ];

    /**
     * The word for one of these, pluralised for a quantity.
     *
     * Falls back to nothing rather than to a guess: most products are sold as
     * whole items and saying "1 each" is worse than saying nothing at all.
     */
    public function unitLabel(int $quantity = 1): ?string
    {
        if (! $this->unit || ! array_key_exists($this->unit, static::UNITS)) {
            return null;
        }

        return \Illuminate\Support\Str::plural(strtolower(static::UNITS[$this->unit]), $quantity);
    }

    /**
     * What the shop price buys - "per tablet", or nothing for a whole item.
     *
     * A bare ₦50 beside a picture of a box reads as the price of the box.
     */
    public function priceUnitLabel(): ?string
    {
        $unit = $this->unitLabel();

        return $unit ? 'per ' . $unit : null;
    }

    /** "Pack of 10 tablets", for a product that is also sold sealed. */
    public function packLabel(): ?string
    {
        if (! $this->has_pack || ! $this->pack_size) {
            return null;
        }

        $size = (int) $this->pack_size;
        $unit = $this->unitLabel($size);

        return 'Pack of ' . $size . ($unit ? ' ' . $unit : '');
    }

    public function batches()
    {
        return $this->hasMany(Batch::class);
    }
}
