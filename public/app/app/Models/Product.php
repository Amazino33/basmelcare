<?php

namespace App\Models;

use App\Models\Concerns\NormalisesName;
use App\Models\Concerns\RecordsAudit;
use App\Support\CloudinaryImage;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use NormalisesName;
    use RecordsAudit;

    /** Prices drive revenue and margin, so changes must be attributable. */
    protected array $audited = ['selling_price', 'wholesale_price', 'wholesale_markup_percent', 'pack_price'];

    private static ?float $defaultMarkup = null;

    protected $fillable = [
        'name', 'sku', 'category_id', 'selling_price', 'wholesale_price',
        'wholesale_min_qty', 'wholesale_markup_percent',
        'has_pack', 'pack_size', 'pack_price',
        'reorder_level', 'description', 'image', 'barcode',
        'requires_prescription', 'is_featured', 'show_in_shop',
    ];

    protected $casts = [
        'selling_price' => 'decimal:2',
        'wholesale_price' => 'decimal:2',
        'wholesale_markup_percent' => 'decimal:2',
        'has_pack' => 'boolean',
        'pack_price' => 'decimal:2',
        'requires_prescription' => 'boolean',
        'is_featured' => 'boolean',
        'show_in_shop' => 'boolean',
    ];


    public function getPriceFor(?Customer $customer, int $qty = 1): float
    {
        if (! $this->qualifiesForWholesale($customer, $qty)) {
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
     * Wholesale applies either because of who is buying or how much of it.
     *
     * Shared so that pack pricing asks the same question as unit pricing;
     * two copies of this rule would drift.
     */
    private function qualifiesForWholesale(?Customer $customer, int $qty): bool
    {
        return ($customer && $customer->type === 'wholesale')
            || ($this->wholesale_min_qty && $qty >= $this->wholesale_min_qty);
    }

    /** Sold in packs as well as singly, and priced for it. */
    public function sellsInPacks(): bool
    {
        return (bool) $this->has_pack
            && (int) $this->pack_size > 1
            && $this->pack_price !== null;
    }

    /**
     * What one pack costs this customer.
     *
     * Retail buyers pay the pack price typed into the product, which is how a
     * pack can be cheaper than the same tablets bought loose.
     *
     * Wholesale buyers do NOT: they pay their per-unit price multiplied by the
     * pack size. Wholesale pricing is already derived from cost per unit, so it
     * scales on its own, and a pack is then just a quicker way of entering
     * twenty tablets. It also means the wholesale margin is protected even if
     * the retail pack price is set below cost by mistake - and that a
     * wholesaler is never better off buying loose.
     */
    public function packPriceFor(?Customer $customer, int $packs = 1): ?float
    {
        if (! $this->sellsInPacks()) {
            return null;
        }

        $units = $packs * (int) $this->pack_size;

        if ($this->qualifiesForWholesale($customer, $units)) {
            return round($this->getPriceFor($customer, $units) * $this->pack_size, 2);
        }

        return (float) $this->pack_price;
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
     * Product images are written to the PUBLIC site's storage, so the URL must
     * be built from that site's address — asset() here would point at the staff
     * subdomain, where the file does not exist.
     */
    public function imageUrl(?string $preset = null): ?string
    {
        return CloudinaryImage::deliver($this->image, $preset);
    }
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function batches()
    {
        return $this->hasMany(Batch::class);
    }
}
