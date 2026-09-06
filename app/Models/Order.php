<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Order extends Model
{
    protected $fillable = [
        'order_number', 'public_token', 'customer_id', 'guest_name', 'guest_email', 'guest_phone',
        'subtotal', 'delivery_fee', 'delivery_zone_id', 'delivery_area', 'total_amount',
        'insurance_covered', 'insurance_subscription_id',
        'fulfillment_type', 'payment_method', 'payment_status', 'payment_reference',
        'status', 'claimed_by', 'claimed_at', 'delivery_address', 'delivery_phone', 'note',
        'prescription_path', 'paid_at',
        'prescription_status',
        'cashier_verified_at', 'verified_by',
        'delivery_person_name', 'delivery_person_phone', 'delivery_user_id', 'dispatched_at',
    ];

    protected $casts = [
        'subtotal'             => 'decimal:2',
        'insurance_covered'    => 'decimal:2',
        'delivery_fee'         => 'decimal:2',
        'total_amount'         => 'decimal:2',
        'paid_at'              => 'datetime',
        'claimed_at'           => 'datetime',
        'cashier_verified_at'  => 'datetime',
        'dispatched_at'        => 'datetime',
    ];

    /**
     * Every order gets an unguessable link of its own.
     *
     * booted(), not a boot-prefixed method of our own naming: Laravel only
     * auto-boots boot{TraitName} for traits, so anything else would sit there
     * never being called and every order would go out without a token.
     */
    protected static function booted(): void
    {
        static::creating(function (self $order) {
            $order->public_token ??= Str::random(32);
        });
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function claimedByUser()
    {
        return $this->belongsTo(User::class, 'claimed_by');
    }

    public function verifiedBy()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /**
     * The area this was ordered for.
     *
     * Nullable, and may point at a zone that has since been renamed or
     * retired - which is why the order keeps delivery_area and delivery_fee
     * of its own. Read those for what the customer was actually told; read
     * this only to group today's runs.
     */
    public function deliveryZone()
    {
        return $this->belongsTo(DeliveryZone::class);
    }

    public function deliveryUser()
    {
        return $this->belongsTo(User::class, 'delivery_user_id');
    }

    /**
     * Where this order has got to, in words a customer understands.
     *
     * The status column is written for the pharmacy - "ready" means the
     * shelf work is done, which tells a customer waiting at home nothing.
     * The same word also means two different things depending on whether
     * somebody is bringing it or coming for it.
     */
    public function progressLabel(): string
    {
        $delivery = $this->fulfillment_type === 'delivery';

        return match ($this->status) {
            'pending'    => 'Order received',
            'processing' => 'Being prepared',
            'ready'      => $delivery ? 'Packed, waiting for a rider' : 'Ready to collect',
            'dispatched' => 'On its way to you',
            'completed'  => $delivery ? 'Delivered' : 'Collected',
            'cancelled'  => 'Cancelled',
            default      => ucfirst((string) $this->status),
        };
    }

    /**
     * The trail of stages, for the page the customer watches.
     *
     * Pickup skips dispatch entirely - nobody is carrying it - so the two
     * routes through the shop get different trails rather than one trail with
     * a step that never lights up.
     *
     * @return array<int, array{label: string, hint: ?string, done: bool, current: bool}>
     */
    public function progressSteps(): array
    {
        $delivery = $this->fulfillment_type === 'delivery';

        $stages = $delivery
            ? ['pending', 'processing', 'ready', 'dispatched', 'completed']
            : ['pending', 'processing', 'ready', 'completed'];

        $awaitingPayment = $this->payment_status === 'pending'
            && $this->payment_method === 'paystack';

        $hints = [
            'pending'    => $awaitingPayment
                ? 'We start on it once your payment goes through.'
                : 'The pharmacy will pick this up shortly.',
            'processing' => 'Someone is putting your order together.',
            'ready'      => $delivery
                ? 'Waiting for a rider to take it out.'
                : 'Come in when you are ready. Bring your order number.',
            'dispatched' => $this->dispatched_at
                ? 'Left the pharmacy ' . $this->dispatched_at->diffForHumans() . '.'
                : null,
            'completed'  => null,
        ];

        $at = array_search($this->status, $stages, true);

        if ($at === false) {
            $at = 0;
        }

        $steps = [];

        foreach ($stages as $i => $stage) {
            $current = $i === $at;

            $steps[] = [
                'label'   => match ($stage) {
                    'pending'    => 'Order received',
                    'processing' => 'Being prepared',
                    'ready'      => $delivery ? 'Packed, waiting for a rider' : 'Ready to collect',
                    'dispatched' => 'On its way to you',
                    'completed'  => $delivery ? 'Delivered' : 'Collected',
                },
                'hint'    => $current ? ($hints[$stage] ?? null) : null,
                // The last stage is an ending, not a thing still happening.
                'done'    => $i < $at || ($current && $stage === 'completed'),
                'current' => $current && $stage !== 'completed',
            ];
        }

        return $steps;
    }

    /** Whether there is a rider out with this one. */
    public function isOnItsWay(): bool
    {
        return $this->status === 'dispatched' && $this->dispatched_at !== null;
    }

    public function isCod(): bool
    {
        return $this->payment_status === 'pending';
    }

    public function isVerified(): bool
    {
        return $this->cashier_verified_at !== null;
    }

    public static function generateOrderNumber(): string
    {
        $last = static::latest('id')->first();
        $next = $last ? $last->id + 1 : 1;
        return 'ORD-' . now()->format('Ym') . '-' . str_pad($next, 4, '0', STR_PAD_LEFT);
    }
}
