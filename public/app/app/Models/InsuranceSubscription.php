<?php

namespace App\Models;

use App\Models\Traits\BelongsToBranch;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One customer's cover.
 *
 * The states are deliberately explicit rather than derived on the fly, because
 * a cashier needs a straight answer at the counter and an auditor needs to see
 * what was true at the time:
 *
 *   pending   signed up, first premium not yet taken - no cover
 *   waiting   paid, but inside the waiting period    - no cover
 *   active    paid and claimable
 *   lapsed    premium overdue past grace             - no cover
 *   cancelled ended                                  - no cover
 *
 * Only `active` pays for anything, and isClaimable() re-checks the dates
 * rather than trusting the stored status, because a subscription lapses by the
 * calendar turning over - not by anybody remembering to run something.
 */
class InsuranceSubscription extends Model
{
    use BelongsToBranch;

    public const PENDING   = 'pending';
    public const WAITING   = 'waiting';
    public const ACTIVE    = 'active';
    public const LAPSED    = 'lapsed';
    public const CANCELLED = 'cancelled';

    protected $fillable = [
        'customer_id', 'insurance_plan_id', 'branch_id', 'status',
        'started_at', 'waiting_until', 'period_start', 'period_end',
        'cover_used', 'cancelled_at', 'cancelled_reason', 'created_by',
    ];

    protected $casts = [
        'started_at'    => 'datetime',
        'waiting_until' => 'datetime',
        'cancelled_at'  => 'datetime',
        'period_start'  => 'date',
        'period_end'    => 'date',
        'cover_used'    => 'decimal:2',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(InsurancePlan::class, 'insurance_plan_id');
    }

    public function premiums(): HasMany
    {
        return $this->hasMany(InsurancePremium::class);
    }

