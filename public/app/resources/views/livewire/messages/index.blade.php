<div>
    <x-header title="Messages" subtitle="Send one message to many customers, each on their own" size="text-xl" />

    @unless($canSend)
        <div class="alert alert-warning py-2 mb-4 text-sm gap-2">
            <x-icon name="o-exclamation-triangle" class="w-4 h-4 shrink-0" />
            <span>You can see what has been sent, but only an admin or branch manager may send.</span>
        </div>
    @endunless

    @if($sending)
        {{-- Sending in progress. Batched on purpose: the whole list in one
             request times out, and hammering the gateway gets a number banned. --}}
        <x-card title="Sending" class="mb-4">
            <div class="text-sm text-base-content/70 mb-3">{{ $sending->message }}</div>

            @if($sending->imageUrl())
                <img src="{{ $sending->imageUrl() }}" class="w-24 h-24 object-cover rounded border border-base-200 mb-3" alt="" />
            @endif

            <div class="flex flex-wrap gap-5 text-sm mb-4">
                <div>
                    <div class="text-xs text-base-content/50">Sent</div>
                    <div class="text-lg font-bold tabular-nums">
                        {{ $progress['total'] - $progress['pending'] }} / {{ $progress['total'] }}
                    </div>
                </div>
                <div>
                    <div class="text-xs text-base-content/50">By WhatsApp</div>
                    <div class="text-lg font-bold tabular-nums text-success">{{ $progress['whatsapp'] }}</div>
                </div>
                <div>
                    <div class="text-xs text-base-content/50">By SMS</div>
                    <div class="text-lg font-bold tabular-nums">{{ $progress['sms'] }}</div>
                </div>
                @if($progress['failed'] > 0)
                    <div>
                        <div class="text-xs text-base-content/50">Failed</div>
                        <div class="text-lg font-bold tabular-nums text-error">{{ $progress['failed'] }}</div>
                    </div>
                @endif
            </div>

            @if($sending->image_path && $progress['sms'] > 0)
                {{-- Say it plainly rather than letting them assume everyone saw it --}}
                <div class="alert alert-info py-2 mb-3 text-sm gap-2">
                    <x-icon name="o-information-circle" class="w-4 h-4 shrink-0" />
                    <span>
                        {{ $progress['withImage'] }} received the picture.
                        {{ $progress['sms'] }} got the words by SMS &mdash; a text message cannot carry an image.
                    </span>
                </div>
            @endif

            <div class="flex gap-2">
                @if($progress['pending'] > 0 && $canSend)
                    <x-button label="Send next {{ min($progress['pending'], 20) }}"
                              icon="o-paper-airplane"
                              wire:click="sendBatch" spinner="sendBatch"
                              class="btn-primary" />
                @endif
                <x-button label="{{ $progress['pending'] > 0 ? 'Stop for now' : 'Done' }}"
                          wire:click="done" class="btn-ghost" />
            </div>

            @if($progress['pending'] > 0)
                <p class="text-xs text-base-content/50 mt-2">
                    Stopping is safe &mdash; nobody is messaged twice, and the rest wait until you come back.
                </p>
            @endif
        </x-card>
    @else
        <x-card title="New message" class="mb-4">
            <x-form wire:submit="create">
                <x-textarea label="Message" wire:model="message" rows="4"
                            placeholder="What do you want to tell customers?"
                            hint="Write so it makes sense without the picture &mdash; not everyone will see one." />

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <x-select label="Send to" wire:model.live="audience"
                              :options="collect(\App\Models\Broadcast::AUDIENCES)->map(fn($l, $v) => ['id' => $v, 'name' => $l])->values()"
                              option-value="id" option-label="name" />

                    <div>
                        <label class="label"><span class="label-text font-semibold">Picture (optional)</span></label>
                        <input type="file" wire:model="photo" accept="image/*"
                               class="file-input file-input-bordered file-input-sm w-full" />
                        @error('photo') <p class="text-error text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                @if($photo)
                    <img src="{{ $photo->temporaryUrl() }}" class="w-24 h-24 object-cover rounded border border-base-200" alt="Preview" />
                @endif

                <div class="rounded-lg border border-base-300 bg-base-200/40 p-3 text-sm">
                    This will reach <strong>{{ number_format($this->recipientCount()) }}</strong>
                    {{ Str::plural('customer', $this->recipientCount()) }}, each as a separate message.
                    Nobody is put in a group, so no customer sees another's number.
                </div>

                <x-slot:actions>
                    @if($canSend)
                        <x-button label="Prepare" type="submit" class="btn-primary" spinner="create" />
                    @endif
                </x-slot:actions>
            </x-form>
        </x-card>
    @endif

    @if($history->isNotEmpty())
        <x-card title="Recently sent">
            @foreach($history as $past)
                <div class="flex items-start gap-3 py-2 border-b border-base-200 last:border-0">
                    <div class="flex-1 min-w-0">
                        <div class="text-sm truncate">{{ Str::limit($past->message, 90) }}</div>
                        <div class="text-xs text-base-content/50">
                            {{ $past->audienceLabel() }}
                            &middot; {{ $past->recipients()->count() }} {{ Str::plural('recipient', $past->recipients()->count()) }}
                            &middot; {{ $past->sender->name ?? 'someone' }}
                            &middot; {{ $past->created_at->diffForHumans() }}
                            @if($past->image_path)
                                &middot; with a picture
                            @endif
                        </div>
                    </div>

                    @if(! $past->isFinished())
                        <span class="badge badge-warning badge-sm shrink-0">{{ $past->pendingCount() }} left</span>
                    @endif
                </div>
            @endforeach
        </x-card>
    @endif
</div>
