<x-filament-panels::page>
    <div class="mx-auto max-w-sm space-y-6">
        <x-filament::section>
            <x-slot name="heading">
                @if ($usingRecovery)
                    Enter a recovery code
                @else
                    Enter your authenticator code
                @endif
            </x-slot>
            <x-slot name="description">
                @if ($usingRecovery)
                    Use one of the recovery codes you saved when you set up 2FA.
                @else
                    Open your authenticator app and enter the 6-digit code for VortexOps.
                @endif
            </x-slot>

            <div class="space-y-4">
                @if (! $usingRecovery)
                    <div>
                        <x-filament::input.wrapper>
                            <x-filament::input
                                type="text"
                                wire:model="code"
                                wire:keydown.enter="verify"
                                inputmode="numeric"
                                autocomplete="one-time-code"
                                maxlength="6"
                                placeholder="000000"
                                autofocus
                            />
                        </x-filament::input.wrapper>
                    </div>
                @else
                    <div>
                        <x-filament::input.wrapper>
                            <x-filament::input
                                type="text"
                                wire:model="recoveryCode"
                                wire:keydown.enter="verify"
                                autocomplete="off"
                                placeholder="xxxx-xxxx-xxxx"
                            />
                        </x-filament::input.wrapper>
                    </div>
                @endif

                <x-filament::button wire:click="verify" class="w-full" color="primary" size="lg">
                    Verify &amp; Continue
                </x-filament::button>

                <button
                    type="button"
                    wire:click="$toggle('usingRecovery')"
                    class="w-full text-center text-sm text-primary-500 hover:text-primary-400 underline underline-offset-2"
                >
                    @if ($usingRecovery)
                        Use authenticator app instead
                    @else
                        Use a recovery code instead
                    @endif
                </button>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
