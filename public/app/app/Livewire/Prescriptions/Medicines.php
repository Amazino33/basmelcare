<?php

namespace App\Livewire\Prescriptions;

use App\Models\Product;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

/**
 * Where the pharmacist says which drugs may not be handed over without a
 * prescription.
 *
 * A page of its own rather than the product form. Deciding a drug is
 * prescription-only is a clinical judgement and belongs to the pharmacist, but
 * the pharmacist is deliberately barred from editing the catalogue - prices,
 * names, stock. This carves out the one field they own without opening the
 * rest of the door.
 *
 * Nobody else may set it, including admin. The flag decides whether an order
 * waits for a pharmacist, so letting a non-clinical role change it would let
 * them route around the review they are supposed to be waiting on.
 */
class Medicines extends Component
{
    use Toast, WithPagination;

    public string $search = '';

    /** all | marked | unmarked */
    #[Url]
    public string $filter = 'all';

    /** Product id currently being written, so the row can show it working. */
    public ?int $saving = null;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilter(): void
    {
        $this->resetPage();
    }

    private function canDecide(): bool
    {
        return in_array('pharmacist', auth()->user()->role ?? [], true);
    }

    public function togglePrescriptionOnly(int $productId): void
    {
        // Checked here rather than only in the route: this is the write.
        if (! $this->canDecide()) {
            $this->error('Only a pharmacist can decide which drugs need a prescription.');

            return;
        }

        $this->saving = $productId;

        $product = Product::findOrFail($productId);

        // Deliberately a targeted update, not a form save: nothing else on the
        // product can be touched from this page even by accident. The change is
        // recorded against the pharmacist by the model's audit trail.
        $product->requires_prescription = ! $product->requires_prescription;
        $product->save();

        $this->saving = null;

        $this->success(
            $product->name . ($product->requires_prescription
                ? ' now needs a prescription.'
                : ' no longer needs a prescription.')
        );
    }

    private function scoped()
    {
        return Product::query()
            ->when($this->search, fn ($q) => $q->where('name', 'like', '%' . $this->search . '%'))
            ->when($this->filter === 'marked', fn ($q) => $q->where('requires_prescription', true))
            ->when($this->filter === 'unmarked', fn ($q) => $q->where('requires_prescription', false));
    }

    public function render()
    {
        return view('livewire.prescriptions.medicines', [
            'products'     => $this->scoped()->orderBy('name')->paginate(25),
            'markedCount'  => Product::where('requires_prescription', true)->count(),
            'totalCount'   => Product::count(),
            'canDecide'    => $this->canDecide(),
        ]);
    }
}
