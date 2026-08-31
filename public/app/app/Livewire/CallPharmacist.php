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

    /**
     * Ring for a pharmacist.
     *
     * Not named call(). wire:click="call" compiles to $wire.call(), which is
     * Livewire's own way of invoking a method - so the browser asked the server
     * to run a method with no name and got a 500. The Livewire test helper
     * calls methods directly and never goes near that, which is why every test
     * passed while the button was broken.
     */
    public function callPharmacist(): void
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

    /**
     * Ring the pharmacists' phones for a call nobody answered on screen.
     *
     * Driven by the counter's own polling because there is no scheduler here.
     * That is a real constraint, not a preference: shared hosting has no cron
     * we can rely on, and a queue worker is not running either.
     *
     * The claim is atomic. This runs on every poll from every user, so two
     * browsers refreshing at the same moment would otherwise both send. The
     * update only succeeds for whoever gets there first.
     */
    private function escalate(?PharmacistCall $call): void
    {
        if (! $call || ! $call->shouldNotify()) {
            return;
        }

        $claimed = PharmacistCall::whereKey($call->id)
            ->whereNull('notified_at')
            ->whereNull('acknowledged_at')
            ->update(['notified_at' => now()]);

        if (! $claimed) {
            return;   // somebody else's poll got there first
        }

        $branch  = $call->branch?->name;
        $message = 'A customer is waiting'
            . ($branch ? ' at ' . $branch : '')
            . '. The counter asked for a pharmacist '
            . $call->created_at->diffForHumans() . '.';

        // Sent inline rather than queued: no worker runs on this host, so a
        // queued job would simply never fire. Recipients are one or two
        // people, which is slow but tolerable inside a poll.
        foreach ($call->notifiable() as $pharmacist) {
            try {
                app(\App\Services\WhatsAppService::class)->send($pharmacist->phone, $message);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning(
                    'Pharmacist call alert failed for ' . $pharmacist->phone . ': ' . $e->getMessage()
                );
            }
        }
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

        // The counter's browser is the one reliably open while somebody waits,
        // so the escalation check rides on its poll.
        $mine = $this->canCall()
            ? PharmacistCall::where('called_by', $user->id)
                ->where('created_at', '>=', now()->subMinutes(PharmacistCall::EXPIRES_AFTER_MINUTES))
                ->with('acknowledgedBy')
                ->latest()
                ->first()
            : null;

        $this->escalate($mine ?? $waiting);

        return view('livewire.call-pharmacist', [
            'waiting' => $waiting,
            'mine'    => $mine,
        ]);
    }
}
