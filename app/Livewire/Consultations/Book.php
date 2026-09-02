<?php

namespace App\Livewire\Consultations;

use App\Models\Appointment;
use App\Models\Customer;
use App\Support\ConsultationPricing;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Mary\Traits\Toast;

/**
 * Booking a consultation from the shop.
 *
 * The customer says when they would like it; staff confirm or propose another
 * time. There is no slot calendar, deliberately - it would mean maintaining
 * each provider's hours, durations and absences, and a pharmacy already does
 * this by ringing the person back.
 *
 * Identity comes from the phone number. It is matched to an existing customer
 * or a new record is created, because the free allowance is per customer and
 * a booking with nobody attached could claim it again and again.
 */
#[Layout('layouts.public')]
class Book extends Component
{
    use Toast;

    public string $name = '';
    public string $phone = '';
    public string $mode = 'physical';
    public string $provider_type = 'pharmacist';
    public string $contact = '';
    public string $preferred_date = '';
    public string $preferred_time = '';
    public string $about = '';

    /** Set once a booking is made, so the page can show what happens next. */
    public ?int $bookedId = null;

    public function mount(): void
    {
        $this->preferred_date = now()->addDay()->format('Y-m-d');

        // Someone already signed in should not have to type what we know.
        $customer = auth('customer')->user();

        if ($customer instanceof Customer) {
            $this->name  = $customer->name;
            $this->phone = $customer->phone ?? '';
        }
    }

    /**
     * The customer this booking belongs to, matched on phone.
     *
     * Null until a phone number is entered, which is why the price panel only
     * appears once there is one - the free allowance cannot be known before.
     */
    public function customer(): ?Customer
    {
        $phone = trim($this->phone);

        // Any spelling of the number finds the same person. An exact match
        // made 0803... and +234803... two customers, each with their own free
        // consultation - which is the whole allowance, leaking.
        return $phone === '' ? null : Customer::findByPhone($phone);
    }

    public function isFree(): bool
    {
        return ConsultationPricing::isFreeFor($this->customer());
    }

    public function price(): float
    {
        return ConsultationPricing::price($this->provider_type, $this->mode);
    }

    public function charge(): float
    {
        return $this->isFree() ? 0.0 : $this->price();
    }

    public function freeRemaining(): int
    {
        return ConsultationPricing::freeRemainingFor($this->customer());
    }

    public function book()
    {
        $this->validate([
            'name'           => 'required|string|max:255',
            'phone'          => 'required|string|max:20',
            'mode'           => 'required|in:' . implode(',', array_keys(ConsultationPricing::MODES)),
            'provider_type'  => 'required|in:' . implode(',', array_keys(ConsultationPricing::PROVIDERS)),
            // A call or a chat needs somewhere to reach them; a visit does not.
            'contact'        => 'nullable|string|max:100|required_unless:mode,physical',
            'preferred_date' => 'required|date|after_or_equal:today',
            'preferred_time' => 'required',
            'about'          => 'nullable|string|max:1000',
        ], [], [
            'contact'        => 'contact',
            'preferred_date' => 'preferred date',
            'preferred_time' => 'preferred time',
        ]);

        $appointment = DB::transaction(function () {
            // Matched on the comparable form, created with the number as it
            // was typed - that is what staff recognise and what gets printed.
            $customer = Customer::findByPhone($this->phone) ?? Customer::create([
                'phone' => trim($this->phone),
                'name'  => strtoupper(trim($this->name)),
                'type'  => 'retail',
            ]);

            // Priced here, inside the transaction, so two bookings made at once
            // cannot both be told they are the free one.
            $free = ConsultationPricing::isFreeFor($customer);

            return Appointment::create([
                'customer_id'      => $customer->id,
                'title'            => 'Consultation (' . ConsultationPricing::MODES[$this->mode] . ')',
                'description'      => $this->about ?: null,
                'scheduled_at'     => $this->preferred_date . ' ' . $this->preferred_time,
                'duration_minutes' => 30,
                // Requested, not scheduled: staff confirm the time or propose
                // another, which is the whole shape of this flow.
                'status'           => 'requested',
                'mode'             => $this->mode,
                'provider_type'    => $this->provider_type,
                'contact'          => $this->contact ?: trim($this->phone),
                'was_free'         => $free,
                'price'            => $free ? 0 : ConsultationPricing::price($this->provider_type, $this->mode),
                'payment_status'   => $free ? 'free' : 'pending',
                // Null marks it as booked by the customer rather than staff.
                'booked_by'        => null,
            ]);
        });

        if ($appointment->payment_status === 'free') {
            $this->bookedId = $appointment->id;

            return null;
        }

        return redirect()->route('consultation.pay', $appointment);
    }

    public function render()
    {
        return view('livewire.consultations.book', [
            'booked' => $this->bookedId ? Appointment::find($this->bookedId) : null,
        ]);
    }
}
