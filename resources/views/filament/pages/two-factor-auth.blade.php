<x-filament-panels::page>
    @php
        $user      = $this->getUser();
        $enabled   = $this->isEnabled();
        $confirmed = $this->isConfirmed();
    @endphp

    <div class="max-w-2xl space-y-6">

        {{-- Status card --}}
        <x-filament::section>
            <x-slot name="heading">Status</x-slot>
            <div class="flex items-center gap-3">
                @if ($enabled && $confirmed)
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-success-100 dark:bg-success-950 px-3 py-1 text-sm font-semibold text-success-700 dark:text-success-400">
                        <x-heroicon-s-shield-check class="size-4" />
                        Enabled &amp; Confirmed
                    </span>
                    <span class="text-sm text-gray-500 dark:text-gray-400">Your account is protected with two-factor authentication.</span>
                @elseif ($enabled && !$confirmed)
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-warning-100 dark:bg-warning-950 px-3 py-1 text-sm font-semibold text-warning-700 dark:text-warning-400">
                        <x-heroicon-s-exclamation-triangle class="size-4" />
                        Enabled — awaiting confirmation
                    </span>
                @else
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-gray-100 dark:bg-gray-800 px-3 py-1 text-sm font-semibold text-gray-600 dark:text-gray-400">
                        <x-heroicon-s-shield-exclamation class="size-4" />
                        Not enabled
                    </span>
                    <span class="text-sm text-gray-500 dark:text-gray-400">Add an extra layer of security to your account.</span>
                @endif
            </div>
        </x-filament::section>

        @if (! $enabled)
            {{-- Enable section --}}
            <x-filament::section>
                <x-slot name="heading">Enable Two-Factor Authentication</x-slot>
                <x-slot name="description">
                    When enabled, you'll be prompted for a 6-digit code from your authenticator app each time you log in.
                </x-slot>

                <x-filament::button wire:click="enable" color="primary" icon="heroicon-o-shield-check">
                    Enable 2FA
                </x-filament::button>
            </x-filament::section>

        @else
            {{-- QR Code --}}
            @if ($showingQr || ! $confirmed)
                <x-filament::section>
                    <x-slot name="heading">Scan QR Code</x-slot>
                    <x-slot name="description">
                        Scan this with Google Authenticator, Authy, 1Password, or any TOTP app.
                    </x-slot>

                    <div class="flex flex-col items-start gap-4">
                        <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white p-3 shadow-sm">
                            {!! $user->twoFactorQrCodeSvg() !!}
                        </div>

                        <details class="text-sm">
                            <summary class="cursor-pointer text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300">
                                Can't scan? Enter the setup key manually
                            </summary>
                            <code class="mt-2 block rounded bg-gray-100 dark:bg-gray-800 px-3 py-2 font-mono text-xs tracking-widest">
                                {{ decrypt($user->two_factor_secret) }}
                            </code>
                        </details>
                    </div>
                </x-filament::section>
            @endif

            {{-- Confirm --}}
            @if (! $confirmed)
                <x-filament::section>
                    <x-slot name="heading">Confirm Your Code</x-slot>
                    <x-slot name="description">
                        Enter the 6-digit code from your authenticator app to confirm setup.
                    </x-slot>

                    <div class="flex items-end gap-3">
                        <div class="flex-1 max-w-xs">
                            <x-filament::input.wrapper>
                                <x-filament::input
                                    type="text"
                                    wire:model="confirmationCode"
                                    inputmode="numeric"
                                    autocomplete="one-time-code"
                                    maxlength="6"
                                    placeholder="000000"
                                />
                            </x-filament::input.wrapper>
                        </div>
                        <x-filament::button wire:click="confirm" color="success">
                            Confirm
                        </x-filament::button>
                    </div>
                </x-filament::section>
            @endif

            {{-- Recovery codes --}}
            @if ($confirmed)
                <x-filament::section>
                    <x-slot name="heading">Recovery Codes</x-slot>
                    <x-slot name="description">
                        Store these codes somewhere safe. Each can be used once if you lose access to your authenticator.
                    </x-slot>

                    @if ($showingRecovery)
                        <div class="mb-4 grid grid-cols-2 gap-2 rounded-lg bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 p-4">
                            @foreach (json_decode(decrypt($user->two_factor_recovery_codes), true) as $code)
                                <code class="font-mono text-sm text-gray-700 dark:text-gray-300">{{ $code }}</code>
                            @endforeach
                        </div>
                    @endif

                    <div class="flex gap-2">
                        @if (! $showingRecovery)
                            <x-filament::button wire:click="$set('showingRecovery', true)" color="gray" icon="heroicon-o-eye">
                                Show Codes
                            </x-filament::button>
                        @endif
                        <x-filament::button wire:click="regenerateCodes" color="warning" icon="heroicon-o-arrow-path">
                            Regenerate Codes
                        </x-filament::button>
                    </div>
                </x-filament::section>
            @endif
        @endif

    </div>
</x-filament-panels::page>