    public function claims(): HasMany
    {
        return $this->hasMany(InsuranceClaim::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── what state it is really in ──────────────────────────────────────

    /**
     * Has the paid-for period run out, grace included?
     *
     * Read rather than scheduled. There is no cron on this host, so a status
     * column alone would say "active" indefinitely after the customer stopped
     * paying - and the cashier would hand over medicine on it.
     */
    public function isExpired(?CarbonInterface $at = null): bool
    {
        $at = $at ? Carbon::parse($at) : now();

        if (! $this->period_end) {
            return true;
        }

        $grace = (int) ($this->plan->grace_days ?? 0);

        return $at->startOfDay()->gt($this->period_end->copy()->addDays($grace));
    }

    public function isWaiting(?CarbonInterface $at = null): bool
    {
        $at = $at ? Carbon::parse($at) : now();

        return $this->waiting_until !== null && $at->lt($this->waiting_until);
    }

    /** Can this subscription pay for anything right now? */
    public function isClaimable(?CarbonInterface $at = null): bool
    {
        if (in_array($this->status, [self::CANCELLED, self::PENDING], true)) {
            return false;
        }

        return ! $this->isExpired($at) && ! $this->isWaiting($at);
    }

    /**
     * Why a customer standing at the counter is not covered, in words a
     * cashier can repeat to them.
     */
    public function blockedReason(?CarbonInterface $at = null): ?string
    {
        if ($this->isClaimable($at)) {
            return null;
        }

        return match (true) {
            $this->status === self::CANCELLED => 'This cover was cancelled on '
                . $this->cancelled_at?->format('j M Y') . '.',
            $this->status === self::PENDING   => 'The first premium has not been paid yet.',
            $this->isWaiting($at)             => 'Cover starts on '
                . $this->waiting_until->format('j M Y') . '.',
            default                           => 'The premium is overdue. Cover ended on '
                . $this->period_end?->format('j M Y') . '.',
        };
    }

    // ── the cover itself ────────────────────────────────────────────────

    /**
     * What is left to spend this period.
     *
     * Nothing at all if the subscription is not claimable, so a lapsed
     * customer never sees a balance that implies they can use it.
     */
    public function coverRemaining(?CarbonInterface $at = null): float
    {
        if (! $this->isClaimable($at)) {
            return 0.0;
        }

        return max(0, round((float) $this->plan->monthly_cover - (float) $this->cover_used, 2));
    }

    /**
     * Spend against the cover, refusing to go past the ceiling.
     *
     * Conditional at the database, not read-then-write: the till and the shop
     * can both be spending the same cover, and two requests that each read
     * "₦10,000 left" would otherwise each be allowed ₦10,000. Returns what was
     * actually taken, which may be less than asked for.
     */
    public function drawDown(float $amount): float
    {
        if ($amount <= 0 || ! $this->isClaimable()) {
            return 0.0;
        }

        $ceiling = (float) $this->plan->monthly_cover;
        $take    = min($amount, $this->coverRemaining());

        if ($take <= 0) {
            return 0.0;
        }

        $applied = static::whereKey($this->getKey())
            ->whereRaw('cover_used + ? <= ?', [$take, $ceiling])
            ->update(['cover_used' => \Illuminate\Support\Facades\DB::raw('cover_used + ' . $take)]);

        if (! $applied) {
            // Somebody else spent it between the read and the write. Re-read
            // and take whatever is genuinely left, rather than overdrawing.
            $this->refresh();
            $take = min($amount, $this->coverRemaining());

            if ($take <= 0) {
                return 0.0;
            }

            static::whereKey($this->getKey())
                ->whereRaw('cover_used + ? <= ?', [$take, $ceiling])
                ->update(['cover_used' => \Illuminate\Support\Facades\DB::raw('cover_used + ' . $take)]);
        }

        $this->refresh();

        return $take;
    }

    /** Give cover back - a returned item, or a sale that was voided. */
    public function refund(float $amount): void
    {
        if ($amount <= 0) {
            return;
        }

        static::whereKey($this->getKey())->update([
            'cover_used' => \Illuminate\Support\Facades\DB::raw(
                'CASE WHEN cover_used - ' . $amount . ' < 0 THEN 0 ELSE cover_used - ' . $amount . ' END'
            ),
        ]);

        $this->refresh();
    }

    // ── moving through the months ───────────────────────────────────────

    /**
     * Record a premium and open the period it buys.
     *
     * The period runs from the day it is paid, not from the first of the
     * month, so somebody joining on the 20th gets a full month rather than
     * ten days for the same money.
     *
     * Unused cover from the previous period is not carried over. That is what
     * makes the pool work - this month's premiums pay this month's claims -
     * and it is why cover_used resets here rather than accumulating.
     */
    public function recordPremium(
        float $amount,
        string $method,
        ?string $reference = null,
        ?int $recordedBy = null,
        ?CarbonInterface $at = null,
    ): InsurancePremium {
        $at = $at ? Carbon::parse($at) : now();

        // A renewal paid before the current period runs out extends from its
        // end, so paying early never costs the customer days.
        $start = ($this->period_end && $this->period_end->gte($at->copy()->startOfDay()))
            ? $this->period_end->copy()->addDay()
            : $at->copy()->startOfDay();

        $end = $start->copy()->addMonth()->subDay();

        $premium = $this->premiums()->create([
            'amount'       => $amount,
            'period_start' => $start,
            'period_end'   => $end,
            'method'       => $method,
            'reference'    => $reference,
            'paid_at'      => $at,
            'recorded_by'  => $recordedBy,
            'branch_id'    => $this->branch_id,
        ]);

        $first = $this->started_at === null;

        $this->forceFill([
            'started_at'    => $this->started_at ?? $at,
            'waiting_until' => $first
                ? $at->copy()->addDays((int) $this->plan->waiting_days)
                : $this->waiting_until,
            'period_start'  => $start,
            'period_end'    => $end,
            // A fresh period is a fresh ceiling.
            'cover_used'    => 0,
        ])->save();

        $this->forceFill(['status' => $this->isWaiting($at) ? self::WAITING : self::ACTIVE])->save();

        return $premium;
    }

    public function cancel(?string $reason = null): void
    {
        $this->forceFill([
            'status'           => self::CANCELLED,
            'cancelled_at'     => now(),
            'cancelled_reason' => $reason,
        ])->save();
    }

    /**
     * Bring the stored status in line with the calendar.
     *
     * Called when the subscription is looked at rather than on a schedule.
     * isClaimable() is the authority either way; this only keeps lists and
     * reports honest.
     */
    public function refreshStatus(): static
    {
        if (in_array($this->status, [self::CANCELLED, self::PENDING], true)) {
            return $this;
        }

        $should = match (true) {
            $this->isExpired() => self::LAPSED,
            $this->isWaiting() => self::WAITING,
            default            => self::ACTIVE,
        };

        if ($should !== $this->status) {
            $this->forceFill(['status' => $should])->save();
        }

        return $this;
    }

    // ── finding them ────────────────────────────────────────────────────

    public function scopeLive($query)
    {
        return $query->whereIn('status', [self::PENDING, self::WAITING, self::ACTIVE, self::LAPSED]);
    }

    /**
     * The subscription to use for a customer at the counter, if any.
     *
     * Returns a lapsed one rather than nothing on purpose: the cashier should
     * be told "their premium is overdue", not left to conclude the customer
     * never had cover.
     */
    public static function forCustomer(int $customerId): ?self
    {
        return static::with('plan')
            ->where('customer_id', $customerId)
            ->live()
            ->latest('id')
            ->first()
            ?->refreshStatus();
    }
}
