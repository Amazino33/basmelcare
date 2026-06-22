<?php

use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $email = '';

    public function sendPasswordResetLink(): void
    {
        $this->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        $status = Password::sendResetLink(
            $this->only('email')
        );

        if ($status != Password::RESET_LINK_SENT) {
            $this->addError('email', __($status));
            return;
        }

        $this->reset('email');

        session()->flash('status', __($status));
    }
}; ?>

<div>
    <h2 class="text-xl font-bold text-center mb-2">Forgot Password</h2>
    <p class="text-sm text-base-content/60 text-center mb-6">Enter your email and we'll send you a reset link.</p>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form wire:submit="sendPasswordResetLink" class="space-y-4">
        <div>
            <label class="label"><span class="label-text font-semibold">Email</span></label>
            <input wire:model="email" type="email" class="input input-bordered w-full" placeholder="you@example.com" required autofocus />
            @error('email') <span class="text-error text-xs mt-1">{{ $message }}</span> @enderror
        </div>

        <button type="submit" class="btn btn-primary btn-block">Send Reset Link</button>

        <div class="text-center text-sm text-base-content/60">
            <a href="{{ route('login') }}" wire:navigate class="text-primary hover:underline">Back to Sign In</a>
        </div>
    </form>
</div>
