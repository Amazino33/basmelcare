<div>
    <x-header title="Product Images" subtitle="Upload and manage product photos" size="text-xl">
        <x-slot:middle class="!justify-end">
            <x-input icon="o-magnifying-glass" placeholder="Search products..." wire:model.live.debounce="search" clearable />
        </x-slot:middle>
    </x-header>

    <div class="flex flex-wrap gap-2 mb-4">
        @foreach([
            'missing' => ['Needs Image', $missingCount],
            'has'     => ['Has Image', $hasCount],
            'all'     => ['All', $allCount],
        ] as $key => [$label, $count])
            <button type="button" wire:click="$set('filter', '{{ $key }}')"
                class="btn btn-sm {{ $filter === $key ? 'btn-primary' : 'btn-ghost bg-base-200' }}">
                {{ $label }}
                <span class="badge badge-sm {{ $filter === $key ? 'badge-neutral' : 'badge-ghost' }}">{{ $count }}</span>
            </button>
        @endforeach
    </div>

    <div class="space-y-2">
        @forelse($products as $product)
            <x-card class="!p-3">
                <div class="flex items-center gap-3">
                    {{-- Thumbnail --}}
                    <div class="shrink-0 w-12 h-12 rounded-lg overflow-hidden bg-base-200 flex items-center justify-center border border-base-300">
                        @if($product->image)
                            <img src="{{ asset('storage/' . $product->image) }}"
                                 class="w-full h-full object-cover" alt="{{ $product->name }}">
                        @else
                            <x-icon name="o-photo" class="w-6 h-6 text-base-content/30" />
                        @endif
                    </div>

                    {{-- Name + Google Images link --}}
                    <div class="flex-1 min-w-0">
                        <a href="https://www.google.com/search?q={{ urlencode($product->name) }}&tbm=isch"
                           target="_blank" rel="noopener noreferrer"
                           class="font-medium text-sm text-primary hover:underline block truncate">
                            {{ $product->name }}
                        </a>
                        <span class="text-xs {{ $product->image ? 'text-success' : 'text-base-content/40' }}">
                            {{ $product->image ? 'Has image' : 'No image yet' }}
                        </span>
                    </div>

                    {{-- Upload/Change button --}}
                    @if($uploadingId !== $product->id)
                        <x-button
                            icon="{{ $product->image ? 'o-arrow-path' : 'o-arrow-up-tray' }}"
                            label="{{ $product->image ? 'Change' : 'Upload' }}"
                            wire:click="startUpload({{ $product->id }})"
                            class="btn-sm {{ $product->image ? 'btn-ghost' : 'btn-outline btn-primary' }} shrink-0" />
                    @endif
                </div>

                {{-- Inline upload form --}}
                @if($uploadingId === $product->id)
                    <div class="mt-3 pt-3 border-t border-base-200">
                        <div class="flex flex-col sm:flex-row gap-2 items-start sm:items-center">
                            <input type="file" wire:model="photo" accept="image/*"
                                   class="file-input file-input-bordered file-input-sm w-full sm:flex-1" />
                            <div class="flex gap-2 shrink-0">
                                <x-button label="Save" wire:click="saveImage"
                                          class="btn-primary btn-sm" icon="o-check"
                                          wire:loading.attr="disabled" wire:target="photo,saveImage" />
                                <x-button label="Cancel" wire:click="cancelUpload" class="btn-ghost btn-sm" />
                            </div>
                        </div>

                        @error('photo')
                            <p class="text-error text-xs mt-1">{{ $message }}</p>
                        @enderror

                        @if($photo)
                            <div class="mt-2">
                                <img src="{{ $photo->temporaryUrl() }}"
                                     class="w-20 h-20 object-cover rounded-lg border border-base-300" alt="Preview">
                            </div>
                        @endif
                    </div>
                @endif
            </x-card>
        @empty
            <x-card>
                <div class="text-center py-10 text-base-content/50">
                    @if($search)
                        <x-icon name="o-magnifying-glass" class="w-12 h-12 mx-auto mb-3 opacity-30" />
                        <p class="font-semibold">Nothing matches "{{ $search }}"</p>
                        <p class="text-sm mt-1">Try a different search term, or switch filter.</p>
                    @elseif($filter === 'missing')
                        <x-icon name="o-check-circle" class="w-12 h-12 mx-auto mb-3 text-success opacity-40" />
                        <p class="font-semibold">Every product has an image</p>
                        <p class="text-sm mt-1">Nothing left to upload — nice work.</p>
                    @elseif($filter === 'has')
                        <x-icon name="o-photo" class="w-12 h-12 mx-auto mb-3 opacity-30" />
                        <p class="font-semibold">No images uploaded yet</p>
                        <p class="text-sm mt-1">Switch to "Needs Image" to get started.</p>
                    @else
                        <x-icon name="o-cube" class="w-12 h-12 mx-auto mb-3 opacity-30" />
                        <p class="font-semibold">No products in the catalogue</p>
                    @endif
                </div>
            </x-card>
        @endforelse
    </div>

    @if($products->hasPages())
        <div class="mt-4">
            {{ $products->links() }}
        </div>
    @endif
</div>
