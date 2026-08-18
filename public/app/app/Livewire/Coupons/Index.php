<?php

namespace App\Livewire\Coupons;

use App\Models\Category;
use App\Models\Coupon;
use App\Models\Product;
use Livewire\Component;
use Mary\Traits\Toast;

class Index extends Component
{
    use Toast;

    public bool $couponModal = false;
    public ?int $couponId = null;

    // Core fields
    public string $code         = '';
    public string $type         = 'fixed';
    public string $value        = '';
    public string $max_discount = '';
    public string $max_uses     = '';
    public string $expires_at   = '';
    public bool   $is_active    = true;
    public bool   $auto_apply   = false;

    // Restrictions — toggles
    public bool $enableCustomerType = false;
    public bool $enableOrderAmount  = false;
    public bool $enableCategories   = false;
    public bool $enableProducts     = false;
    public bool $enableItemCount    = false;

    // Restriction values
    public string $customer_type     = 'all';
    public string $min_order_amount  = '';
    public string $max_order_amount  = '';
    public array  $restricted_categories = [];
    public array  $restricted_products   = [];
    public string $min_item_count    = '';

    public function openCreate(): void
    {
        $this->reset([
            'couponId', 'code', 'value', 'max_discount', 'max_uses', 'expires_at',
            'min_order_amount', 'max_order_amount', 'restricted_categories',
            'restricted_products', 'min_item_count',
            'enableCustomerType', 'enableOrderAmount', 'enableCategories',
            'enableProducts', 'enableItemCount', 'auto_apply',
        ]);
        $this->type          = 'fixed';
        $this->is_active     = true;
        $this->customer_type = 'all';
        $this->couponModal   = true;
    }

    public function openEdit(int $id): void
    {
        $coupon = Coupon::findOrFail($id);

        $this->couponId     = $id;
        $this->code         = $coupon->code;
        $this->type         = $coupon->type;
        $this->value        = (string) $coupon->value;
        $this->max_discount = $coupon->max_discount !== null ? (string) $coupon->max_discount : '';
        $this->max_uses     = $coupon->max_uses !== null ? (string) $coupon->max_uses : '';
        $this->expires_at   = $coupon->expires_at?->format('Y-m-d') ?? '';
        $this->is_active    = $coupon->is_active;
        $this->auto_apply   = (bool) $coupon->auto_apply;

        $this->customer_type        = $coupon->customer_type ?? 'all';
        $this->enableCustomerType   = $this->customer_type !== 'all';
        $this->min_order_amount     = $coupon->min_order_amount !== null ? (string) $coupon->min_order_amount : '';
        $this->max_order_amount     = $coupon->max_order_amount !== null ? (string) $coupon->max_order_amount : '';
        $this->enableOrderAmount    = $coupon->min_order_amount !== null || $coupon->max_order_amount !== null;
        $this->restricted_categories = $coupon->restricted_categories ?? [];
        $this->enableCategories     = !empty($this->restricted_categories);
        $this->restricted_products  = $coupon->restricted_products ?? [];
        $this->enableProducts       = !empty($this->restricted_products);
        $this->min_item_count       = $coupon->min_item_count !== null ? (string) $coupon->min_item_count : '';
        $this->enableItemCount      = $coupon->min_item_count !== null;

        $this->couponModal = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'code'         => 'required|string|max:50|alpha_dash|unique:coupons,code' . ($this->couponId ? ",{$this->couponId}" : ''),
            'type'         => 'required|in:fixed,percent',
            'value'        => 'required|numeric|min:0.01|' . ($this->type === 'percent' ? 'max:100' : ''),
            'max_discount' => 'nullable|numeric|min:0.01',
            'max_uses'     => 'nullable|integer|min:1',
            'expires_at'   => 'nullable|date|after:today',
            'is_active'    => 'boolean',
            'auto_apply'   => 'boolean',
            // Restrictions
            'customer_type'          => 'in:all,new,returning',
            'min_order_amount'       => 'nullable|numeric|min:0.01',
            'max_order_amount'       => 'nullable|numeric|min:0.01',
            'restricted_categories'  => 'nullable|array',
            'restricted_categories.*'=> 'integer',
            'restricted_products'    => 'nullable|array',
            'restricted_products.*'  => 'integer',
            'min_item_count'         => 'nullable|integer|min:1',
        ]);

        $data['code']         = strtoupper($data['code']);
        $data['max_discount'] = ($this->type === 'percent' && $this->max_discount !== '') ? $this->max_discount : null;
        $data['max_uses']     = $data['max_uses'] ?: null;
        $data['expires_at']   = $data['expires_at'] ?: null;

        // Cross-field restriction checks
        if ($this->enableOrderAmount
            && $this->min_order_amount !== ''
            && $this->max_order_amount !== ''
            && (float) $this->min_order_amount >= (float) $this->max_order_amount) {
            $this->addError('max_order_amount', 'Maximum must be greater than the minimum.');
            return;
        }
        if ($this->enableCategories && empty($this->restricted_categories)) {
            $this->addError('restricted_categories', 'Select at least one category or turn off this restriction.');
            return;
        }
        if ($this->enableProducts && empty($this->restricted_products)) {
            $this->addError('restricted_products', 'Select at least one product or turn off this restriction.');
            return;
        }

        $data['customer_type']         = $this->enableCustomerType ? $this->customer_type : 'all';
        $data['min_order_amount']      = ($this->enableOrderAmount && $this->min_order_amount !== '') ? $this->min_order_amount : null;
        $data['max_order_amount']      = ($this->enableOrderAmount && $this->max_order_amount !== '') ? $this->max_order_amount : null;
        $data['restricted_categories'] = ($this->enableCategories && !empty($this->restricted_categories)) ? $this->restricted_categories : null;
        $data['restricted_products']   = ($this->enableProducts && !empty($this->restricted_products)) ? $this->restricted_products : null;
        $data['min_item_count']        = ($this->enableItemCount && $this->min_item_count !== '') ? (int) $this->min_item_count : null;

        if ($this->couponId) {
            Coupon::findOrFail($this->couponId)->update($data);
            $this->success('Coupon updated.');
        } else {
            Coupon::create($data);
            $this->success('Coupon created.');
        }

        $this->couponModal = false;
    }

    public function toggleActive(int $id): void
    {
        $coupon = Coupon::findOrFail($id);
        $coupon->update(['is_active' => !$coupon->is_active]);
        $this->success($coupon->is_active ? 'Coupon activated.' : 'Coupon deactivated.');
    }

    public function delete(int $id): void
    {
        Coupon::findOrFail($id)->delete();
        $this->success('Coupon deleted.');
    }

    public function render()
    {
        return view('livewire.coupons.index', [
            'coupons'    => Coupon::latest()->get(),
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'products'   => Product::orderBy('name')->get(['id', 'name']),
        ]);
    }
}
