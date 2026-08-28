<?php

namespace App\Livewire\Messages;

use App\Models\Broadcast;
use App\Services\BroadcastSender;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Mary\Traits\Toast;

/**
 * Writing and sending a message to many customers.
 *
 * Each person receives their own message. Nobody is added to a group - for a
 * pharmacy that would publish who else buys medicine here, to everyone in it.
 *
 * Sending happens in batches, a button press at a time. Several hundred
 * messages in one request times out, and firing that many calls at an
 * unofficial WhatsApp gateway back to back is what gets a number banned. The
 * same number sends your receipts.
 */
class Index extends Component
{
    use Toast, WithFileUploads, WithPagination;

    public string $message = '';
    public string $audience = 'all';
    public $photo = null;

    /** The broadcast being sent, once it exists. */
    public ?int $sendingId = null;

    private function canSend(): bool
    {
        return (bool) array_intersect(auth()->user()->role ?? [], ['admin', 'branch_manager']);
    }

    public function updatedAudience(): void
    {
        // Just to refresh the count shown beside the picker.
    }

    public function recipientCount(): int
    {
        return app(BroadcastSender::class)->audience($this->audience)->count();
    }

    public function create(): void
    {
        if (! $this->canSend()) {
            $this->error('You cannot send messages to customers.');

            return;
        }

        $this->validate([
            'message'  => 'required|string|max:1000',
            'audience' => 'required|in:' . implode(',', array_keys(Broadcast::AUDIENCES)),
            'photo'    => 'nullable|image|max:2048',
        ], [], ['photo' => 'image']);

        // Stored where the gateway can fetch it: WhatsApp media is sent as a
        // URL, so the file has to be publicly reachable. Fine for a marketing
        // picture, and the reason patient documents never go near this.
        $path = $this->photo?->store('broadcasts', 'public_site');

        $broadcast = Broadcast::create([
            'message'    => trim($this->message),
            'image_path' => $path,
            'audience'   => $this->audience,
            'user_id'    => auth()->id(),
        ]);

        $count = app(BroadcastSender::class)->prepare($broadcast);

        if ($count === 0) {
            $broadcast->delete();
            $this->error('Nobody in that group has a phone number.');

            return;
        }

        $this->sendingId = $broadcast->id;
        $this->reset(['message', 'photo']);

        $this->success($count . ' ' . str('recipient')->plural($count) . ' ready. Send when you are.');
    }

    public function sendBatch(): void
    {
        if (! $this->canSend() || ! $this->sendingId) {
            return;
        }

        $broadcast = Broadcast::findOrFail($this->sendingId);
        $result    = app(BroadcastSender::class)->sendBatch($broadcast);

        if ($result['remaining'] > 0) {
            $this->success(
                $result['sent'] . ' sent. ' . $result['remaining'] . ' to go - press again to continue.'
            );

            return;
        }

        $this->success('Finished. Everyone has been messaged.');
    }

    public function done(): void
    {
        $this->sendingId = null;
    }

    public function render()
    {
        $sending = $this->sendingId ? Broadcast::find($this->sendingId) : null;

        return view('livewire.messages.index', [
            'sending'   => $sending,
            'progress'  => $sending ? [
                'total'     => $sending->recipients()->count(),
                'pending'   => $sending->pendingCount(),
                'whatsapp'  => $sending->recipients()->where('status', 'whatsapp')->count(),
                'sms'       => $sending->recipients()->where('status', 'sms')->count(),
                'failed'    => $sending->recipients()->where('status', 'failed')->count(),
                'withImage' => $sending->recipients()->where('image_sent', true)->count(),
            ] : null,
            'history'   => Broadcast::with('sender')->latest()->limit(10)->get(),
            'canSend'   => $this->canSend(),
        ]);
    }
}
