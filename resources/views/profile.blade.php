<x-app-layout>
    <x-header title="My Profile" subtitle="Manage your account settings" />

    <div class="space-y-6 max-w-2xl">
        <!-- Profile Info Card -->
        <x-card>
            <div class="flex items-center gap-4 mb-6">
                <x-avatar :value="auth()->user()->name" class="!w-16 !h-16" />
                <div>
                    <div class="text-lg font-bold">{{ auth()->user()->name }}</div>
                    <div class="text-sm text-base-content/60">{{ auth()->user()->email }}</div>
                    <div class="flex gap-2 mt-1">
                        <x-badge :value="ucfirst(str_replace('_', ' ', auth()->user()->role))" class="badge-primary badge-sm" />
                        @if(auth()->user()->branch)
                            <x-badge :value="auth()->user()->branch->name" class="badge-ghost badge-sm" />
                        @endif
                    </div>
                </div>
            </div>

            <livewire:profile.update-profile-information-form />
        </x-card>

        <!-- Password Card -->
        <x-card>
            <livewire:profile.update-password-form />
        </x-card>

        <!-- Logout -->
        <x-card>
            <div class="flex items-center justify-between">
                <div>
                    <div class="font-semibold">Sign Out</div>
                    <div class="text-sm text-base-content/60">End your current session.</div>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn btn-error btn-outline btn-sm">Logout</button>
                </form>
            </div>
        </x-card>
    </div>
</x-app-layout>
