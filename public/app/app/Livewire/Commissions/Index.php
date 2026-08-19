<?php

namespace App\Livewire\Commissions;

use App\Models\AppSetting;
use App\Models\PromoterCode;
use App\Models\ReferralCommission;
use App\Models\User;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

class Index extends Component
{
    use Toast, WithPagination;

    public bool $payModal    = false;
    public ?int $payUserId   = null;
    public bool $detailDrawer = false;
    public ?int $viewUserId  = null;

    /** Which day's promoter activity to review. Defaults to today. */
    #[Url]
    public string $date = '';

    public function mount(): void
    {
        $this->date = $this->date ?: today()->format('Y-m-d');
    }

    /**
     * Credit a promoter for a customer who was registered and given a code but
     * never connected — e.g. the portal was down. Deliberately manual.
     */
    public function creditCode(int $codeId): void
    {
        if (! $this->isManager()) return;

        $code = PromoterCode::find($codeId);

        if (! $code || $code->redeemed_at) {
            $this->error('That code has already connected, or no longer exists.');
            return;
        }

        if (ReferralCommission::where('user_id', $code->user_id)
            ->where('customer_id', $code->customer_id)->exists()) {
            $this->error('This promoter has already been paid for that customer.');
            return;
        }

        ReferralCommission::create([
            'user_id'     => $code->user_id,
            'customer_id' => $code->customer_id,
            'amount'      => (float) AppSetting::get('commission_amount', 100),
        ]);

        $this->success('Commission credited.');
    }

    private function isManager(): bool
    {
        return (bool) array_intersect(auth()->user()->role ?? [], ['admin', 'branch_manager']);
    }

    public function openPay(int $userId): void
    {
        if (!$this->isManager()) return;
        $this->payUserId = $userId;
        $this->payModal  = true;
    }

    public function confirmPay(): void
    {
        if (!$this->isManager() || !$this->payUserId) return;

        $count = ReferralCommission::where('user_id', $this->payUserId)
            ->whereNull('paid_at')
            ->update([
                'paid_at' => now(),
                'paid_by' => auth()->id(),
            ]);

        $this->payModal  = false;
        $this->payUserId = null;
        $this->success("{$count} commission(s) marked as paid.");
    }

    public function openDetail(int $userId): void
    {
        if (!$this->isManager()) return;

        $this->viewUserId   = $userId;
        $this->detailDrawer = true;
    }

    public function render()
    {
        $isManager = $this->isManager();

        if ($isManager) {
            $allEligible = User::with('referralCommissions')
                ->get()
                ->filter(fn($u) => array_intersect($u->role ?? [], ReferralCommission::eligibleRoles()))
                ->map(function ($u) {
                    $all     = $u->referralCommissions;
                    $pending = $all->filter(fn($c) => is_null($c->paid_at));

                    // Targets are a promoter concept; cashiers and sales don't have one.
                    $isPromoter = in_array('promoter', $u->role ?? []);

                    return (object) [
                        'id'           => $u->id,
                        'name'         => $u->name,
                        'role'         => $u->role,
                        'count'        => $all->count(),
                        'total_earned' => (float) $all->sum('amount'),
                        'total_paid'   => (float) $all->reject(fn($c) => is_null($c->paid_at))->sum('amount'),
                        'pending'      => (float) $pending->sum('amount'),
                        'progress'     => $isPromoter ? $u->promoterProgressOn($this->date) : null,
                    ];
                })
                ->values();

            // Registered and given a code on the selected day, but never connected.
            $stalledCodes = PromoterCode::with('customer', 'promoter')
                ->whereDate('created_at', $this->date)
                ->whereNull('redeemed_at')
                ->get()
                ->map(function ($code) {
                    $code->already_paid = ReferralCommission::where('user_id', $code->user_id)
                        ->where('customer_id', $code->customer_id)->exists();
                    return $code;
                });

            $overallPending = $allEligible->sum('pending');
            $overallPaid    = $allEligible->sum('total_paid');

            $detailUser        = $this->viewUserId ? User::find($this->viewUserId) : null;
            $detailCommissions = $this->viewUserId
                ? ReferralCommission::with('customer')
                    ->where('user_id', $this->viewUserId)
                    ->latest()
                    ->get()
                : collect();

            $payUser   = $this->payUserId ? User::find($this->payUserId) : null;
            $payAmount = $this->payUserId
                ? (float) ReferralCommission::where('user_id', $this->payUserId)->whereNull('paid_at')->sum('amount')
                : 0.0;

            return view('livewire.commissions.index', compact(
                'isManager', 'allEligible', 'overallPending', 'overallPaid',
                'detailUser', 'detailCommissions', 'payUser', 'payAmount', 'stalledCodes'
            ));
        }

        $userId        = auth()->id();
        $myTotal       = (float) ReferralCommission::where('user_id', $userId)->sum('amount');
        $myPaid        = (float) ReferralCommission::where('user_id', $userId)->whereNotNull('paid_at')->sum('amount');
        $myPending     = $myTotal - $myPaid;
        $myCommissions = ReferralCommission::with('customer')
            ->where('user_id', $userId)
            ->latest()
            ->paginate(20);

        return view('livewire.commissions.index', compact(
            'isManager', 'myTotal', 'myPaid', 'myPending', 'myCommissions'
        ));
    }
}
