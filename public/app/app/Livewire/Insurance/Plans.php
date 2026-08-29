<?php

namespace App\Livewire\Insurance;

use App\Models\Category;
use App\Models\InsurancePlan;
use App\Models\InsuranceSubscription;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

/**
 * The plans the pharmacy offers.
 *
 * Setting a premium against a cover is the single decision that determines
 * whether the scheme makes or loses money, so the form shows the exposure it
 * is committing to rather than leaving it to be discovered a month later.
 */
class Plans extends Component
{
    use Toast, WithPagination;

    public bool $planModal = false;
    public ?int $planId    = null;

    public string $name            = '';
    public string $code            = '';
    public string $description     = '';
    public string $monthly_premium = '';
    public string $monthly_cover   = '';
    public int    $copay_percent   = 0;
    public int    $waiting_days    = 30;
    public int    $grace_days      = 7;
    public array  $excluded_categories = [];
    public bool   $is_active       = true;

    /** Writes belong to the people who set prices. */
    public function canManage(): bool
    {
        return (bool) array_intersect(auth()->user()->role ?? [], ['admin', 'branch_manager']);
    }

    public function createPlan(): void
    {
        if (! $this->canManage()) {
            return;
        }

        $this->reset([
            'planId', 'name', 'code', 'description', 'monthly_premium', 'monthly_cover',
            'copay_percent', 'waiting_days', 'grace_days', 'excluded_categories',
        ]);
        $this->waiting_days = 30;
        $this->grace_days   = 7;
        $this->is_active    = true;
        $this->resetValidation();
        $this->planModal = true;
    }

    public function editPlan(int $id): void
    {
        if (! $this->canManage()) {
            return;
        }

        $plan = InsurancePlan::findOrFail($id);

        $this->planId              = $plan->id;
        $this->name                = $plan->name;
        $this->code                = $plan->code;
        $this->description         = (string) $plan->description;
        $this->monthly_premium     = (string) $plan->monthly_premium;
        $this->monthly_cover       = (string) $plan->monthly_cover;
        $this->copay_percent       = (int) $plan->copay_percent;
        $this->waiting_days        = (int) $plan->waiting_days;
        $this->grace_days          = (int) $plan->grace_days;
        $this->excluded_categories = array_map('intval', $plan->excluded_categories ?? []);
        $this->is_active           = (bool) $plan->is_active;

        $this->resetValidation();
        $this->planModal = true;
    }

    public function savePlan(): void
    {
        if (! $this->canManage()) {
            return;
        }

        $this->validate([
            'name'            => 'required|string|max:100',
            'code'            => 'required|string|max:40|unique:insurance_plans,code,' . ($this->planId ?? 'NULL'),
            'monthly_premium' => 'required|numeric|min:1',
            // A plan with no ceiling is an open promise against stock the
            // pharmacy has already paid for.
            'monthly_cover'   => 'required|numeric|min:1',
            'copay_percent'   => 'required|integer|min:0|max:90',
            'waiting_days'    => 'required|integer|min:0|max:365',
            'grace_days'      => 'required|integer|min:0|max:60',
        ], [], [
            'monthly_premium' => 'monthly premium',
            'monthly_cover'   => 'monthly cover',
            'copay_percent'   => 'co-pay',
            'waiting_days'    => 'waiting period',
            'grace_days'      => 'grace period',
        ]);

        InsurancePlan::updateOrCreate(['id' => $this->planId], [
            'name'                => $this->name,
            'code'                => strtoupper(trim($this->code)),
            'description'         => $this->description ?: null,
            'monthly_premium'     => $this->monthly_premium,
            'monthly_cover'       => $this->monthly_cover,
            'copay_percent'       => $this->copay_percent,
            'waiting_days'        => $this->waiting_days,
            'grace_days'          => $this->grace_days,
            'excluded_categories' => $this->excluded_categories ?: null,
            'is_active'           => $this->is_active,
        ]);

        $this->planModal = false;
        $this->success($this->planId ? 'Plan updated.' : 'Plan created.');
    }

    /**
     * Stop offering a plan without touching anybody already on it.
     *
     * Deleting is not offered: subscriptions point at the plan for their cover
     * and their waiting period, and the claims already paid out under it have
     * to stay explicable.
     */
    public function toggleActive(int $id): void
    {
        if (! $this->canManage()) {
            return;
        }

        $plan = InsurancePlan::findOrFail($id);
        $plan->update(['is_active' => ! $plan->is_active]);

        $this->success($plan->is_active
            ? $plan->name . ' is being offered again.'
            : $plan->name . ' is no longer offered to new customers. Existing cover is unchanged.');
    }

    /**
     * What this plan would cost if everybody on it claimed the lot.
     *
     * The figure worth seeing before agreeing a premium, because it is the
     * pharmacy's own money and its own stock at risk.
     */
    public function exposure(InsurancePlan $plan): float
    {
        return $plan->monthlyExposure();
    }

    public function render()
    {
        $plans = InsurancePlan::withCount([
            'subscriptions as live_count' => fn ($q) => $q->whereIn('status', [
                InsuranceSubscription::ACTIVE, InsuranceSubscription::WAITING,
            ]),
        ])->orderBy('monthly_premium')->paginate(15);

        return view('livewire.insurance.plans', [
            'plans'      => $plans,
            'categories' => Category::orderBy('name')->get(),
            'canManage'  => $this->canManage(),
        ]);
    }
}
