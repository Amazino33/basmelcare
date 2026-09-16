<div>
    <x-header title="Messages" subtitle="Send one message to many customers, each on their own" size="text-xl" />

    @unless($canSend)
        <div class="alert alert-warning py-2 mb-4 text-sm gap-2">
            <x-icon name="o-exclamation-triangle" class="w-4 h-4 shrink-0" />
            <span>You can see what has been sent, but only an admin or branch manager may send.</span>
        </div>
    @endunless

    @if($sending)
        {{-- Sending in progress with Anti-Ban Auto-Pacer & Jitter --}}
        <div x-data="{
            autoPacing: @entangle('isAutoPacing'),
            countdown: 6,
            timer: null,
            sending: false,
            startPacer() {
                this.autoPacing = true;
                this.tick();
            },
            pausePacer() {
                this.autoPacing = false;
                if (this.timer) {
                    clearTimeout(this.timer);
                    this.timer = null;
                }
            },
            tick() {
                if (!this.autoPacing) return;
                this.countdown = 6;
                const interval = setInterval(() => {
                    if (!this.autoPacing) {
                        clearInterval(interval);
                        return;
                    }
                    this.countdown--;
                    if (this.countdown <= 0) {
                        clearInterval(interval);
                        this.sending = true;
                        $wire.sendBatch().then(() => {
                            this.sending = false;
                            if (this.autoPacing && {{ $progress['pending'] ?? 0 }} > 0) {
                                this.tick();
                            } else {
                                this.autoPacing = false;
                            }
                        }).catch(() => {
                            this.sending = false;
                            this.autoPacing = false;
                        });
                    }
                }, 1000);
            }
        }">
            <x-card title="Sending with Anti-Ban Protection" class="mb-4">
                <div class="p-3 bg-base-200/50 rounded-lg text-sm mb-3 border border-base-300 font-sans whitespace-pre-line">
                    {{ $sending->message }}
                </div>

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
                    <div class="alert alert-info py-2 mb-3 text-sm gap-2">
                        <x-icon name="o-information-circle" class="w-4 h-4 shrink-0" />
                        <span>
                            {{ $progress['withImage'] }} received the picture.
                            {{ $progress['sms'] }} got the words by SMS &mdash; a text message cannot carry an image.
                        </span>
                    </div>
                @endif

                {{-- Live Pacer Status Feedback --}}
                @if($progress['pending'] > 0)
                    <div class="p-3 bg-base-200/70 rounded-lg border border-base-300 mb-4 text-xs flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <template x-if="autoPacing && !sending">
                                <div class="flex items-center gap-2 text-primary font-medium">
                                    <span class="badge badge-primary badge-sm font-mono" x-text="'Next batch in ' + countdown + 's'"></span>
                                    <span>Cooling down session to prevent WhatsApp anti-spam flags...</span>
                                </div>
                            </template>
                            <template x-if="autoPacing && sending">
                                <div class="flex items-center gap-2 text-primary font-medium">
                                    <span class="loading loading-spinner loading-xs"></span>
                                    <span>Sending micro-batch of 5 with human-like delays (2&ndash;4s per recipient)...</span>
                                </div>
                            </template>
                            <template x-if="!autoPacing">
                                <div class="flex items-center gap-2 text-base-content/70">
                                    <x-icon name="o-shield-check" class="w-4 h-4 text-success" />
                                    <span>Anti-ban ready: Each message is sent individually with unique personalization.</span>
                                </div>
                            </template>
                        </div>
                    </div>
                @endif

                <div class="flex flex-wrap gap-2 items-center">
                    @if($progress['pending'] > 0 && $canSend)
                        <template x-if="!autoPacing">
                            <div class="flex gap-2">
                                <button type="button" @click="startPacer" class="btn btn-primary gap-2">
                                    <x-icon name="o-play" class="w-4 h-4" /> Start Auto-Pacer
                                </button>
                                <x-button label="Send next {{ min($progress['pending'], 5) }}"
                                          icon="o-paper-airplane"
                                          wire:click="sendBatch" spinner="sendBatch"
                                          class="btn-outline btn-primary" />
                            </div>
                        </template>
                        <template x-if="autoPacing">
                            <button type="button" @click="pausePacer" class="btn btn-warning gap-2">
                                <x-icon name="o-pause" class="w-4 h-4" /> Pause Auto-Pacer
                            </button>
                        </template>
                    @endif

                    <x-button label="{{ $progress['pending'] > 0 ? 'Stop for now' : 'Done' }}"
                              wire:click="done" class="btn-ghost" />
                </div>

                @if($progress['pending'] > 0)
                    <p class="text-xs text-base-content/50 mt-2">
                        Stopping or pausing is 100% safe &mdash; completed recipients are saved, and you can resume anytime without duplicates.
                    </p>
                @endif
            </x-card>
        </div>
    @else
        <x-card title="New message" class="mb-4">
            <x-form wire:submit="create">
                <x-textarea label="Message" wire:model.live.debounce.300ms="message" rows="4"
                            placeholder="What do you want to tell customers? Use {name} or {first_name} for personalization."
                            hint="Write so it makes sense without the picture &mdash; not everyone will see one." />

                {{-- Personalization Chips --}}
                <div class="flex flex-wrap items-center gap-2 text-xs -mt-2 mb-3">
                    <span class="text-base-content/60 font-semibold">Available tags:</span>
                    <span class="badge badge-outline cursor-pointer hover:badge-primary" title="Customer's full name">{name}</span>
                    <span class="badge badge-outline cursor-pointer hover:badge-primary" title="Customer's first name">{first_name}</span>
                    <span class="badge badge-outline cursor-pointer hover:badge-primary" title="Basmelcare">{pharmacy}</span>
                    <span class="text-base-content/50 italic ml-1">(Every message becomes uniquely personalized to prevent spam detection)</span>
                </div>

                {{-- Live Preview Box --}}
                @if($this->messagePreview)
                    <div class="bg-base-200/60 rounded-lg p-3 text-xs border border-base-300 mb-3">
                        <span class="font-semibold text-base-content/70">Live preview for customer (e.g. Adewale Musa):</span>
                        <div class="mt-1.5 p-2.5 rounded-lg bg-base-100 border border-base-300 font-sans whitespace-pre-line text-sm text-base-content">
                            {{ $this->messagePreview }}
                        </div>
                    </div>
                @endif

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

                {{-- Opt-Out Toggle --}}
                <div class="mt-3">
                    <x-checkbox label="Include opt-out note ('Reply STOP to opt out')"
                                wire:model.live="includeOptOut"
                                hint="Strongly recommended: Drastically reduces 'Report Spam' actions, protecting your WhatsApp business number from bans." />
                </div>

                @if($photo)
                    <div class="mt-2">
                        <img src="{{ $photo->temporaryUrl() }}" class="w-24 h-24 object-cover rounded border border-base-200" alt="Preview" />
                    </div>
                @endif

                <div class="rounded-lg border border-base-300 bg-base-200/40 p-3 text-sm mt-3">
                    This will reach <strong>{{ number_format($this->recipientCount()) }}</strong>
                    {{ Str::plural('customer', $this->recipientCount()) }}, each as a separate, individually personalized message.
                    Nobody is put in a group, so no customer sees another's number.
                </div>

                @if($this->recipientCount() > 50 && $audience === 'all')
                    <div class="alert alert-warning py-2 text-xs gap-2 mt-2">
                        <x-icon name="o-shield-exclamation" class="w-4 h-4 shrink-0" />
                        <span>
                            <strong>Anti-Ban Tip:</strong> Inactive contacts are more likely to report spam. For promotional messages on unofficial gateways, selecting <strong>"Bought in the last 90 days"</strong> is recommended.
                        </span>
                    </div>
                @endif

                <x-slot:actions>
                    @if($canSend)
                        <x-button label="Prepare Broadcast" type="submit" class="btn-primary" spinner="create" icon="o-paper-airplane" />
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
