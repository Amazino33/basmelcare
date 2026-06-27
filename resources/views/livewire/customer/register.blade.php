<div class="min-h-[70vh] flex items-center justify-center px-4 py-10">
    <div class="w-full max-w-sm">
        <div class="text-center mb-6">
            <div class="w-12 h-12 bg-primary rounded-xl flex items-center justify-center mx-auto mb-3">
                <x-icon name="o-heart" class="w-7 h-7 text-primary-content" />
            </div>
            <h1 class="text-xl font-bold">Create Account</h1>
            <p class="text-sm text-base-content/60 mt-1">Join BasmelCare for easy ordering</p>
        </div>

        <div class="card bg-base-100 shadow-lg border border-base-200">
            <div class="card-body p-5">
                <form wire:submit="register" class="space-y-4">
                    <div>
                        <label class="label"><span class="label-text font-semibold">Full Name</span></label>
                        <input wire:model="name" type="text" class="input input-bordered w-full" placeholder="Your full name" required autofocus />
                        @error('name') <span class="text-error text-xs mt-1">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="label"><span class="label-text font-semibold">Email</span></label>
                        <input wire:model="email" type="email" class="input input-bordered w-full" placeholder="you@example.com" required />
                        @error('email') <span class="text-error text-xs mt-1">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="label"><span class="label-text font-semibold">Phone (WhatsApp)</span></label>
                        <input wire:model="phone" type="tel" class="input input-bordered w-full" placeholder="08012345678" required />
                        @error('phone') <span class="text-error text-xs mt-1">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="label"><span class="label-text font-semibold">Password</span></label>
                        <input wire:model="password" type="password" class="input input-bordered w-full" placeholder="Min 6 characters" required />
                        @error('password') <span class="text-error text-xs mt-1">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="label"><span class="label-text font-semibold">Confirm Password</span></label>
                        <input wire:model="password_confirmation" type="password" class="input input-bordered w-full" required />
                    </div>

                    <button type="submit" class="btn btn-primary btn-block">Create Account</button>

                    <div class="text-center text-sm text-base-content/60">
                        Already have an account?
                        <a href="/customer/login" class="text-primary font-semibold hover:underline">Sign In</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
