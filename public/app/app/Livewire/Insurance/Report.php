<?php

namespace App\Livewire\Insurance;

use App\Models\InsuranceClaim;
use App\Models\InsurancePlan;
use App\Models\InsurancePremium;
use App\Models\InsuranceSubscription;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Whether the cover scheme can go on being offered.
 *
 * Two different answers, both shown, because reporting either alone would
 * mislead:
 *
 *   Premiums minus what the claims cost the pharmacy is the cash answer -
 *   money in against stock actually consumed. This is the one that says
 *   whether the scheme is solvent.
 *
 *   Premiums minus the retail value of the claims is what the shop would have
 *   taken had those customers simply walked in and bought. That figure is
 *   worse, and it is the one to weigh when deciding whether the scheme is
 *   winning enough new custom to be worth it.
 *
 * The per-subscriber table exists for the same reason an insurer has one: a
 * handful of people claiming many times their premium is normal, and it is
 * only a problem when the pool as a whole stops covering them.
 */
class Report extends Component
{
    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    public function mount(): void
    {
        $this->from = $this->from ?: now()->startOfMonth()->toDateString();
        $this->to   = $this->to ?: now()->endOfMonth()->toDateString();
    }

    private function range(): array
    {
        return [
            Carbon::parse($this->from ?: now()->startOfMonth())->startOfDay(),
            Carbon::parse($this->to ?: now()->endOfMonth())->endOfDay(),
        ];
    }

    public function thisMonth(): void
    {
        $this->from = now()->startOfMonth()->toDateString();
        $this->to   = now()->endOfMonth()->toDateString();
    }

    public function lastMonth(): void
    {
        $this->from = now()->subMonth()->startOfMonth()->toDateString();
        $this->to   = now()->subMonth()->endOfMonth()->toDateString();
    }

    public function thisYear(): void
    {
        $this->from = now()->startOfYear()->toDateString();
        $this->to   = now()->endOfYear()->toDateString();
    }

    /**
     * Every figure on the page, in one place.
     *
     * A method rather than assembled inside render() so the numbers can be
     * asserted directly. A report whose arithmetic is only reachable through
     * rendered HTML is a report nobody can check.
     */
    public function figures(): array
    {
        [$from, $to] = $this->range();

        $premiums = (float) InsurancePremium::whereBetween('paid_at', [$from, $to])->sum('amount');

        $claimedValue = (float) InsuranceClaim::whereBetween('created_at', [$from, $to])->sum('amount');
        $claimedCost  = (float) InsuranceClaim::whereBetween('created_at', [$from, $to])->sum('cost_amount');
        $claimCount   = InsuranceClaim::whereBetween('created_at', [$from, $to])->count();

        // Per plan, so a plan that is losing money can be repriced rather than
        // the whole scheme being abandoned.
        $byPlan = InsurancePlan::orderBy('name')->get()->map(function ($plan) use ($from, $to) {
            $subIds = InsuranceSubscription::withoutGlobalScopes()
                ->where('insurance_plan_id', $plan->id)->pluck('id');

            $claims = InsuranceClaim::whereIn('insurance_subscription_id', $subIds)
                ->whereBetween('created_at', [$from, $to]);

            return [
                'plan'     => $plan,
                'premiums' => (float) InsurancePremium::whereIn('insurance_subscription_id', $subIds)
                    ->whereBetween('paid_at', [$from, $to])->sum('amount'),
                'value'    => (float) (clone $claims)->sum('amount'),
                'cost'     => (float) (clone $claims)->sum('cost_amount'),
                'live'     => InsuranceSubscription::where('insurance_plan_id', $plan->id)
                    ->whereIn('status', [InsuranceSubscription::ACTIVE, InsuranceSubscription::WAITING])
                    ->count(),
            ];
        })->filter(fn ($row) => $row['premiums'] > 0 || $row['cost'] > 0 || $row['live'] > 0)->values();

        // Who is drawing most. Grouped in the database rather than in PHP, so
        // this stays usable once there are thousands of claims.
        $heaviest = InsuranceClaim::query()
            ->whereBetween('insurance_claims.created_at', [$from, $to])
            ->join('insurance_subscriptions', 'insurance_subscriptions.id', '=', 'insurance_claims.insurance_subscription_id')
            ->join('customers', 'customers.id', '=', 'insurance_subscriptions.customer_id')
            ->groupBy('customers.id', 'customers.name')
            ->select([
                'customers.id as customer_id',
                'customers.name as customer_name',
                DB::raw('SUM(insurance_claims.amount) as claimed'),
                DB::raw('SUM(insurance_claims.cost_amount) as cost'),
                DB::raw('COUNT(*) as visits'),
            ])
            ->orderByDesc('claimed')
            ->limit(15)
            ->get();

        // What each of those has paid in over the same window, so the table
        // shows both sides rather than only the alarming one.
        $paidIn = $heaviest->isEmpty()
            ? collect()
            : InsurancePremium::query()
                ->whereBetween('insurance_premiums.paid_at', [$from, $to])
                ->join('insurance_subscriptions', 'insurance_subscriptions.id', '=', 'insurance_premiums.insurance_subscription_id')
                ->whereIn('insurance_subscriptions.customer_id', $heaviest->pluck('customer_id'))
                ->groupBy('insurance_subscriptions.customer_id')
                ->select([
                    'insurance_subscriptions.customer_id',
                    DB::raw('SUM(insurance_premiums.amount) as paid'),
                ])
                ->get()
                ->mapWithKeys(fn ($row) => [(int) $row->customer_id => (float) $row->paid]);

        return [
            'premiums'      => $premiums,
            'claimedValue'  => $claimedValue,
            'claimedCost'   => $claimedCost,
            'claimCount'    => $claimCount,
            'cashResult'    => round($premiums - $claimedCost, 2),
            'tradingResult' => round($premiums - $claimedValue, 2),
            'byPlan'        => $byPlan,
            'heaviest'      => $heaviest,
            'paidIn'        => $paidIn,
            'liveCount'     => InsuranceSubscription::whereIn('status', [
                InsuranceSubscription::ACTIVE, InsuranceSubscription::WAITING,
            ])->count(),
            // What the pharmacy is committed to if everybody claimed the lot.
            'exposure'      => (float) InsuranceSubscription::query()
                ->whereIn('insurance_subscriptions.status', [
                    InsuranceSubscription::ACTIVE, InsuranceSubscription::WAITING,
                ])
                ->join('insurance_plans', 'insurance_plans.id', '=', 'insurance_subscriptions.insurance_plan_id')
                ->sum('insurance_plans.monthly_cover'),
        ];
    }

    public function render()
    {
        return view('livewire.insurance.report', $this->figures());
    }
}
