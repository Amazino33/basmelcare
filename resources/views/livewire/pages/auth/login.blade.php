<?php

use App\Livewire\Forms\LoginForm;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public LoginForm $form;

    public function login(): void
    {
        $this->validate();

        $this->form->authenticate();

        Session::regenerate();

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div>
    <h2 class="text-xl font-bold text-center mb-6">Staff Sign In</h2>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form wire:submit="login" class="space-y-4" x-data="{ showPass: false }">
        <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-base-content/70 mb-1.5">
                Staff Email
            </label>
            <div class="relative">
                <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-base-content/40">
                    <x-icon name="o-envelope" class="w-5 h-5" />
                </span>
                <input wire:model="form.email"
                       type="email"
                       class="input input-bordered w-full pl-10 rounded-xl focus:border-primary text-sm @error('form.email') input-error @enderror"
                       placeholder="you@basmelcare.com"
                       required autofocus autocomplete="username" />
            </div>
            @error('form.email')
                <span class="text-error text-xs mt-1 block font-medium flex items-center gap-1">
                    <x-icon name="o-exclamation-circle" class="w-3.5 h-3.5 shrink-0" /> {{ $message }}
                </span>
            @enderror
        </div>

        <div>
            <div class="flex items-center justify-between mb-1.5">
                <label class="text-xs font-bold uppercase tracking-wider text-base-content/70">
                    Password
                </label>
                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}" wire:navigate class="text-xs text-primary hover:underline">
                        Forgot password?
                    </a>
                @endif
            </div>
            <div class="relative">
                <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-base-content/40">
                    <x-icon name="o-lock-closed" class="w-5 h-5" />
                </span>
                <input wire:model="form.password"
                       :type="showPass ? 'text' : 'password'"
                       class="input input-bordered w-full pl-10 pr-10 rounded-xl focus:border-primary text-sm @error('form.password') input-error @enderror"
                       placeholder="Enter staff password"
                       required autocomplete="current-password" />
                <button type="button"
                        @click="showPass = !showPass"
                        class="absolute inset-y-0 right-0 flex items-center pr-3.5 text-base-content/40 hover:text-base-content focus:outline-none"
                        tabindex="-1">
                    <x-icon x-show="!showPass" name="o-eye" class="w-4 h-4" />
                    <x-icon x-show="showPass" name="o-eye-slash" class="w-4 h-4" style="display: none;" />
                </button>
            </div>
            @error('form.password')
                <span class="text-error text-xs mt-1 block font-medium flex items-center gap-1">
                    <x-icon name="o-exclamation-circle" class="w-3.5 h-3.5 shrink-0" /> {{ $message }}
                </span>
            @enderror
        </div>

        <div class="flex items-center justify-between pt-1">
            <label class="flex items-center gap-2 cursor-pointer select-none">
                <input wire:model="form.remember" type="checkbox" class="checkbox checkbox-primary checkbox-sm rounded-md" />
                <span class="text-xs text-base-content/70 font-medium">Keep me signed in</span>
            </label>
        </div>

        <button type="submit"
                wire:loading.attr="disabled"
                class="btn btn-primary btn-block rounded-xl font-semibold shadow-sm">
            <span wire:loading.remove class="flex items-center justify-center gap-2">
                <span>Sign In to Desk</span>
                <x-icon name="o-arrow-right-end-on-rectangle" class="w-4 h-4" />
            </span>
            <span wire:loading class="flex items-center justify-center gap-2">
                <span class="loading loading-spinner loading-xs"></span>
                <span>Authenticating...</span>
            </span>
        </button>

        <div class="text-center text-xs text-base-content/50 pt-2 border-t border-base-200">
            Staff accounts are provisioned by an administrator.
        </div>
    </form>
</div>
