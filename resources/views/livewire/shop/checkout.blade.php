<div class="max-w-3xl mx-auto px-4 py-6">
    <a href="/cart" class="btn btn-ghost btn-sm mb-4">
        <x-icon name="o-arrow-left" class="w-4 h-4" /> Back to Cart
    </a>

    <h1 class="text-xl font-bold mb-4">Checkout</h1>

    @if(count($items) === 0)
        <div class="text-center py-12">
            <p class="text-base-content/60">Your cart is empty.</p>
            <a href="/shop" class="btn btn-primary btn-sm mt-3">Shop Now</a>
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
            <!-- Form -->
            <div class="md:col-span-3 space-y-4">

                <!-- Account / Guest Toggle -->
                @if(!$isLoggedIn)
                    <div class="card bg-base-100 border border-base-200 p-4">
                        <h2 class="font-semibold mb-3">How would you like to checkout?</h2>
                        <div class="flex gap-2 mb-4">
                            <button wire:click="$set('checkout_mode', 'guest')" @class([
                                'btn btn-sm flex-1',
                                'btn-primary' => $checkout_mode === 'guest',
                                'btn-ghost border border-base-300' => $checkout_mode !== 'guest',
                            ])>Guest</button>
                            <button wire:click="$set('checkout_mode', 'login')" @class([
                                'btn btn-sm flex-1',
                                'btn-primary' => $checkout_mode === 'login',
                                'btn-ghost border border-base-300' => $checkout_mode !== 'login',
                            ])>Sign In</button>
                        </div>

                        @if($checkout_mode === 'guest')
                            <div class="space-y-3">
                                <div>
                                    <label class="label"><span class="label-text font-semibold text-sm">Full Name</span></label>
                                    <input wire:model="guest_name" type="text" class="input input-bordered w-full input-sm" placeholder="Your name" required />
                                    @error('guest_name') <span class="text-error text-xs">{{ $message }}</span> @enderror
                                </div>
                                <div>
                                    <label class="label"><span class="label-text font-semibold text-sm">Email</span></label>
                                    <input wire:model="guest_email" type="email" class="input input-bordered w-full input-sm" placeholder="you@example.com" required />
                                    @error('guest_email') <span class="text-error text-xs">{{ $message }}</span> @enderror
                                </div>
                                <div>
                                    <label class="label"><span class="label-text font-semibold text-sm">Phone</span></label>
                                    <input wire:model="guest_phone" type="tel" class="input input-bordered w-full input-sm" placeholder="08012345678" required />
                                    @error('guest_phone') <span class="text-error text-xs">{{ $message }}</span> @enderror
                                </div>
                            </div>
                        @endif

                        @if($checkout_mode === 'login')
                            <form wire:submit="loginAndCheckout" class="space-y-3">
                                <div>
                                    <label class="label"><span class="label-text font-semibold text-sm">Email or Phone</span></label>
                                    <input wire:model="login_email" type="text" class="input input-bordered w-full input-sm" placeholder="Email or phone" required />
                                    @error('login_email') <span class="text-error text-xs">{{ $message }}</span> @enderror
                                </div>
                                <div>
                                    <label class="label"><span class="label-text font-semibold text-sm">Password</span></label>
                                    <input wire:model="login_password" type="password" class="input input-bordered w-full input-sm" required />
                                    @error('login_password') <span class="text-error text-xs">{{ $message }}</span> @enderror
                                </div>
                                <button type="submit" class="btn btn-primary btn-sm btn-block">Sign In & Continue</button>
                                <div class="text-center text-xs text-base-content/60">
                                    No account? <button type="button" wire:click="$set('checkout_mode', 'guest')" class="text-primary font-semibold">Checkout as guest</button>
                                </div>
                            </form>
                        @endif
                    </div>
                @else
                    <div class="card bg-success/10 border border-success/20 p-4">
                        <div class="flex items-center gap-3">
                            <x-icon name="o-check-circle" class="w-5 h-5 text-success" />
                            <div>
                                <div class="font-semibold text-sm">Signed in as {{ auth('customer')->user()->name }}</div>
                                <div class="text-xs text-base-content/60">{{ auth('customer')->user()->email }}</div>
                            </div>
                        </div>
                    </div>
                @endif

                <!-- Only show remaining fields for guest or logged-in (not login form) -->
                @if($checkout_mode !== 'login' || $isLoggedIn)
                    <!-- Fulfillment -->
                    <div class="card bg-base-100 border border-base-200 p-4">
                        <h2 class="font-semibold mb-3">Fulfillment Method</h2>
                        <div class="flex gap-2">
                            <button wire:click="$set('fulfillment_type', 'delivery')" @class([
                                'btn btn-sm flex-1',
                                'btn-primary' => $fulfillment_type === 'delivery',
                                'btn-ghost border border-base-300' => $fulfillment_type !== 'delivery',
                            ])>
                                <x-icon name="o-truck" class="w-4 h-4" /> Delivery
                            </button>
                            <button wire:click="$set('fulfillment_type', 'pickup')" @class([
                                'btn btn-sm flex-1',
                                'btn-primary' => $fulfillment_type === 'pickup',
                                'btn-ghost border border-base-300' => $fulfillment_type !== 'pickup',
                            ])>
                                <x-icon name="o-building-storefront" class="w-4 h-4" /> Pickup
                            </button>
                        </div>

                        @if($fulfillment_type === 'delivery')
                            <div class="space-y-3 mt-4">
                                <div>
                                    <label class="label"><span class="label-text font-semibold text-sm">Delivery Address</span></label>
                                    <textarea wire:model="delivery_address" class="textarea textarea-bordered w-full text-sm" rows="2" placeholder="Full delivery address" required></textarea>
                                    @error('delivery_address') <span class="text-error text-xs">{{ $message }}</span> @enderror
                                </div>
                                <div>
                                    <label class="label"><span class="label-text font-semibold text-sm">Phone Number</span></label>
                                    <input wire:model="delivery_phone" type="tel" class="input input-bordered w-full input-sm" placeholder="08012345678" required />
                                    @error('delivery_phone') <span class="text-error text-xs">{{ $message }}</span> @enderror
                                </div>
                            </div>
                        @else
                            <div class="bg-base-200 rounded-lg p-3 mt-4 text-sm">
                                <div class="font-semibold">Pickup Location</div>
                                <p class="text-base-content/60 text-xs mt-1">{{ \App\Models\AppSetting::get('pharmacy_address', 'Our pharmacy address') }}</p>
                            </div>
                        @endif
                    </div>

                    <!-- Prescription Upload -->
                    @if($requiresPrescription)
                        <div class="card bg-error/5 border border-error/20 p-4">
                            <h2 class="font-semibold mb-2 text-error">Prescription Required</h2>
                            <p class="text-xs text-base-content/60 mb-3">Some items in your cart require a valid prescription.</p>
                            <input type="file" wire:model="prescription" accept="image/*,.pdf" class="file-input file-input-bordered file-input-sm w-full" />
                            @error('prescription') <span class="text-error text-xs">{{ $message }}</span> @enderror
                        </div>
                    @endif

                    <!-- Payment -->
                    <div class="card bg-base-100 border border-base-200 p-4">
                        <h2 class="font-semibold mb-3">Payment Method</h2>
                        <div class="space-y-2">
                            <label @class([
                                'flex items-center gap-3 p-3 rounded-lg border cursor-pointer',
                                'border-primary bg-primary/5' => $payment_method === 'paystack',
                                'border-base-300' => $payment_method !== 'paystack',
                            ])>
                                <input type="radio" wire:model.live="payment_method" value="paystack" class="radio radio-primary radio-sm" />
                                <div>
                                    <div class="font-semibold text-sm">Pay Online (Paystack)</div>
                                    <div class="text-xs text-base-content/60">Card, bank transfer, USSD</div>
                                </div>
                            </label>
                            <label @class([
                                'flex items-center gap-3 p-3 rounded-lg border cursor-pointer',
                                'border-primary bg-primary/5' => $payment_method === 'pay_on_delivery',
                                'border-base-300' => $payment_method !== 'pay_on_delivery',
                            ])>
                                <input type="radio" wire:model.live="payment_method" value="pay_on_delivery" class="radio radio-primary radio-sm" />
                                <div>
                                    <div class="font-semibold text-sm">Pay on {{ $fulfillment_type === 'delivery' ? 'Delivery' : 'Pickup' }}</div>
                                    <div class="text-xs text-base-content/60">Cash or transfer when you receive</div>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Note -->
                    <div class="card bg-base-100 border border-base-200 p-4">
                        <label class="label"><span class="label-text font-semibold text-sm">Order Note (optional)</span></label>
                        <textarea wire:model="note" class="textarea textarea-bordered w-full text-sm" rows="2" placeholder="Any special instructions..."></textarea>
                    </div>
                @endif
            </div>

            <!-- Order Summary -->
            @if($checkout_mode !== 'login' || $isLoggedIn)
                <div class="md:col-span-2">
                    <div class="card bg-base-100 border border-base-200 p-4 md:sticky md:top-20">
                        <h2 class="font-semibold mb-3">Order Summary</h2>

                        <div class="space-y-2 max-h-48 overflow-y-auto">
                            @foreach($items as $item)
                                <div class="flex justify-between text-sm">
                                    <span class="text-base-content/60 truncate flex-1">{{ $item['name'] }} × {{ $item['quantity'] }}</span>
                                    <span class="ml-2 shrink-0">₦{{ number_format($item['price'] * $item['quantity'], 0) }}</span>
                                </div>
                            @endforeach
                        </div>

                        <div class="border-t border-base-200 mt-3 pt-3 space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-base-content/60">Subtotal</span>
                                <span>₦{{ number_format($subtotal, 2) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-base-content/60">Delivery</span>
                                <span>{{ $deliveryFee > 0 ? '₦' . number_format($deliveryFee, 2) : 'Free' }}</span>
                            </div>
                            <div class="flex justify-between font-bold text-base border-t border-base-200 pt-2">
                                <span>Total</span>
                                <span class="text-primary">₦{{ number_format($total, 2) }}</span>
                            </div>
                        </div>

                        <button wire:click="placeOrder" class="btn btn-primary btn-block mt-4" wire:loading.attr="disabled">
                            <span wire:loading.remove>
                                @if($payment_method === 'paystack')
                                    <x-icon name="o-lock-closed" class="w-4 h-4" /> Pay ₦{{ number_format($total, 0) }}
                                @else
                                    Place Order
                                @endif
                            </span>
                            <span wire:loading>Processing...</span>
                        </button>
                    </div>
                </div>
            @endif
        </div>
    @endif
</div>
