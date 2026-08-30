{{--
    One public layout, two ways in.

    Livewire pages declare #[Layout('layouts.public')]; plain Blade views use
    <x-layouts.public>, which resolves to this file. They used to be two copies
    of the same markup, and they had already drifted - the Consultation link
    existed in one and not the other, so half the site had no way to reach it.

    This is the component entry point and nothing else. The layout itself lives
    in one place.
--}}
@include('layouts.public', [
    'slot'        => $slot,
    'title'       => $title ?? null,
    'description' => $description ?? null,
])
