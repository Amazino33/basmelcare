<?php

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    public function register(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ]);

        $validated['password'] = Hash::make($validated['password']);

        event(new Registered($user = User::create($validated)));

        Auth::login($user);

        $this->redirect(route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div>
    <h2 class="text-xl font-bold text-center mb-6">Create Account</h2>

    <form wire:submit="register" class="space-y-4">
        <div>
            <label class="label"><span class="label-text font-semibold">Full Name</span></label>
            <input wire:model="name" type="text" class="input input-bordered w-full" placeholder="Your full name" required autofocus autocomplete="name" />
            @error('name') <span class="text-error text-xs mt-1">{{ $message }}</span> @enderror
        </div>

        <div>
            <label class="label"><span class="label-text font-semibold">Email</span></label>
            <input wire:model="email" type="email" class="input input-bordered w-full" placeholder="you@example.com" required autocomplete="username" />
            @error('email') <span class="text-error text-xs mt-1">{{ $message }}</span> @enderror
        </div>

        <div>
            <label class="label"><span class="label-text font-semibold">Password</span></label>
            <input wire:model="password" type="password" class="input input-bordered w-full" placeholder="Min 8 characters" required autocomplete="new-password" />
            @error('password') <span class="text-error text-xs mt-1">{{ $message }}</span> @enderror
        </div>

        <div>
            <label class="label"><span class="label-text font-semibold">Confirm Password</span></label>
            <input wire:model="password_confirmation" type="password" class="input input-bordered w-full" placeholder="Repeat your password" required autocomplete="new-password" />
            @error('password_confirmation') <span class="text-error text-xs mt-1">{{ $message }}</span> @enderror
        </div>

        <button type="submit" class="btn btn-primary btn-block">Create Account</button>

        <div class="text-center text-sm text-base-content/60">
            Already have an account?
            <a href="{{ route('login') }}" wire:navigate class="text-primary hover:underline font-semibold">Sign In</a>
        </div>
    </form>
</div>
