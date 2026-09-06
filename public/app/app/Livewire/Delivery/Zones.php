<?php

namespace App\Livewire\Delivery;

use App\Models\AppSetting;
use App\Models\DeliveryZone;
use Livewire\Component;
use Mary\Traits\Toast;

/**
 * Where the shop delivers to, and what it charges.
 *
 * This is pricing, so the same people who set product prices set these:
 * admin and branch manager. Everybody else can look - dispatch needs to know
 * what an area covers - but the figures are not theirs to move.
 */
class Zones extends Component
{
    use Toast;

    public string $name = '';
    public string $fee = '';
    public string $note = '';
    public bool $is_active = true;
    public int $sort_order = 0;
    public ?int $zoneId = null;
    public bool $modal = false;

    /** Whole-shop settings, saved from the same screen. */
    public bool $delivery_enabled = true;
    public string $delivery_free_over = '0';

    public function mount(): void
    {
        $this->delivery_enabled = AppSetting::bool('delivery_enabled', true);
        $this->delivery_free_over = (string) AppSetting::get('delivery_free_over', 0);
    }

    public function canSetPrices(): bool
    {
        return (bool) array_intersect(auth()->user()->role ?? [], ['admin', 'branch_manager']);
    }

    /**
     * Guard the action, not the button.
     *
     * A Livewire method stays callable whether or not the control that calls
     * it was rendered.
     */
    private function blocked(): bool
    {
        if ($this->canSetPrices()) {
            return false;
        }

        $this->error('Delivery charges are set by a manager.');

        return true;
    }

    public function create(): void
    {
        if ($this->blocked()) {
            return;
        }

        $this->reset(['name', 'fee', 'note', 'zoneId']);
        $this->is_active = true;
        $this->sort_order = (int) DeliveryZone::max('sort_order') + 1;
        $this->resetValidation();
        $this->modal = true;
    }

    public function edit(int $id): void
    {
        if ($this->blocked()) {
            return;
        }

        $zone = DeliveryZone::findOrFail($id);

        $this->zoneId = $zone->id;
        $this->name = $zone->name;
        $this->fee = (string) $zone->fee;
        $this->note = (string) $zone->note;
        $this->is_active = $zone->is_active;
        $this->sort_order = $zone->sort_order;
        $this->resetValidation();
        $this->modal = true;
    }

    public function save(): void
    {
        if ($this->blocked()) {
            return;
        }

        $this->validate([
            'name'       => 'required|string|max:120',
            'fee'        => 'required|numeric|min:0',
            'note'       => 'nullable|string|max:200',
            'sort_order' => 'integer|min:0',
        ], [], [
            'fee'        => 'delivery charge',
            'sort_order' => 'position in the list',
        ]);

        DeliveryZone::updateOrCreate(
            ['id' => $this->zoneId],
            [
                'name'       => $this->name,
                'fee'        => (float) $this->fee,
                'note'       => $this->note ?: null,
                'is_active'  => $this->is_active,
                'sort_order' => $this->sort_order,
            ]
        );

        $this->modal = false;
        $this->success($this->zoneId ? 'Area updated.' : 'Area added.');
        $this->reset(['name', 'fee', 'note', 'zoneId']);
    }

    /**
     * Stop serving an area without losing the orders that went there.
     *
     * There is no delete once an area has been used: an old order points at
     * it, and the shop's history should still be able to say where a delivery
     * went. Turning it off takes it off the checkout, which is what "we do not
     * go there any more" actually means.
     */
    public function toggleActive(int $id): void
    {
        if ($this->blocked()) {
            return;
        }

        $zone = DeliveryZone::findOrFail($id);
        $zone->update(['is_active' => ! $zone->is_active]);

        $this->success($zone->is_active
            ? $zone->name . ' is back on the shop.'
            : $zone->name . ' will no longer be offered at checkout.');
    }

    public function delete(int $id): void
    {
        if ($this->blocked()) {
            return;
        }

        $zone = DeliveryZone::findOrFail($id);

        if ($zone->orders()->exists()) {
            $this->error('Orders have gone to this area, so it cannot be removed. Turn it off instead.');

            return;
        }

        $zone->delete();
        $this->success('Area removed.');
    }

    public function saveSettings(): void
    {
        if ($this->blocked()) {
            return;
        }

        $this->validate([
            'delivery_free_over' => 'required|numeric|min:0',
        ], [], ['delivery_free_over' => 'free delivery threshold']);

        AppSetting::set('delivery_enabled', $this->delivery_enabled ? '1' : '0');
        AppSetting::set('delivery_free_over', (float) $this->delivery_free_over);

        $this->success('Delivery settings saved.');
    }

    public function render()
    {
        return view('livewire.delivery.zones', [
            'zones' => DeliveryZone::orderBy('sort_order')->orderBy('name')->withCount('orders')->get(),
        ]);
    }
}
