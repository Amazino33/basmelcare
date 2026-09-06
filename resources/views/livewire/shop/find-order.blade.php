<div class="max-w-md mx-auto px-4 py-10">
    <div class="text-center mb-6">
        <x-icon name="o-magnifying-glass" class="w-12 h-12 text-base-content/20 mx-auto mb-3" />
        <h1 class="text-xl font-bold">Find your order</h1>
        <p class="text-base-content/60 text-sm mt-1">
            Your order number and the phone number you gave us.
        </p>
    </div>

    <form wire:submit="find" class="card bg-base-100 border border-base-200 p-4 space-y-3">
        <div>
            <label class="label"><span class="label-text font-semibold text-sm">Order number</span></label>
            <input wire:model="order_number" class="input w-full input-sm" placeholder="ORD-202609-0001" />
            @error('order_number') <span class="text-error text-xs">{{ $message }}</span> @enderror
        </div>

        <div>
            <label class="label"><span class="label-text font-semibold text-sm">Phone number</span></label>
            <input wire:model="phone" type="tel" class="input w-full input-sm" placeholder="08012345678" />
            @error('phone') <span class="text-error text-xs">{{ $message }}</span> @enderror
        </div>

        <button type="submit" class="btn btn-primary btn-block" wire:loading.attr="disabled">
            <span wire:loading.remove>Find it</span>
            <span wire:loading>Looking…</span>
        </button>
    </form>

    <p class="text-xs text-base-content/50 text-center mt-4">
        Have an account? <a href="/account" class="link">All your orders are there.</a>
    </p>
</div>
