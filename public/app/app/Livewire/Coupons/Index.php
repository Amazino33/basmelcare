<?php

namespace App\Livewire\Coupons;

use App\Models\Coupon;
use Livewire\Component;
use Mary\Traits\Toast;

class Index extends Component
{
    use Toast;

    public bool $couponModal = false;
    public ?int $couponId = null;

    public string $code         = '';
    public string $type         = 'fixed';
    public string $value        = '';
    public string $max_discount = '';
    public string $max_uses     = '';
    public string $expires_at   = '';
    public bool   $is_active    = true;

    public function openCreate(): void
    {
        $this->reset(['couponId', 'code', 'value', 'max_discount', 'max_uses', 'expires_at']);
        $this->type      = 'fixed';
        $this->is_active = true;
        $this->couponModal = true;
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
        $this->couponModal  = true;
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
        ]);

        $data['code']         = strtoupper($data['code']);
        $data['max_discount'] = ($this->type === 'percent' && $this->max_discount !== '') ? $this->max_discount : null;
        $data['max_uses']     = $data['max_uses'] ?: null;
        $data['expires_at']   = $data['expires_at'] ?: null;

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
            'coupons' => Coupon::latest()->get(),
        ]);
    }
}
