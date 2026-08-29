<div>
    <x-header title="Cover" subtitle="Who is covered, and who owes a premium" size="text-xl">
        <x-slot:actions>
            @if($canManage)
                <x-button label="Sign up a customer" icon="o-plus" wire:click="openSignUp" class="btn-primary btn-sm" />
            @endif
        </x-slot:actions>
    </x-header>

    @unless(\App\Services\InsuranceCover::enabled())
        <div class="alert alert-info py-2 mb-4 text-sm gap-2">
            <x-icon name="o-information-circle" class="w-4 h-4 shrink-0" />
            <span>Cover is switched off in Settings, so none of this pays for anything at the till yet.</span>
        </div>
    @endunless

    <div class="flex flex-col sm:flex-row gap-2 mb-4">
        <x-input wire:model.live.debounce.300ms="search" placeholder="Search by name or phone"
                 icon="o-magnifying-glass" class="flex-1" clearable />
        <x-select wire:model.live="statusFilter" placeholder="Any status"
                  :options="[
                      ['id' => 'active',  'name' => 'Covered'],
                      ['id' => 'waiting', 'name' => 'Waiting period'],
                      ['id' => 'lapsed',  'name' => 'Premium overdue'],
                      ['id' => 'pending', 'name' => 'Not yet paid'],
                  ]"
                  option-value="id" option-label="name" class="sm:w-52" />
    </div>

    @forelse($subscriptions as $subscription)
        @php
            $claimable = $subscription->isClaimable();
            $remaining = $subscription->coverRemaining();
        @endphp

        <x-card class="mb-3">
            <div class="flex flex-col md:flex-row md:items-start gap-4">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="font-bold truncate">{{ $subscription->customer->name }}</span>
                        <span class="badge badge-sm
                            @class([
                                'badge-success' => $subscription->status === 'active',
                                'badge-info'    => $subscription->status === 'waiting',
                                'badge-error'   => $subscription->status === 'lapsed',
                                'badge-ghost'   => in_array($subscription->status, ['pending', 'cancelled']),
                            ])">
                            @switch($subscription->status)
                                @case('active')  Covered @break
                                @case('waiting') Waiting @break
                                @case('lapsed')  Overdue @break
                                @case('pending') Not paid @break
                                @default {{ ucfirst($subscription->status) }}
                            @endswitch
                        </span>
                    </div>

                    <div class="text-sm text-base-content/70">
                        {{ $subscription->plan->name }} &middot; {{ $subscription->plan->summary() }}
                    </div>

                    <div class="text-xs text-base-content/60 mt-1">
                        {{ $subscription->customer->phone ?: 'No number on file' }}
                        @if($subscription->period_end)
                            &middot; paid to {{ $subscription->period_end->format('j M Y') }}
                        @endif
                    </div>

                    {{-- The reason in the same words the cashier would say it. --}}
                    @unless($claimable)
                        <div class="text-xs text-warning mt-1">{{ $subscription->blockedReason() }}</div>
                    @endunless
                </div>

                <div class="md:text-right shrink-0">
                    <div class="text-xs text-base-content/50">Left this month</div>
                    <div class="text-xl font-bold tabular-nums {{ $claimable && $remaining > 0 ? 'text-success' : 'text-base-content/40' }}">
                        &#8358;{{ number_format($remaining, 2) }}
                    </div>
                    <div class="text-xs text-base-content/50">
                        of &#8358;{{ number_format((float) $subscription->plan->monthly_cover, 2) }}
                    </div>
                </div>
            </div>

            @if($canManage)
                <x-slot:actions>
                    <x-button label="Record premium" icon="o-banknotes"
                              wire:click="openPremium({{ $subscription->id }})" class="btn-ghost btn-xs" />
                    <x-button label="Cancel cover"
                              wire:click="openCancel({{ $subscription->id }})" class="btn-ghost btn-xs text-error" />
                </x-slot:actions>
            @endif
        </x-card>
    @empty
        <x-card>
            <div class="text-center py-8">
                <x-icon name="o-shield-check" class="w-10 h-10 mx-auto text-base-content/20" />
                <p class="text-base-content/60 mt-2">Nobody is on cover yet.</p>
            </div>
        </x-card>
    @endforelse

    {{ $subscriptions->links() }}

    {{-- ── signing somebody up ─────────────────────────────────────── --}}
    <x-modal wire:model="signUpModal" title="Sign up a customer" box-class="max-w-lg">
        <x-form wire:submit="signUp">
            <div>
                <x-input label="Customer" wire:model.live.debounce.300ms="customerSearch"
                         placeholder="Search by name or phone" icon="o-magnifying-glass" />
                @error('newCustomerId') <p class="text-error text-xs mt-1">{{ $message }}</p> @enderror

                @if($customers->isNotEmpty())
                    <div class="border border-base-300 rounded-lg mt-2 max-h-48 overflow-y-auto">
                        @foreach($customers as $customer)
                            <button type="button" wire:click="$set('newCustomerId', {{ $customer->id }})"
                                    class="w-full text-left px-3 py-2 hover:bg-base-200 text-sm flex justify-between items-center
                                           {{ $newCustomerId === $customer->id ? 'bg-primary/10' : '' }}">
                                <span class="truncate">{{ $customer->name }}</span>
                                <span class="text-xs text-base-content/50 shrink-0 ml-2">{{ $customer->phone }}</span>
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>

            <x-select label="Plan" wire:model.live="newPlanId" placeholder="Choose a plan"
                      :options="$plans->map(fn($p) => ['id' => $p->id, 'name' => $p->name . ' — ' . $p->summary()])"
                      option-value="id" option-label="name" />

            <x-toggle label="Take the first premium now" wire:model.live="collectFirstPremium" />

            @if($collectFirstPremium)
                <x-select label="Paid by" wire:model="firstPremiumMethod"
                          :options="[
                              ['id' => 'cash', 'name' => 'Cash'],
                              ['id' => 'card', 'name' => 'Card'],
                              ['id' => 'transfer', 'name' => 'Transfer'],
                          ]" option-value="id" option-label="name" />
            @endif

            @if($newPlanId)
                @php $chosen = $plans->firstWhere('id', (int) $newPlanId); @endphp
                @if($chosen)
                    {{-- Said plainly at sign-up, because being told "you are
                         covered" and turned away that afternoon is the thing
                         that loses a customer for good. --}}
                    <div class="rounded-lg border border-base-300 bg-base-200/40 p-3 text-sm">
                        @if($chosen->waiting_days > 0)
                            Cover begins in <strong>{{ $chosen->waiting_days }} days</strong>, on
                            <strong>{{ now()->addDays($chosen->waiting_days)->format('j M Y') }}</strong>.
                            Tell the customer this now.
                        @else
                            Cover begins immediately.
                        @endif
                        <div class="text-base-content/60 mt-1">
                            &#8358;{{ number_format((float) $chosen->monthly_cover, 2) }} of medicine a month,
                            @if($chosen->copay_percent > 0)
                                with a {{ $chosen->copay_percent }}% share to pay each time.
                            @else
                                free at the counter.
                            @endif
                            Unused cover does not carry over.
                        </div>
                    </div>
                @endif
            @endif

            <x-slot:actions>
                <x-button label="Cancel" wire:click="$set('signUpModal', false)" class="btn-ghost" />
                <x-button label="Sign up" type="submit" class="btn-primary" spinner="signUp" />
            </x-slot:actions>
        </x-form>
    </x-modal>

    {{-- ── taking a premium ────────────────────────────────────────── --}}
    <x-modal wire:model="premiumModal" title="Record a premium" box-class="max-w-md">
        <x-form wire:submit="recordPremium">
            <x-input label="Amount" wire:model="premiumAmount" type="number" step="0.01" min="1" prefix="&#8358;" />

            <x-select label="Paid by" wire:model.live="premiumMethod"
                      :options="[
                          ['id' => 'cash', 'name' => 'Cash'],
                          ['id' => 'card', 'name' => 'Card'],
                          ['id' => 'transfer', 'name' => 'Transfer'],
                          ['id' => 'paystack', 'name' => 'Paystack'],
                      ]" option-value="id" option-label="name" />

            @if(in_array($premiumMethod, ['transfer', 'paystack', 'card']))
                <x-input label="Reference (optional)" wire:model="premiumReference"
                         placeholder="What the bank or Paystack calls it" />
            @endif

            <div class="rounded-lg border border-base-300 bg-base-200/40 p-3 text-sm">
                This buys a full month from today, or from the end of the month already
                paid for &mdash; paying early never costs the customer days.
                The month's cover starts again at nothing spent.
            </div>

            <x-slot:actions>
                <x-button label="Cancel" wire:click="$set('premiumModal', false)" class="btn-ghost" />
                <x-button label="Record" type="submit" class="btn-primary" spinner="recordPremium" />
            </x-slot:actions>
        </x-form>
    </x-modal>

    {{-- ── stopping ────────────────────────────────────────────────── --}}
    <x-modal wire:model="cancelModal" title="Cancel this cover?" box-class="max-w-md">
        <x-form wire:submit="cancelCover">
            <p class="text-sm text-base-content/70">
                Cover stops straight away and nothing more will be paid for at the till.
                Premiums already taken are not refunded by this &mdash; do that at the counter.
            </p>

            <x-input label="Reason (optional)" wire:model="cancelReason"
                     placeholder="Customer asked to stop" />

            <x-slot:actions>
                <x-button label="Keep it" wire:click="$set('cancelModal', false)" class="btn-ghost" />
                <x-button label="Cancel cover" type="submit" class="btn-error" spinner="cancelCover" />
            </x-slot:actions>
        </x-form>
    </x-modal>
</div>
