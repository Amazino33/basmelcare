<div class="py-8 md:py-12 px-4 sm:px-6 lg:px-8 bg-gradient-to-b from-base-200/50 via-base-100 to-base-200/30 min-h-[85vh] flex items-center justify-center">
    <div class="w-full max-w-6xl">
        <!-- Top Section Header -->
        <div class="text-center max-w-2xl mx-auto mb-8">
            <h1 class="text-2xl sm:text-3xl font-black text-base-content tracking-tight">Patient Portal Sign In</h1>
            <p class="text-xs sm:text-sm text-base-content/65 mt-1.5">Manage prescriptions, track medication delivery, and access certified pharmacy support</p>
        </div>

        <!-- Bento Grid Layout -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 items-stretch">
            <!-- Left Bento Column (4 cols on lg): Advisory & Logistics -->
            <div class="lg:col-span-4 flex flex-col gap-5 justify-between order-2 lg:order-1">
                <!-- Card 1: Live Pharmacist Desk -->
                <div class="bg-gradient-to-br from-[#000C83] via-[#091b61] to-[#005111] text-white p-6 rounded-3xl shadow-xl border border-white/10 relative overflow-hidden flex-1 flex flex-col justify-between">
                    <div class="absolute -top-16 -right-16 w-40 h-40 bg-blue-400/15 rounded-full blur-2xl pointer-events-none"></div>
                    <div class="absolute -bottom-16 -left-16 w-40 h-40 bg-emerald-400/20 rounded-full blur-2xl pointer-events-none"></div>

                    <div class="relative z-10">
                        <div class="flex items-center gap-2 mb-3">
                            <span class="relative flex h-2.5 w-2.5">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-400"></span>
                            </span>
                            <span class="text-[11px] font-bold uppercase tracking-wider text-emerald-300">Live Pharmacist Desk</span>
                        </div>

                        <h3 class="text-lg font-black text-white leading-snug">
                            Need guidance on medications or dosage?
                        </h3>
                        <p class="text-xs text-white/75 mt-2 leading-relaxed">
                            Our registered clinical pharmacists are on duty in Uyo to consult with you via WhatsApp or in person.
                        </p>
                    </div>

                    <div class="mt-6 pt-4 border-t border-white/15 relative z-10">
                        <a href="https://wa.me/2348123456789" target="_blank" rel="noopener noreferrer"
                           class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-white/15 hover:bg-white/25 border border-white/20 text-xs font-bold text-white transition-all">
                            <x-icon name="o-chat-bubble-left-right" class="w-4 h-4 text-emerald-300" />
                            <span>Chat with Pharmacist</span>
                        </a>
                    </div>
                </div>

                <!-- Card 2: Express Delivery Badge -->
                <div class="bg-base-100 p-5 rounded-3xl border border-base-200/80 shadow-md flex items-center gap-4">
                    <div class="w-12 h-12 rounded-2xl bg-primary/10 text-primary flex items-center justify-center shrink-0">
                        <x-icon name="o-truck" class="w-6 h-6" />
                    </div>
                    <div>
                        <h4 class="text-xs font-bold uppercase tracking-wider text-base-content">Fast Doorstep Delivery</h4>
                        <p class="text-xs text-base-content/65 mt-0.5">Rapid dispatch across Uyo municipality or free pickup at our pharmacy desk.</p>
                    </div>
                </div>
            </div>

            <!-- Center Bento Column (5 cols on lg): Main Auth Form -->
            <div class="lg:col-span-5 bg-base-100 p-6 sm:p-8 rounded-3xl border border-base-200/80 shadow-2xl flex flex-col justify-between order-1 lg:order-2">
                <div>
                    <!-- Form Top Navigation -->
                    <div class="flex items-center justify-between gap-3 mb-5 pb-4 border-b border-base-200/80">
                        <div class="flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full bg-primary"></span>
                            <span class="text-xs font-bold uppercase tracking-wider text-base-content/80">Patient Sign In</span>
                        </div>
                        <div class="text-xs text-base-content/65">
                            New here?
                            <a href="{{ route('customer.register') }}" wire:navigate class="text-primary font-bold hover:underline ml-1">
                                Register &rarr;
                            </a>
                        </div>
                    </div>

                    <!-- Method Tabs -->
                    <div class="grid grid-cols-2 p-1 bg-base-200/80 rounded-2xl mb-6 text-sm font-medium">
                        <button type="button"
                                wire:click="$set('usePassword', false)"
                                class="py-2.5 px-3 rounded-xl flex items-center justify-center gap-2 transition-all duration-150 {{ !$usePassword ? 'bg-base-100 text-primary font-bold shadow-sm' : 'text-base-content/60 hover:text-base-content' }}">
                            <x-icon name="o-chat-bubble-bottom-center-text" class="w-4 h-4 {{ !$usePassword ? 'text-primary' : 'text-base-content/50' }}" />
                            <span>WhatsApp OTP</span>
                        </button>
                        <button type="button"
                                wire:click="$set('usePassword', true)"
                                class="py-2.5 px-3 rounded-xl flex items-center justify-center gap-2 transition-all duration-150 {{ $usePassword ? 'bg-base-100 text-primary font-bold shadow-sm' : 'text-base-content/60 hover:text-base-content' }}">
                            <x-icon name="o-key" class="w-4 h-4 {{ $usePassword ? 'text-primary' : 'text-base-content/50' }}" />
                            <span>Password</span>
                        </button>
                    </div>

                    @if(!$usePassword)
                        {{-- OTP Login Flow --}}
                        @if(!$otpSent)
                            <form wire:submit="sendOtp" class="space-y-4">
                                <div>
                                    <label class="block text-xs font-bold uppercase tracking-wider text-base-content/70 mb-1.5">
                                        Phone Number or Email
                                    </label>
                                    <div class="relative">
                                        <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-base-content/40">
                                            <x-icon name="o-user" class="w-5 h-5" />
                                        </span>
                                        <input wire:model="identifier"
                                               type="text"
                                               class="w-full h-11 pl-10 pr-4 rounded-xl border border-slate-300 bg-white text-slate-800 text-sm focus:border-primary focus:ring-2 focus:ring-primary/20 focus:outline-none transition-all placeholder:text-slate-400 shadow-xs @error('identifier') border-error ring-1 ring-error @enderror"
                                               placeholder="e.g. 08012345678 or name@example.com"
                                               required autofocus autocomplete="username" />
                                    </div>
                                    <p class="text-xs text-base-content/60 mt-1.5 flex items-center gap-1.5">
                                        <x-icon name="o-shield-check" class="w-3.5 h-3.5 text-success shrink-0" />
                                        <span>We'll send a 6-digit one-time code to your WhatsApp.</span>
                                    </p>
                                    @error('identifier')
                                        <span class="text-error text-xs mt-1 block font-medium flex items-center gap-1">
                                            <x-icon name="o-exclamation-circle" class="w-3.5 h-3.5 shrink-0" /> {{ $message }}
                                        </span>
                                    @enderror
                                </div>

                                <button type="submit"
                                        wire:loading.attr="disabled"
                                        class="btn btn-primary btn-block rounded-xl font-semibold shadow-md hover:shadow-lg transition-all">
                                    <span wire:loading.remove class="flex items-center justify-center gap-2">
                                        <span>Send Login Code</span>
                                        <x-icon name="o-paper-airplane" class="w-4 h-4" />
                                    </span>
                                    <span wire:loading class="flex items-center justify-center gap-2">
                                        <span class="loading loading-spinner loading-xs"></span>
                                        <span>Sending code...</span>
                                    </span>
                                </button>
                            </form>
                        @else
                            {{-- OTP Verification Form --}}
                            <form wire:submit="verifyOtp" class="space-y-4">
                                <div class="p-3.5 rounded-xl bg-primary/5 border border-primary/20 text-center">
                                    <div class="w-8 h-8 rounded-full bg-primary/10 text-primary flex items-center justify-center mx-auto mb-1.5">
                                        <x-icon name="o-chat-bubble-left-ellipsis" class="w-4 h-4" />
                                    </div>
                                    <p class="text-xs text-base-content/70">6-digit code sent via WhatsApp to</p>
                                    <p class="font-bold text-sm text-base-content mt-0.5 break-all">{{ $identifier }}</p>
                                    <button type="button"
                                            wire:click="changeIdentifier"
                                            class="text-xs text-primary font-semibold hover:underline mt-1.5 inline-flex items-center gap-1">
                                        <x-icon name="o-pencil-square" class="w-3 h-3" /> Change number or email
                                    </button>
                                </div>

                                <div>
                                    <label class="block text-xs font-bold uppercase tracking-wider text-base-content/70 mb-1.5 text-center">
                                        Enter 6-Digit Code
                                    </label>
                                    <input wire:model="otp"
                                           type="text"
                                           inputmode="numeric"
                                           maxlength="6"
                                           placeholder="000000"
                                           class="w-full h-12 text-center text-2xl font-mono tracking-[0.4em] font-extrabold rounded-xl border border-slate-300 bg-white text-slate-800 focus:border-primary focus:ring-2 focus:ring-primary/20 focus:outline-none transition-all shadow-xs @error('otp') border-error ring-1 ring-error @enderror"
                                           required autofocus autocomplete="one-time-code" />
                                    @error('otp')
                                        <span class="text-error text-xs mt-1.5 text-center block font-medium flex items-center justify-center gap-1">
                                            <x-icon name="o-exclamation-circle" class="w-3.5 h-3.5 shrink-0" /> {{ $message }}
                                        </span>
                                    @enderror
                                </div>

                                <button type="submit"
                                        wire:loading.attr="disabled"
                                        class="btn btn-primary btn-block rounded-xl font-semibold shadow-md">
                                    <span wire:loading.remove class="flex items-center justify-center gap-2">
                                        <span>Verify & Continue</span>
                                        <x-icon name="o-arrow-right" class="w-4 h-4" />
                                    </span>
                                    <span wire:loading class="flex items-center justify-center gap-2">
                                        <span class="loading loading-spinner loading-xs"></span>
                                        <span>Verifying code...</span>
                                    </span>
                                </button>

                                <div class="text-center pt-1">
                                    <button type="button"
                                            wire:click="sendOtp"
                                            wire:loading.attr="disabled"
                                            class="text-xs text-base-content/70 hover:text-primary font-medium inline-flex items-center gap-1">
                                        <x-icon name="o-arrow-path" class="w-3.5 h-3.5" />
                                        <span>Didn't receive the code? Resend via WhatsApp</span>
                                    </button>
                                </div>
                            </form>
                        @endif
                    @else
                        {{-- Password Login Flow --}}
                        <form wire:submit="loginWithPassword" class="space-y-4" x-data="{ showPass: false }">
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider text-base-content/70 mb-1.5">
                                    Email or Phone Number
                                </label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-base-content/40">
                                        <x-icon name="o-user" class="w-5 h-5" />
                                    </span>
                                    <input wire:model="identifier"
                                           type="text"
                                           class="w-full h-11 pl-10 pr-4 rounded-xl border border-slate-300 bg-white text-slate-800 text-sm focus:border-primary focus:ring-2 focus:ring-primary/20 focus:outline-none transition-all placeholder:text-slate-400 shadow-xs @error('identifier') border-error ring-1 ring-error @enderror"
                                           placeholder="e.g. 08012345678 or name@example.com"
                                           required autofocus autocomplete="username" />
                                </div>
                                @error('identifier')
                                    <span class="text-error text-xs mt-1 block font-medium flex items-center gap-1">
                                        <x-icon name="o-exclamation-circle" class="w-3.5 h-3.5 shrink-0" /> {{ $message }}
                                    </span>
                                @enderror
                            </div>

                            <div>
                                <div class="flex items-center justify-between mb-1.5">
                                    <label class="text-xs font-bold uppercase tracking-wider text-base-content/70">
                                        Password
                                    </label>
                                    <button type="button"
                                            wire:click="$set('usePassword', false)"
                                            class="text-xs text-primary hover:underline font-medium">
                                        Forgot password?
                                    </button>
                                </div>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-base-content/40">
                                        <x-icon name="o-lock-closed" class="w-5 h-5" />
                                    </span>
                                    <input wire:model="password"
                                           :type="showPass ? 'text' : 'password'"
                                           class="w-full h-11 pl-10 pr-10 rounded-xl border border-slate-300 bg-white text-slate-800 text-sm focus:border-primary focus:ring-2 focus:ring-primary/20 focus:outline-none transition-all placeholder:text-slate-400 shadow-xs @error('password') border-error ring-1 ring-error @enderror"
                                           placeholder="Enter your password"
                                           required autocomplete="current-password" />
                                    <button type="button"
                                            @click="showPass = !showPass"
                                            class="absolute inset-y-0 right-0 flex items-center pr-3.5 text-base-content/40 hover:text-base-content focus:outline-none"
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

                            <button type="submit"
                                    wire:loading.attr="disabled"
                                    class="btn btn-primary btn-block rounded-xl font-semibold shadow-md hover:shadow-lg transition-all">
                                <span wire:loading.remove class="flex items-center justify-center gap-2">
                                    <span>Sign In</span>
                                    <x-icon name="o-arrow-right-end-on-rectangle" class="w-4 h-4" />
                                </span>
                                <span wire:loading class="flex items-center justify-center gap-2">
                                    <span class="loading loading-spinner loading-xs"></span>
                                    <span>Signing in...</span>
                                </span>
                            </button>
                        </form>
                    @endif
                </div>

                <!-- Bottom Form Footnote -->
                <div class="mt-6 pt-4 border-t border-base-200/80 flex items-center justify-between text-xs text-base-content/60">
                    <span class="inline-flex items-center gap-1">
                        <x-icon name="o-lock-closed" class="w-3.5 h-3.5 text-success" />
                        <span>SSL Encrypted</span>
                    </span>
                    <a href="{{ route('customer.register') }}" wire:navigate class="text-primary font-bold hover:underline">
                        Create Account &rarr;
                    </a>
                </div>
            </div>

            <!-- Right Bento Column (3 cols on lg): Trust & Assurance -->
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
                            Every drug is sourced directly from certified manufacturers with rigorous quality & expiration checks.
                        </p>
                    </div>
                    <div class="mt-4 pt-3 border-t border-white/15 text-[11px] text-emerald-200/80 font-medium flex items-center gap-1.5">
                        <x-icon name="o-check-badge" class="w-3.5 h-3.5 text-emerald-300" />
                        <span>Registered Community Pharmacy</span>
                    </div>
                </div>

                <!-- Card 4: Community Trust / Patient Rating -->
                <div class="bg-base-100 p-5 rounded-3xl border border-base-200/80 shadow-md text-center">
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
                    <div class="text-xs font-extrabold text-base-content mt-1.5">Over 2,500+ Patients Served</div>
                    <p class="text-[11px] text-base-content/60 mt-1">Serving families across Uyo with healthcare integrity</p>
                </div>
            </div>
        </div>
    </div>
</div>

