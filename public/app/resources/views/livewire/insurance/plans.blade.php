<div>
    <x-header title="Cover Plans" subtitle="What each plan promises, and what it could cost" size="text-xl">
        <x-slot:actions>
            @if($canManage)
                <x-button label="New Plan" icon="o-plus" wire:click="createPlan" class="btn-primary btn-sm" />
            @endif
        </x-slot:actions>
    </x-header>

    @unless(\App\Services\InsuranceCover::enabled())
        <div class="alert alert-info py-2 mb-4 text-sm gap-2">
            <x-icon name="o-information-circle" class="w-4 h-4 shrink-0" />
            <span>
                Cover is switched off, so nothing here affects a sale yet. Plans can be set up now
                and turned on under Settings when the pharmacy is ready.
            </span>
        </div>
    @endunless

    @forelse($plans as $plan)
        <x-card class="mb-3 {{ $plan->is_active ? '' : 'opacity-60' }}">
            <div class="flex flex-col md:flex-row md:items-start gap-4">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="font-bold">{{ $plan->name }}</span>
                        <span class="badge badge-ghost badge-sm font-mono">{{ $plan->code }}</span>
                        @unless($plan->is_active)
                            <span class="badge badge-warning badge-sm">Not offered</span>
                        @endunless
                    </div>

                    <div class="text-sm text-base-content/70 mt-1">{{ $plan->summary() }}</div>

                    @if($plan->description)
                        <p class="text-sm text-base-content/60 mt-1">{{ $plan->description }}</p>
                    @endif

                    <div class="flex flex-wrap gap-x-5 gap-y-1 text-xs text-base-content/60 mt-2">
                        <span>Waiting period: {{ $plan->waiting_days }} days</span>
                        <span>Grace: {{ $plan->grace_days }} days</span>
                        @if($plan->excluded_categories)
                            <span>{{ count($plan->excluded_categories) }} excluded {{ Str::plural('category', count($plan->excluded_categories)) }}</span>
                        @endif
                    </div>
                </div>

                {{-- The number worth looking at before agreeing a premium: what
                     this plan owes if everybody on it claims the lot. --}}
                <div class="md:text-right shrink-0">
                    <div class="text-xs text-base-content/50">On this plan</div>
                    <div class="text-lg font-bold tabular-nums">{{ $plan->live_count }}</div>
                    <div class="text-xs text-base-content/50 mt-2">Most it could pay out this month</div>
                    <div class="text-lg font-bold tabular-nums {{ $plan->live_count > 0 ? 'text-warning' : '' }}">
                        &#8358;{{ number_format($plan->live_count * (float) $plan->monthly_cover, 2) }}
                    </div>
                    <div class="text-xs text-base-content/50">
                        against &#8358;{{ number_format($plan->live_count * (float) $plan->monthly_premium, 2) }} collected
                    </div>
                </div>
            </div>

            @if($canManage)
                <x-slot:actions>
                    <x-button label="Edit" icon="o-pencil" wire:click="editPlan({{ $plan->id }})" class="btn-ghost btn-xs" />
                    <x-button label="{{ $plan->is_active ? 'Stop offering' : 'Offer again' }}"
                              wire:click="toggleActive({{ $plan->id }})"
                              class="btn-ghost btn-xs {{ $plan->is_active ? 'text-warning' : 'text-success' }}" />
                </x-slot:actions>
            @endif
        </x-card>
    @empty
        <x-card>
            <div class="text-center py-8">
                <x-icon name="o-shield-check" class="w-10 h-10 mx-auto text-base-content/20" />
                <p class="text-base-content/60 mt-2">No plans yet.</p>
                <p class="text-sm text-base-content/50">
                    A plan is a monthly premium and the most it will pay out in a month.
                </p>
            </div>
        </x-card>
    @endforelse

    {{ $plans->links() }}

    {{-- ── the plan form ───────────────────────────────────────────── --}}
    <x-modal wire:model="planModal" title="{{ $planId ? 'Edit plan' : 'New plan' }}" box-class="max-w-2xl">
        <x-form wire:submit="savePlan">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-input label="Name" wire:model="name" placeholder="Bronze" />
                <x-input label="Code" wire:model="code" placeholder="BRONZE"
                         hint="Short, and never reused" />
            </div>

            <x-textarea label="Description (optional)" wire:model="description" rows="2"
                        placeholder="How you would explain this plan to a customer" />

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-input label="Monthly premium" wire:model.live="monthly_premium"
                         type="number" step="0.01" min="1" prefix="&#8358;" />
                <x-input label="Cover per month" wire:model.live="monthly_cover"
                         type="number" step="0.01" min="1" prefix="&#8358;"
                         hint="The most this plan will pay out in a month" />
            </div>

            @if(is_numeric($monthly_premium) && is_numeric($monthly_cover) && $monthly_premium > 0)
                <div class="rounded-lg border border-base-300 bg-base-200/40 p-3 text-sm">
                    Every subscriber costs up to
                    <strong>&#8358;{{ number_format((float) $monthly_cover - (float) $monthly_premium, 2) }}</strong>
                    more than they pay, in a month where they claim everything.
                    @if((float) $monthly_cover > (float) $monthly_premium * 4)
                        <span class="text-warning block mt-1">
                            Cover is more than four times the premium. That only works if most
                            subscribers claim little &mdash; worth being sure of before offering it.
                        </span>
                    @endif
                </div>
            @endif

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <x-input label="Co-pay" wire:model="copay_percent" type="number" min="0" max="90" suffix="%"
                         hint="Subscriber's share" />
                <x-input label="Waiting period" wire:model="waiting_days" type="number" min="0" max="365" suffix="days"
                         hint="Before any claim" />
                <x-input label="Grace period" wire:model="grace_days" type="number" min="0" max="60" suffix="days"
                         hint="After a missed payment" />
            </div>

            <div>
                <label class="label"><span class="label-text font-semibold">Not covered</span></label>
                <p class="text-xs text-base-content/60 mb-2">
                    The premium was collected for medicine. Tick anything the plan should never pay for.
                </p>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-1 max-h-48 overflow-y-auto border border-base-300 rounded-lg p-2">
                    @foreach($categories as $category)
                        <label class="flex items-center gap-2 text-sm py-1 cursor-pointer">
                            <input type="checkbox" class="checkbox checkbox-xs"
                                   value="{{ $category->id }}" wire:model="excluded_categories" />
                            <span class="truncate">{{ $category->name }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <x-toggle label="Offer this plan to new customers" wire:model="is_active" />

            <x-slot:actions>
                <x-button label="Cancel" wire:click="$set('planModal', false)" class="btn-ghost" />
                <x-button label="Save Plan" type="submit" class="btn-primary" spinner="savePlan" />
            </x-slot:actions>
        </x-form>
    </x-modal>
</div>
