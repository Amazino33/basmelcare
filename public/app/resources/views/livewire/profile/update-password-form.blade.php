<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Component;

new class extends Component
{
    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';

    public function updatePassword(): void
    {
        try {
            $validated = $this->validate([
                'current_password' => ['required', 'string', 'current_password'],
                'password' => ['required', 'string', Password::defaults(), 'confirmed'],
            ]);
        } catch (ValidationException $e) {
            $this->reset('current_password', 'password', 'password_confirmation');
            throw $e;
        }

        Auth::user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        $this->reset('current_password', 'password', 'password_confirmation');

        $this->dispatch('password-updated');
    }
}; ?>

<section>
    <div class="font-semibold mb-1">Change Password</div>
    <div class="text-sm text-base-content/60 mb-4">Use a strong, unique password to keep your account secure.</div>

    <form wire:submit="updatePassword" class="space-y-4">
        <div>
            <label class="label"><span class="label-text font-semibold">Current Password</span></label>
            <input wire:model="current_password" type="password" class="input input-bordered w-full" autocomplete="current-password" />
            @error('current_password') <span class="text-error text-xs mt-1">{{ $message }}</span> @enderror
        </div>

        <div>
            <label class="label"><span class="label-text font-semibold">New Password</span></label>
            <input wire:model="password" type="password" class="input input-bordered w-full" autocomplete="new-password" />
            @error('password') <span class="text-error text-xs mt-1">{{ $message }}</span> @enderror
        </div>

        <div>
            <label class="label"><span class="label-text font-semibold">Confirm New Password</span></label>
            <input wire:model="password_confirmation" type="password" class="input input-bordered w-full" autocomplete="new-password" />
            @error('password_confirmation') <span class="text-error text-xs mt-1">{{ $message }}</span> @enderror
        </div>

        <div class="flex items-center gap-3">
            <button type="submit" class="btn btn-primary btn-sm">Update Password</button>
            <span x-data="{ show: false }" x-on:password-updated.window="show = true; setTimeout(() => show = false, 2000)" x-show="show" x-transition class="text-sm text-success">Updated.</span>
        </div>
    </form>
</section>
