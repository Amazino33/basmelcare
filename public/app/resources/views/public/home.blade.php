<x-layouts.public title="BasmelCare Pharmacy — Your Health, Our Priority">

    <!-- Hero Section -->
    <section class="bg-gradient-to-br from-primary/10 via-base-100 to-accent/5">
        <div class="max-w-7xl mx-auto px-4 py-12 md:py-20">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 items-center">
                <div>
                    <span class="badge badge-primary badge-outline mb-3 text-xs">Trusted Healthcare Partner</span>
                    <h1 class="text-3xl md:text-5xl font-bold leading-tight mb-4">
                        Your Health,<br>
                        <span class="text-primary">Our Priority</span>
                    </h1>
                    <p class="text-base-content/60 text-sm md:text-base mb-6 max-w-md">
                        Quality medicines, expert pharmaceutical care, and wellness products delivered to your doorstep or ready for pickup.
                    </p>
                    <div class="flex flex-col sm:flex-row gap-3">
                        <a href="/shop" class="btn btn-primary">
                            <x-icon name="o-shopping-bag" class="w-5 h-5" /> Shop Now
                        </a>
                        <a href="#services" class="btn btn-ghost border border-base-300">
                            Our Services
                        </a>
                    </div>

                    <!-- Trust badges -->
                    <div class="flex items-center gap-4 mt-8">
                        <div class="flex items-center gap-1 text-xs text-base-content/60">
                            <x-icon name="o-shield-check" class="w-4 h-4 text-success" />
                            <span>Licensed Pharmacy</span>
                        </div>
                        <div class="flex items-center gap-1 text-xs text-base-content/60">
                            <x-icon name="o-truck" class="w-4 h-4 text-primary" />
                            <span>Fast Delivery</span>
                        </div>
                        <div class="flex items-center gap-1 text-xs text-base-content/60">
                            <x-icon name="o-check-badge" class="w-4 h-4 text-accent" />
                            <span>Genuine Products</span>
                        </div>
                    </div>
                </div>

                <!-- Hero image placeholder -->
                <div class="hidden md:flex justify-center">
                    <div class="w-80 h-80 bg-primary/10 rounded-full flex items-center justify-center">
                        <x-icon name="o-heart" class="w-32 h-32 text-primary/30" />
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Category Quick Links -->
    <section class="py-8 md:py-12 bg-base-100">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex overflow-x-auto gap-3 pb-2 scrollbar-hide">
                @php $categories = \App\Models\Category::withCount('products')->orderBy('name')->get(); @endphp
                @foreach($categories as $cat)
                    <a href="/shop?category={{ $cat->id }}" class="flex-shrink-0 flex flex-col items-center gap-1 p-3 rounded-xl bg-base-200 hover:bg-primary/10 transition-colors min-w-[80px]">
                        <x-icon name="o-cube" class="w-6 h-6 text-primary" />
                        <span class="text-xs font-medium text-center whitespace-nowrap">{{ $cat->name }}</span>
                        <span class="text-xs text-base-content/40">{{ $cat->products_count }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    </section>

    <!-- Featured Products -->
    <section class="py-8 md:py-12 bg-base-200/50">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h2 class="text-xl md:text-2xl font-bold">Popular Products</h2>
                    <p class="text-sm text-base-content/60">Top selling healthcare products</p>
                </div>
                <a href="/shop" class="btn btn-ghost btn-sm">
                    View All <x-icon name="o-arrow-right" class="w-4 h-4" />
                </a>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3 md:gap-4">
                @php
                    $featuredProducts = \App\Models\Product::with('category', 'batches')
                        ->whereHas('batches', fn($q) => $q->where('quantity', '>', 0))
                        ->limit(8)->get();
                @endphp
                @foreach($featuredProducts as $product)
                    @php $stock = $product->batches->sum('quantity'); @endphp
                    <a href="/shop/{{ $product->id }}" class="card bg-base-100 shadow-sm hover:shadow-md transition-shadow">
                        <figure class="bg-base-200 h-32 md:h-40 flex items-center justify-center">
                            @if($product->image)
                                <img src="{{ asset('storage/' . $product->image) }}" alt="{{ $product->name }}" class="w-full h-full object-cover" />
                            @else
                                <x-icon name="o-cube" class="w-12 h-12 text-base-content/20" />
                            @endif
                        </figure>
                        <div class="card-body p-3">
                            <span class="text-xs text-primary">{{ $product->category?->name }}</span>
                            <h3 class="text-sm font-semibold leading-tight line-clamp-2">{{ $product->name }}</h3>
                            <div class="flex items-center justify-between mt-1">
                                <span class="text-primary font-bold">₦{{ number_format($product->selling_price, 0) }}</span>
                                @if($stock > 0)
                                    <span class="badge badge-success badge-xs">In Stock</span>
                                @else
                                    <span class="badge badge-error badge-xs">Out</span>
                                @endif
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    </section>

    <!-- Services -->
    <section id="services" class="py-10 md:py-16 bg-base-100">
        <div class="max-w-7xl mx-auto px-4">
            <div class="text-center mb-8">
                <h2 class="text-xl md:text-2xl font-bold">Our Services</h2>
                <p class="text-sm text-base-content/60 mt-1">More than just a pharmacy</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <div class="card bg-base-200/50 p-5">
                    <div class="w-10 h-10 bg-primary/10 rounded-lg flex items-center justify-center mb-3">
                        <x-icon name="o-beaker" class="w-5 h-5 text-primary" />
                    </div>
                    <h3 class="font-semibold mb-1">Prescription Dispensing</h3>
                    <p class="text-sm text-base-content/60">Accurate dispensing of prescribed medications by licensed pharmacists.</p>
                </div>
                <div class="card bg-base-200/50 p-5">
                    <div class="w-10 h-10 bg-accent/10 rounded-lg flex items-center justify-center mb-3">
                        <x-icon name="o-chat-bubble-left-right" class="w-5 h-5 text-accent" />
                    </div>
                    <h3 class="font-semibold mb-1">Pharmaceutical Consultation</h3>
                    <p class="text-sm text-base-content/60">Expert advice on medication usage, interactions, and side effects.</p>
                </div>
                <div class="card bg-base-200/50 p-5">
                    <div class="w-10 h-10 bg-success/10 rounded-lg flex items-center justify-center mb-3">
                        <x-icon name="o-heart" class="w-5 h-5 text-success" />
                    </div>
                    <h3 class="font-semibold mb-1">Health Monitoring</h3>
                    <p class="text-sm text-base-content/60">Blood pressure checks, blood sugar tests, and health screenings.</p>
                </div>
                <div class="card bg-base-200/50 p-5">
                    <div class="w-10 h-10 bg-info/10 rounded-lg flex items-center justify-center mb-3">
                        <x-icon name="o-truck" class="w-5 h-5 text-info" />
                    </div>
                    <h3 class="font-semibold mb-1">Home Delivery</h3>
                    <p class="text-sm text-base-content/60">Get your medications delivered right to your doorstep.</p>
                </div>
                <div class="card bg-base-200/50 p-5">
                    <div class="w-10 h-10 bg-warning/10 rounded-lg flex items-center justify-center mb-3">
                        <x-icon name="o-calendar" class="w-5 h-5 text-warning" />
                    </div>
                    <h3 class="font-semibold mb-1">Appointments</h3>
                    <p class="text-sm text-base-content/60">Book consultations and health check appointments online.</p>
                </div>
                <div class="card bg-base-200/50 p-5">
                    <div class="w-10 h-10 bg-error/10 rounded-lg flex items-center justify-center mb-3">
                        <x-icon name="o-shield-check" class="w-5 h-5 text-error" />
                    </div>
                    <h3 class="font-semibold mb-1">Genuine Products</h3>
                    <p class="text-sm text-base-content/60">Only authentic, NAFDAC-approved medications and health products.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Why Choose Us -->
    <section class="py-10 md:py-16 bg-primary text-primary-content">
        <div class="max-w-7xl mx-auto px-4">
            <div class="text-center mb-8">
                <h2 class="text-xl md:text-2xl font-bold">Why Choose BasmelCare?</h2>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-center">
                <div>
                    <div class="text-3xl md:text-4xl font-bold">24/7</div>
                    <div class="text-sm opacity-80 mt-1">Available Support</div>
                </div>
                <div>
                    <div class="text-3xl md:text-4xl font-bold">1000+</div>
                    <div class="text-sm opacity-80 mt-1">Products Available</div>
                </div>
                <div>
                    <div class="text-3xl md:text-4xl font-bold">Fast</div>
                    <div class="text-sm opacity-80 mt-1">Same Day Delivery</div>
                </div>
                <div>
                    <div class="text-3xl md:text-4xl font-bold">100%</div>
                    <div class="text-sm opacity-80 mt-1">Genuine Products</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact / CTA -->
    <section id="contact" class="py-10 md:py-16 bg-base-100">
        <div class="max-w-7xl mx-auto px-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div>
                    <h2 class="text-xl md:text-2xl font-bold mb-2">Get In Touch</h2>
                    <p class="text-sm text-base-content/60 mb-6">Have a question or need help? We're here for you.</p>

                    @php
                        $phone = \App\Models\AppSetting::get('pharmacy_phone', '');
                        $email = \App\Models\AppSetting::get('pharmacy_email', '');
                        $address = \App\Models\AppSetting::get('pharmacy_address', '');
                    @endphp

                    <div class="space-y-4">
                        @if($phone)
                            <div class="flex items-start gap-3">
                                <div class="w-10 h-10 bg-primary/10 rounded-lg flex items-center justify-center shrink-0">
                                    <x-icon name="o-phone" class="w-5 h-5 text-primary" />
                                </div>
                                <div>
                                    <div class="font-semibold text-sm">Phone</div>
                                    <a href="tel:{{ $phone }}" class="text-sm text-base-content/60 hover:text-primary">{{ $phone }}</a>
                                </div>
                            </div>
                        @endif
                        @if($email)
                            <div class="flex items-start gap-3">
                                <div class="w-10 h-10 bg-primary/10 rounded-lg flex items-center justify-center shrink-0">
                                    <x-icon name="o-envelope" class="w-5 h-5 text-primary" />
                                </div>
                                <div>
                                    <div class="font-semibold text-sm">Email</div>
                                    <a href="mailto:{{ $email }}" class="text-sm text-base-content/60 hover:text-primary">{{ $email }}</a>
                                </div>
                            </div>
                        @endif
                        @if($address)
                            <div class="flex items-start gap-3">
                                <div class="w-10 h-10 bg-primary/10 rounded-lg flex items-center justify-center shrink-0">
                                    <x-icon name="o-map-pin" class="w-5 h-5 text-primary" />
                                </div>
                                <div>
                                    <div class="font-semibold text-sm">Address</div>
                                    <p class="text-sm text-base-content/60">{{ $address }}</p>
                                </div>
                            </div>
                        @endif
                    </div>

                    @if($phone)
                        <a href="https://wa.me/{{ preg_replace('/\D/', '', $phone) }}" class="btn btn-success btn-sm mt-6" target="_blank">
                            <x-icon name="o-chat-bubble-left-right" class="w-4 h-4" /> Chat on WhatsApp
                        </a>
                    @endif
                </div>

                <!-- CTA Card -->
                <div class="card bg-gradient-to-br from-primary to-primary/80 text-primary-content p-6 md:p-8 flex flex-col justify-center">
                    <h3 class="text-xl md:text-2xl font-bold mb-2">Ready to Order?</h3>
                    <p class="opacity-80 text-sm mb-6">Browse our catalogue and get your medications delivered or ready for pickup.</p>
                    <div class="flex flex-col sm:flex-row gap-3">
                        <a href="/shop" class="btn btn-accent">
                            <x-icon name="o-shopping-bag" class="w-5 h-5" /> Shop Now
                        </a>
                        <a href="/customer/login" class="btn btn-ghost border border-primary-content/30">
                            Create Account
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

</x-layouts.public>
