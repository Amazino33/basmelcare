<div class="py-8 md:py-12 px-4 sm:px-6 lg:px-8 bg-gradient-to-b from-base-200/50 via-base-100 to-base-200/30 min-h-[85vh] flex items-center justify-center">
    <div class="w-full max-w-6xl">
        <!-- Top Section Header -->
        <div class="text-center max-w-2xl mx-auto mb-8">
            <h1 class="text-2xl sm:text-3xl font-black text-base-content tracking-tight">Join BasmelCare Pharmacy</h1>
            <p class="text-xs sm:text-sm text-base-content/65 mt-1.5">Create your patient profile for express prescription refills, doorstep delivery, and direct pharmacist advisory</p>
        </div>

        <!-- Bento Grid Layout -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 items-stretch">
            <!-- Left Bento Column (4 cols on lg): Member Privileges & Live Advisory -->
            <div class="lg:col-span-4 flex flex-col gap-5 justify-between order-2 lg:order-1">
                <!-- Card 1: Exclusive Patient Privileges -->
                <div class="bg-gradient-to-br from-[#000C83] via-[#091b61] to-[#005111] text-white p-6 rounded-3xl shadow-xl border border-white/10 relative overflow-hidden flex-1 flex flex-col justify-between">
                    <div class="absolute -top-16 -right-16 w-40 h-40 bg-blue-400/15 rounded-full blur-2xl pointer-events-none"></div>
                    <div class="absolute -bottom-16 -left-16 w-40 h-40 bg-emerald-400/20 rounded-full blur-2xl pointer-events-none"></div>

                    <div class="relative z-10">
                        <div class="flex items-center gap-2 mb-3">
                            <x-icon name="o-sparkles" class="w-4 h-4 text-emerald-300" />
                            <span class="text-[11px] font-bold uppercase tracking-wider text-emerald-300">Patient Privileges</span>
                        </div>

                        <h3 class="text-lg font-black text-white leading-snug">
                            Your complete digital pharmacy companion.
                        </h3>

                        <div class="mt-5 space-y-3.5">
                            <div class="flex items-start gap-3">
                                <span class="w-7 h-7 rounded-xl bg-white/15 border border-white/20 flex items-center justify-center shrink-0 mt-0.5 text-emerald-300">
                                    <x-icon name="o-document-text" class="w-3.5 h-3.5" />
                                </span>
                                <div>
                                    <h5 class="text-xs font-bold text-white">Digital Prescription Vault</h5>
                                    <p class="text-[11px] text-white/70">Upload and request repeat refills with 1 click.</p>
                                </div>
                            </div>

                            <div class="flex items-start gap-3">
                                <span class="w-7 h-7 rounded-xl bg-white/15 border border-white/20 flex items-center justify-center shrink-0 mt-0.5 text-emerald-300">
                                    <x-icon name="o-truck" class="w-3.5 h-3.5" />
                                </span>
                                <div>
                                    <h5 class="text-xs font-bold text-white">WhatsApp Delivery Alerts</h5>
                                    <p class="text-[11px] text-white/70">Real-time status updates right on your phone.</p>
                                </div>
                            </div>

                            <div class="flex items-start gap-3">
                                <span class="w-7 h-7 rounded-xl bg-white/15 border border-white/20 flex items-center justify-center shrink-0 mt-0.5 text-emerald-300">
                                    <x-icon name="o-clock" class="w-3.5 h-3.5" />
                                </span>
                                <div>
                                    <h5 class="text-xs font-bold text-white">Direct Pharmacist Support</h5>
                                    <p class="text-[11px] text-white/70">Consult registered clinical staff whenever you need.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 pt-4 border-t border-white/15 relative z-10 flex items-center justify-between text-xs text-white/80">
                        <span>Free Membership</span>
                        <span class="text-emerald-300 font-bold">Takes &lt; 1 Min</span>
                    </div>
                </div>

                <!-- Card 2: Pharmacist Live Status -->
                <div class="bg-base-100 p-5 rounded-3xl border border-base-200/80 shadow-md flex items-center gap-3.5">
                    <div class="relative flex h-3 w-3 shrink-0">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-3 w-3 bg-emerald-500"></span>
                    </div>
                    <div>
                        <div class="text-xs font-bold text-base-content uppercase tracking-wider">Clinical Pharmacist Online</div>
                        <p class="text-xs text-base-content/65">Prescriptions are verified by licensed personnel before dispatch.</p>
                    </div>
                </div>
            </div>

            <!-- Center Bento Column (5 cols on lg): Registration Form -->
            <div class="lg:col-span-5 bg-base-100 p-6 sm:p-8 rounded-3xl border border-base-200/80 shadow-2xl flex flex-col justify-between order-1 lg:order-2">
                <div>
                    <!-- Form Top Navigation -->
                    <div class="flex items-center justify-between gap-3 mb-5 pb-4 border-b border-base-200/80">
                        <div class="flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full bg-emerald-600"></span>
                            <span class="text-xs font-bold uppercase tracking-wider text-base-content/80">Create Patient Profile</span>
                        </div>
                        <div class="text-xs text-base-content/65">
                            Member?
                            <a href="{{ route('customer.login') }}" wire:navigate class="text-primary font-bold hover:underline ml-1">
                                Sign In &rarr;
                            </a>
                        </div>
                    </div>

                    <form wire:submit="register" class="space-y-4" x-data="{ showPass: false, showConfirm: false }">
                        <!-- Full Name -->
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-base-content/70 mb-1.5">
                                Full Name
                            </label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-base-content/40">
                                    <x-icon name="o-user" class="w-5 h-5" />
                                </span>
                                <input wire:model="name"
                                       type="text"
                                       class="w-full h-11 pl-10 pr-4 rounded-xl border border-slate-300 bg-white text-slate-800 text-sm focus:border-primary focus:ring-2 focus:ring-primary/20 focus:outline-none transition-all placeholder:text-slate-400 shadow-xs @error('name') border-error ring-1 ring-error @enderror"
                                       placeholder="e.g. Adaeze Okon"
                                       required autofocus autocomplete="name" />
                            </div>
                            @error('name')
                                <span class="text-error text-xs mt-1 block font-medium flex items-center gap-1">
                                    <x-icon name="o-exclamation-circle" class="w-3.5 h-3.5 shrink-0" /> {{ $message }}
                                </span>
                            @enderror
                        </div>

                        <!-- Phone & Email Grid -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                            <!-- Phone -->
                            <div>
                                <div class="flex items-center justify-between mb-1.5">
                                    <label class="text-xs font-bold uppercase tracking-wider text-base-content/70">
                                        Phone Number
                                    </label>
                                    <span class="badge badge-success/15 text-success text-[10px] font-bold">WhatsApp</span>
                                </div>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-base-content/40">
                                        <x-icon name="o-phone" class="w-5 h-5" />
                                    </span>
                                    <input wire:model="phone"
                                           type="tel"
                                           class="w-full h-11 pl-10 pr-4 rounded-xl border border-slate-300 bg-white text-slate-800 text-sm focus:border-primary focus:ring-2 focus:ring-primary/20 focus:outline-none transition-all placeholder:text-slate-400 shadow-xs @error('phone') border-error ring-1 ring-error @enderror"
                                           placeholder="08012345678"
                                           required autocomplete="tel" />
                                </div>
                                @error('phone')
                                    <span class="text-error text-xs mt-1 block font-medium flex items-center gap-1">
                                        <x-icon name="o-exclamation-circle" class="w-3.5 h-3.5 shrink-0" /> {{ $message }}
                                    </span>
                                @enderror
                            </div>

                            <!-- Email -->
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider text-base-content/70 mb-1.5">
                                    Email Address
                                </label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-base-content/40">
                                        <x-icon name="o-envelope" class="w-5 h-5" />
                                    </span>
                                    <input wire:model="email"
                                           type="email"
                                           class="w-full h-11 pl-10 pr-4 rounded-xl border border-slate-300 bg-white text-slate-800 text-sm focus:border-primary focus:ring-2 focus:ring-primary/20 focus:outline-none transition-all placeholder:text-slate-400 shadow-xs @error('email') border-error ring-1 ring-error @enderror"
                                           placeholder="you@example.com"
                                           required autocomplete="email" />
                                </div>
                                @error('email')
                                    <span class="text-error text-xs mt-1 block font-medium flex items-center gap-1">
                                        <x-icon name="o-exclamation-circle" class="w-3.5 h-3.5 shrink-0" /> {{ $message }}
                                    </span>
                                @enderror
                            </div>
                        </div>

                        <!-- Password & Confirm Password Grid -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                            <!-- Password -->
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider text-base-content/70 mb-1.5">
                                    Password
                                </label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-base-content/40">
                                        <x-icon name="o-lock-closed" class="w-5 h-5" />
                                    </span>
                                    <input wire:model="password"
                                           :type="showPass ? 'text' : 'password'"
                                           class="w-full h-11 pl-10 pr-10 rounded-xl border border-slate-300 bg-white text-slate-800 text-sm focus:border-primary focus:ring-2 focus:ring-primary/20 focus:outline-none transition-all placeholder:text-slate-400 shadow-xs @error('password') border-error ring-1 ring-error @enderror"
                                           placeholder="Min 6 characters"
                                           required autocomplete="new-password" />
                                    <button type="button"
                                            @click="showPass = !showPass"
                                            class="absolute inset-y-0 right-0 flex items-center pr-3 text-base-content/40 hover:text-base-content"
                                            tabindex="-1">
                                        <x-icon x-show="!showPass" name="o-eye" class="w-4 h-4" />
                                        <x-icon x-show="showPass" name="o-eye-slash" class="w-4 h-4" style="display: none;" />
                                    </button>
                                </div>
                                @error('password')
                                    <span class="text-error text-xs mt-1 block font-medium flex items-center gap-1">
                                        <x-icon name="o-exclamation-circle" class="w-3.5 h-3.5 shrink-0" /> {{ $message }}
                                    </span>
                                @enderror
                            </div>

                            <!-- Confirm Password -->
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider text-base-content/70 mb-1.5">
                                    Confirm Password
                                </label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-base-content/40">
                                        <x-icon name="o-lock-closed" class="w-5 h-5" />
                                    </span>
                                    <input wire:model="password_confirmation"
                                           :type="showConfirm ? 'text' : 'password'"
                                           class="w-full h-11 pl-10 pr-10 rounded-xl border border-slate-300 bg-white text-slate-800 text-sm focus:border-primary focus:ring-2 focus:ring-primary/20 focus:outline-none transition-all placeholder:text-slate-400 shadow-xs @error('password_confirmation') border-error ring-1 ring-error @enderror"
                                           placeholder="Repeat password"
                                           required autocomplete="new-password" />
                                    <button type="button"
                                            @click="showConfirm = !showConfirm"
                                            class="absolute inset-y-0 right-0 flex items-center pr-3 text-base-content/40 hover:text-base-content"
                                            tabindex="-1">
                                        <x-icon x-show="!showConfirm" name="o-eye" class="w-4 h-4" />
                                        <x-icon x-show="showConfirm" name="o-eye-slash" class="w-4 h-4" style="display: none;" />
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <button type="submit"
                                wire:loading.attr="disabled"
                                class="btn btn-primary btn-block rounded-xl font-semibold shadow-md hover:shadow-lg transition-all mt-2">
                            <span wire:loading.remove class="flex items-center justify-center gap-2">
                                <span>Create Patient Account</span>
                                <x-icon name="o-arrow-right" class="w-4 h-4" />
                            </span>
                            <span wire:loading class="flex items-center justify-center gap-2">
                                <span class="loading loading-spinner loading-xs"></span>
                                <span>Creating profile...</span>
                            </span>
                        </button>
                    </form>
                </div>

                <!-- Bottom Form Footnote -->
                <div class="mt-6 pt-4 border-t border-base-200/80 flex items-center justify-between text-xs text-base-content/60">
                    <span class="inline-flex items-center gap-1">
                        <x-icon name="o-lock-closed" class="w-3.5 h-3.5 text-success" />
                        <span>SSL Encrypted</span>
                    </span>
                    <a href="{{ route('customer.login') }}" wire:navigate class="text-primary font-bold hover:underline">
                        Sign In Instead &rarr;
                    </a>
                </div>
            </div>

            <!-- Right Bento Column (3 cols on lg): Trust, Confidentiality & Ratings -->
            <div class="lg:col-span-3 flex flex-col gap-5 justify-between order-3">
                <!-- Card 3: 100% Genuine Medicine Guarantee -->
                <div class="bg-gradient-to-br from-[#005111] to-[#002e09] text-white p-5 rounded-3xl shadow-lg border border-white/10 flex-1 flex flex-col justify-between">
                    <div>
                        <div class="w-10 h-10 rounded-2xl bg-white/15 text-emerald-300 flex items-center justify-center mb-3">
                            <x-icon name="o-shield-check" class="w-5 h-5" />
                        </div>
                        <h4 class="text-xs font-bold uppercase tracking-wider text-emerald-300">Clinical Assurance</h4>
                        <h3 class="text-base font-black text-white mt-1">100% Genuine Medicines</h3>
                        <p class="text-xs text-white/75 mt-1.5 leading-relaxed">
                            NAFDAC registered items direct from certified distributors. Strict lot tracking and temperature safety.
                        </p>
                    </div>
                    <div class="mt-4 pt-3 border-t border-white/15 text-[11px] text-emerald-200/80 font-medium flex items-center gap-1.5">
                        <x-icon name="o-check-badge" class="w-3.5 h-3.5 text-emerald-300" />
                        <span>Registered Community Pharmacy</span>
                    </div>
                </div>

                <!-- Card 4: Strict Clinical Confidentiality -->
                <div class="bg-base-100 p-5 rounded-3xl border border-base-200/80 shadow-md">
                    <div class="flex items-center gap-2 mb-2">
                        <x-icon name="o-shield-exclamation" class="w-4 h-4 text-primary" />
                        <h5 class="text-xs font-bold uppercase tracking-wider text-base-content">Confidential Care</h5>
                    </div>
                    <p class="text-xs text-base-content/65">
                        Your medical details, prescription uploads, and order logs remain strictly private and HIPAA compliant.
                    </p>
                </div>

                <!-- Card 5: Community Rating -->
                <div class="bg-base-100 p-4 rounded-3xl border border-base-200/80 shadow-md text-center">
                    <div class="inline-flex items-center gap-1.5 justify-center">
                    <div class="flex items-center gap-0.5 text-amber-400">
                        <svg class="w-4 h-4 fill-current" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                        <svg class="w-4 h-4 fill-current" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                        <svg class="w-4 h-4 fill-current" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                        <svg class="w-4 h-4 fill-current" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                        <svg class="w-4 h-4 fill-current" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                    </div>
                    <span class="text-xs text-base-content/80 font-extrabold">4.9/5</span>
                </div>
                    <div class="text-xs font-bold text-base-content mt-1">Trusted Across Uyo</div>
                </div>
            </div>
        </div>
    </div>
</div>

