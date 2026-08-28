<?php

namespace App\Livewire;

use App\Models\PharmacistCall;
use Livewire\Component;
use Mary\Traits\Toast;

/**
 * The button in the top bar, and the banner the pharmacist sees.
 *
 * One component doing both sides so there is a single description of what a
 * waiting call is. It sits in the layout, so the banner reaches a pharmacist
 * on whatever page they happen to be on rather than only on a dashboard they
 * may not be looking at.
 *
 * Polled rather than pushed. There is no realtime infrastructure here, and the
 * till and cashier screens already refresh this way - adding websockets for
 * one button would be a lot of moving parts for a five second delay.
 */
class CallPharmacist extends Component
{
    use Toast;

    /** Who may ring: the people who stand in front of customers. */
    private const CALLERS = ['sales', 'cashier', 'admin', 'branch_manager'];

    /** Who is being rung. */
    private const RESPONDERS = ['pharmacist', 'admin'];

    public function canCall(): bool
    {
        return (bool) array_intersect(auth()->user()->role ?? [], self::CALLERS);
    }

    public function canRespond(): bool
    {
        return (bool) array_intersect(auth()->user()->role ?? [], self::RESPONDERS);
    }

    public function call(): void
    {
        if (! $this->canCall()) {
            return;
        }

        PharmacistCall::ring(auth()->user());

        $this->success('A pharmacist has been called.');
    }

    public function acknowledge(int $id): void
    {
        if (! $this->canRespond()) {
            return;
        }

        $call = PharmacistCall::findOrFail($id);

        if ($call->acknowledged_at) {
            return;   // somebody else got there first
        }

        $call->forceFill([
            'acknowledged_by' => auth()->id(),
            'acknowledged_at' => now(),
        ])->save();

        $this->success('The counter has been told you are coming.');
    }

    public function render()
    {
        $user = auth()->user();

        // What this person needs to see, which differs by who they are: the
        // caller wants to know somebody is coming, the pharmacist wants to
        // know somebody is waiting.
        $waiting = $this->canRespond()
            ? PharmacistCall::waiting()->with('caller')->oldest()->first()
            : null;

        $mine = $this->canCall()
            ? PharmacistCall::where('called_by', $user->id)
                ->where('created_at', '>=', now()->subMinutes(PharmacistCall::EXPIRES_AFTER_MINUTES))
                ->with('acknowledgedBy')
                ->latest()
                ->first()
            : null;

        return view('livewire.call-pharmacist', [
            'waiting' => $waiting,
            'mine'    => $mine,
        ]);
    }
}
