<div>
    <x-header title="Images" subtitle="The pictures on the shop, and the products they sell" size="text-xl">
        <x-slot:middle class="!justify-end">
            @if($tab === 'products')
                <x-input icon="o-magnifying-glass" placeholder="Search products..." wire:model.live.debounce="search" clearable />
            @endif
        </x-slot:middle>
    </x-header>

    <div role="tablist" class="tabs tabs-border mb-4">
        @foreach(['products' => 'Products', 'storefront' => 'Shop Front', 'categories' => 'Categories'] as $key => $label)
            <button role="tab" wire:click="switchTab('{{ $key }}')"
                @class(['tab', 'tab-active' => $tab === $key])>{{ $label }}</button>
        @endforeach
    </div>

    {{-- ── the shop front ─────────────────────────────────────────── --}}
    @if($tab === 'storefront')
        <x-card title="Shop front picture" subtitle="The wide banner behind the welcome message" class="mb-4">
            {{-- Shown at the size it will actually appear, so what is chosen
                 here is what customers get. A picture that looks right in a
                 file browser and wrong on the page is the usual way this goes
                 astray. --}}
            <div class="relative rounded-xl overflow-hidden border border-base-300 aspect-[21/9] bg-base-200">
                {{-- isPreviewable first: temporaryUrl() throws on anything that
                     is not an image, so picking a PDF by mistake would blow the
                     page up before the validation message could say so. --}}
                @if($heroPhoto && $heroPhoto->isPreviewable())
                    <img src="{{ $heroPhoto->temporaryUrl() }}" class="w-full h-full object-cover" alt="Preview" />
                    <span class="absolute top-2 left-2 badge badge-warning badge-sm">Not saved yet</span>
                @elseif($heroImage)
                    <img src="{{ $this->siteImageUrl($heroImage) }}" class="w-full h-full object-cover" alt="Current shop front picture" />
                @else
                    <div class="w-full h-full flex flex-col items-center justify-center text-base-content/40 gap-2">
                        <x-icon name="o-photo" class="w-10 h-10" />
                        <span class="text-sm">No picture &mdash; the shop front uses its plain colour</span>
                    </div>
                @endif

                {{-- The same dark wash the site puts over it, so the preview
                     shows how the white writing will actually sit. --}}
                @if($heroPhoto || $heroImage)
                    <div class="absolute inset-0 pointer-events-none" style="background: oklch(38% 0.062 178 / 0.72)"></div>
                    <div class="absolute inset-0 flex items-center pointer-events-none">
                        <div class="px-6">
                            <div class="text-white/80 text-[10px] uppercase tracking-widest">Trusted in Uyo</div>
                            <div class="text-white font-extrabold text-lg sm:text-2xl">Your Health, Our Priority.</div>
                        </div>
                    </div>
                @endif
            </div>

            <div class="rounded-lg border border-base-300 bg-base-200/40 p-3 text-sm mt-3">
                <p class="font-semibold mb-1">For it to look right</p>
                <ul class="list-disc list-inside space-y-1 text-base-content/70">
                    <li>A wide picture, roughly twice as wide as it is tall. About 1600&times;700 is ideal.</li>
                    <li>Keep faces and anything important away from the left, where the writing sits.</li>
                    <li>Up to 4MB. Anything larger slows the page down on a phone.</li>
                </ul>
                <p class="text-base-content/60 mt-2">
                    Whatever the shape, it is cropped to fill the banner rather than squashed, so it
                    will not look stretched &mdash; but a tall picture will lose its top and bottom.
                </p>
            </div>

            <x-form wire:submit="saveHero" class="mt-3">
                <input type="file" wire:model="heroPhoto" accept="image/*"
                       class="file-input file-input-bordered file-input-sm w-full" />
                @error('heroPhoto') <p class="text-error text-xs mt-1">{{ $message }}</p> @enderror

                <x-slot:actions>
                    @if($heroImage)
                        <x-button label="Remove picture" wire:click="removeHero" wire:confirm="Remove the shop front picture?"
                                  class="btn-ghost btn-sm text-error" />
                    @endif
                    <x-button label="{{ $heroImage ? 'Replace picture' : 'Use this picture' }}" type="submit"
                              class="btn-primary btn-sm" spinner="saveHero" :disabled="! $heroPhoto" />
                </x-slot:actions>
            </x-form>
        </x-card>

    {{-- ── category tiles ─────────────────────────────────────────── --}}
    @elseif($tab === 'categories')
        <div class="rounded-lg border border-base-300 bg-base-200/40 p-3 text-sm mb-4">
            A category with no picture shows a drawn symbol instead, so the shop front is
            finished either way. Square pictures work best &mdash; about 400&times;400.
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            @foreach($categories as $category)
                <x-card class="!p-3">
                    <div class="flex items-center gap-3">
                        <div class="w-16 h-16 rounded-lg overflow-hidden bg-base-200 shrink-0 flex items-center justify-center">
                            @if($category->image)
                                <img src="{{ $this->siteImageUrl($category->image) }}" class="w-full h-full object-cover" alt="{{ $category->name }}" />
                            @else
                                <x-icon name="o-squares-2x2" class="w-6 h-6 text-base-content/30" />
                            @endif
                        </div>

                        <div class="flex-1 min-w-0">
                            <div class="font-semibold truncate">{{ $category->name }}</div>
                            <div class="text-xs text-base-content/60">
                                {{ $category->products_count }} {{ Str::plural('product', $category->products_count) }}
                                @unless($category->image) &middot; showing a symbol @endunless
                            </div>

                            @if($uploadingCategoryId === $category->id)
                                <div class="mt-2">
                                    <input type="file" wire:model="categoryPhoto" accept="image/*"
                                           class="file-input file-input-bordered file-input-xs w-full" />
                                    @error('categoryPhoto') <p class="text-error text-xs mt-1">{{ $message }}</p> @enderror
                                    <div class="flex gap-1 mt-2">
                                        <x-button label="Save" wire:click="saveCategoryImage" class="btn-primary btn-xs"
                                                  spinner="saveCategoryImage" :disabled="! $categoryPhoto" />
                                        <x-button label="Cancel" wire:click="cancelCategoryUpload" class="btn-ghost btn-xs" />
                                    </div>
                                </div>
                            @else
                                <div class="flex gap-1 mt-1">
                                    <x-button label="{{ $category->image ? 'Replace' : 'Add picture' }}"
                                              wire:click="startCategoryUpload({{ $category->id }})" class="btn-ghost btn-xs" />
                                    @if($category->image)
                                        <x-button label="Remove" wire:click="removeCategoryImage({{ $category->id }})"
                                                  wire:confirm="Remove this picture?" class="btn-ghost btn-xs text-error" />
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>
                </x-card>
            @endforeach
        </div>

    {{-- ── products, as before ────────────────────────────────────── --}}
    @else
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
                            <img src="{{ $product->imageUrl('thumb') }}"
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
    @endif
</div>
