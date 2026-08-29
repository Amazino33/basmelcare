<?php

namespace App\Livewire\Insurance;

use App\Models\Customer;
use App\Models\InsurancePlan;
use App\Models\InsuranceSubscription;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

/**
 * Who is covered, and whether they have paid this month.
 *
 * The page a member of staff opens when a customer asks to join, or comes in
 * to pay. Everything a cashier needs to answer "am I covered?" is on the row
 * itself, because the alternative is opening each one.
 */
class Subscriptions extends Component
{
    use Toast, WithPagination;

    public string $search       = '';
    public string $statusFilter = '';

    // Signing somebody up
    public bool $signUpModal          = false;
    public string $customerSearch     = '';
    public ?int $newCustomerId        = null;
    public ?int $newPlanId            = null;
    public bool $collectFirstPremium  = true;
    public string $firstPremiumMethod = 'cash';

    // Taking a premium
    public bool $premiumModal      = false;
    public ?int $premiumSubId      = null;
    public string $premiumAmount   = '';
    public string $premiumMethod   = 'cash';
    public string $premiumReference = '';

    // Stopping cover
    public bool $cancelModal      = false;
    public ?int $cancelSubId      = null;
    public string $cancelReason   = '';

    /**
     * Who may sign somebody up and take their money.
     *
     * The counter roles, because this happens at the counter. The auditor is
     * deliberately absent from writes and present on the page.
     */
    public function canManage(): bool
    {
        return (bool) array_intersect(auth()->user()->role ?? [],
            ['admin', 'branch_manager', 'cashier', 'sales']);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    // ── signing up ──────────────────────────────────────────────────────

    public function openSignUp(): void
    {
        if (! $this->canManage()) {
            return;
        }

        $this->reset(['customerSearch', 'newCustomerId', 'newPlanId']);
        $this->collectFirstPremium  = true;
        $this->firstPremiumMethod   = 'cash';
        $this->resetValidation();
        $this->signUpModal = true;
    }

    public function signUp(): void
    {
        if (! $this->canManage()) {
            return;
        }

        $this->validate([
            'newCustomerId' => 'required|exists:customers,id',
            'newPlanId'     => 'required|exists:insurance_plans,id',
        ], [], [
            'newCustomerId' => 'customer',
            'newPlanId'     => 'plan',
        ]);

        // One live subscription per customer. Two would each carry their own
        // cover, so the same premium would buy the cover twice over.
        $existing = InsuranceSubscription::forCustomer($this->newCustomerId);

        if ($existing && $existing->status !== InsuranceSubscription::CANCELLED) {
            $this->addError('newCustomerId',
                'This customer is already on ' . $existing->plan->name . '. Cancel that cover first.');
            return;
        }

        $plan = InsurancePlan::findOrFail($this->newPlanId);

        $subscription = InsuranceSubscription::create([
            'customer_id'       => $this->newCustomerId,
            'insurance_plan_id' => $plan->id,
            'created_by'        => auth()->id(),
        ]);

        if ($this->collectFirstPremium) {
            $subscription->recordPremium(
                (float) $plan->monthly_premium,
                $this->firstPremiumMethod,
                recordedBy: auth()->id(),
            );
        }

        $this->signUpModal = false;

        // Say when cover actually starts, so nobody is told "you are covered"
        // and turned away that afternoon.
        $this->success($this->collectFirstPremium
            ? 'Signed up. Cover starts ' . $subscription->fresh()->waiting_until?->format('j M Y') . '.'
            : 'Signed up. Cover starts once the first premium is paid.');
    }

    // ── taking a premium ────────────────────────────────────────────────

    public function openPremium(int $id): void
    {
        if (! $this->canManage()) {
            return;
        }

        $subscription = InsuranceSubscription::with('plan')->findOrFail($id);

        $this->premiumSubId     = $subscription->id;
        $this->premiumAmount    = (string) $subscription->plan->monthly_premium;
        $this->premiumMethod    = 'cash';
        $this->premiumReference = '';
        $this->resetValidation();
        $this->premiumModal = true;
    }

    public function recordPremium(): void
    {
        if (! $this->canManage()) {
            return;
        }

        $this->validate([
            'premiumAmount' => 'required|numeric|min:1',
            'premiumMethod' => 'required|in:cash,card,transfer,paystack',
        ], [], [
            'premiumAmount' => 'amount',
            'premiumMethod' => 'method',
        ]);

        $subscription = InsuranceSubscription::with('plan')->findOrFail($this->premiumSubId);

        if ($subscription->status === InsuranceSubscription::CANCELLED) {
            $this->error('This cover was cancelled. Sign the customer up again instead.');
            return;
        }

        $subscription->recordPremium(
            (float) $this->premiumAmount,
            $this->premiumMethod,
            $this->premiumReference ?: null,
            auth()->id(),
        );

        $this->premiumModal = false;
        $this->success('Premium recorded. Covered until '
            . $subscription->fresh()->period_end->format('j M Y') . '.');
    }

    // ── stopping ────────────────────────────────────────────────────────

    public function openCancel(int $id): void
    {
        if (! $this->canManage()) {
            return;
        }

        $this->cancelSubId  = $id;
        $this->cancelReason = '';
        $this->cancelModal  = true;
    }

    public function cancelCover(): void
    {
        if (! $this->canManage()) {
            return;
        }

        InsuranceSubscription::findOrFail($this->cancelSubId)->cancel($this->cancelReason ?: null);

        $this->cancelModal = false;
        $this->warning('Cover cancelled. Premiums already paid are not refunded automatically.');
    }

    public function customerOptions()
    {
        if (strlen($this->customerSearch) < 2) {
            return collect();
        }

        return Customer::where('name', 'like', '%' . $this->customerSearch . '%')
            ->orWhere('phone', 'like', '%' . $this->customerSearch . '%')
            ->orderBy('name')
            ->limit(15)
            ->get(['id', 'name', 'phone']);
    }

    public function render()
    {
        $subscriptions = InsuranceSubscription::with(['customer', 'plan'])
            ->live()
            ->when($this->search, fn ($q) => $q->whereHas('customer', fn ($c) => $c
                ->where('name', 'like', '%' . $this->search . '%')
                ->orWhere('phone', 'like', '%' . $this->search . '%')))
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->latest('id')
            ->paginate(20);

        // Bring the stored status in line with the calendar as they are read.
        // There is no scheduler on this host, so a page nobody opens is the
        // only place a lapse would otherwise be noticed.
        $subscriptions->getCollection()->each->refreshStatus();

        return view('livewire.insurance.subscriptions', [
            'subscriptions' => $subscriptions,
            'plans'         => InsurancePlan::active()->orderBy('monthly_premium')->get(),
            'canManage'     => $this->canManage(),
            'customers'     => $this->customerOptions(),
        ]);
    }
}
