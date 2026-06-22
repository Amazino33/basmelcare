<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;

new class extends Component
{
    public string $name = '';
    public string $email = '';

    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->email = Auth::user()->email;
    }

    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($user->id)],
        ]);

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        $this->dispatch('profile-updated', name: $user->name);
    }
}; ?>

<section>
    <div class="font-semibold mb-1">Profile Information</div>
    <div class="text-sm text-base-content/60 mb-4">Update your name and email address.</div>

    <form wire:submit="updateProfileInformation" class="space-y-4">
        <div>
            <label class="label"><span class="label-text font-semibold">Name</span></label>
            <input wire:model="name" type="text" class="input input-bordered w-full" required autofocus autocomplete="name" />
            @error('name') <span class="text-error text-xs mt-1">{{ $message }}</span> @enderror
        </div>

        <div>
            <label class="label"><span class="label-text font-semibold">Email</span></label>
            <input wire:model="email" type="email" class="input input-bordered w-full" required autocomplete="username" />
            @error('email') <span class="text-error text-xs mt-1">{{ $message }}</span> @enderror
        </div>

        <div class="flex items-center gap-3">
            <button type="submit" class="btn btn-primary btn-sm">Save Changes</button>
            <span x-data="{ show: false }" x-on:profile-updated.window="show = true; setTimeout(() => show = false, 2000)" x-show="show" x-transition class="text-sm text-success">Saved.</span>
        </div>
    </form>
</section>
