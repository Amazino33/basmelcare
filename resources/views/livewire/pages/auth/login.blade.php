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
    <h2 class="text-xl font-bold text-center mb-6">Sign In</h2>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form wire:submit="login" class="space-y-4">
        <div>
            <label class="label"><span class="label-text font-semibold">Email</span></label>
            <input wire:model="form.email" type="email" class="input input-bordered w-full" placeholder="you@example.com" required autofocus autocomplete="username" />
            @error('form.email') <span class="text-error text-xs mt-1">{{ $message }}</span> @enderror
        </div>

        <div>
            <label class="label"><span class="label-text font-semibold">Password</span></label>
            <input wire:model="form.password" type="password" class="input input-bordered w-full" placeholder="Enter your password" required autocomplete="current-password" />
            @error('form.password') <span class="text-error text-xs mt-1">{{ $message }}</span> @enderror
        </div>

        <div class="flex items-center justify-between">
            <label class="flex items-center gap-2 cursor-pointer">
                <input wire:model="form.remember" type="checkbox" class="checkbox checkbox-primary checkbox-sm" />
                <span class="text-sm text-base-content/70">Remember me</span>
            </label>

            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}" wire:navigate class="text-sm text-primary hover:underline">
                    Forgot password?
                </a>
            @endif
        </div>

        <button type="submit" class="btn btn-primary btn-block">Sign In</button>

        <div class="text-center text-sm text-base-content/60">
            Don't have an account?
            <a href="{{ route('register') }}" wire:navigate class="text-primary hover:underline font-semibold">Register</a>
        </div>
    </form>
</div>
