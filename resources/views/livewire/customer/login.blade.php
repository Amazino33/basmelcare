<div class="min-h-[70vh] flex items-center justify-center px-4 py-10">
    <div class="w-full max-w-sm">
        <div class="text-center mb-6">
            <div class="w-12 h-12 bg-primary rounded-xl flex items-center justify-center mx-auto mb-3">
                <x-icon name="o-heart" class="w-7 h-7 text-primary-content" />
            </div>
            <h1 class="text-xl font-bold">Welcome Back</h1>
            <p class="text-sm text-base-content/60 mt-1">Sign in to your BasmelCare account</p>
        </div>

        <div class="card bg-base-100 shadow-lg border border-base-200">
            <div class="card-body p-5">
                @if(!$usePassword)
                    {{-- OTP Login --}}
                    @if(!$otpSent)
                        <form wire:submit="sendOtp" class="space-y-4">
                            <div>
                                <label class="label"><span class="label-text font-semibold">Email or Phone</span></label>
                                <input wire:model="identifier" type="text" class="input input-bordered w-full" placeholder="Email or phone number" required autofocus />
                                @error('identifier') <span class="text-error text-xs mt-1">{{ $message }}</span> @enderror
                            </div>
                            <button type="submit" class="btn btn-primary btn-block">Send Login Code</button>
                        </form>
                    @else
                        <form wire:submit="verifyOtp" class="space-y-4">
                            <div class="text-center mb-2">
                                <p class="text-sm text-base-content/60">We sent a 6-digit code to</p>
                                <p class="font-semibold text-sm">{{ $identifier }}</p>
                            </div>
                            <div>
                                <label class="label"><span class="label-text font-semibold">Enter OTP</span></label>
                                <input wire:model="otp" type="text" class="input input-bordered w-full text-center text-lg tracking-widest" placeholder="000000" maxlength="6" required autofocus inputmode="numeric" />
                                @error('otp') <span class="text-error text-xs mt-1">{{ $message }}</span> @enderror
                            </div>
                            <button type="submit" class="btn btn-primary btn-block">Verify & Sign In</button>
                            <button type="button" wire:click="sendOtp" class="btn btn-ghost btn-sm btn-block">Resend Code</button>
                        </form>
                    @endif

                    <div class="divider text-xs">OR</div>
                    <button wire:click="$set('usePassword', true)" class="btn btn-ghost btn-sm btn-block">Use Password Instead</button>
                @else
                    {{-- Password Login --}}
                    <form wire:submit="loginWithPassword" class="space-y-4">
                        <div>
                            <label class="label"><span class="label-text font-semibold">Email or Phone</span></label>
                            <input wire:model="identifier" type="text" class="input input-bordered w-full" placeholder="Email or phone number" required autofocus />
                            @error('identifier') <span class="text-error text-xs mt-1">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="label"><span class="label-text font-semibold">Password</span></label>
                            <input wire:model="password" type="password" class="input input-bordered w-full" required />
                            @error('password') <span class="text-error text-xs mt-1">{{ $message }}</span> @enderror
                        </div>
                        <button type="submit" class="btn btn-primary btn-block">Sign In</button>
                    </form>

                    <div class="divider text-xs">OR</div>
                    <button wire:click="$set('usePassword', false)" class="btn btn-ghost btn-sm btn-block">Use OTP Instead</button>
                @endif

                <div class="text-center mt-2 text-sm text-base-content/60">
                    Don't have an account?
                    <a href="/customer/register" class="text-primary font-semibold hover:underline">Register</a>
                </div>
            </div>
        </div>
    </div>
</div>
