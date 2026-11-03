<x-filament-panels::page>
    <div class="vxr" data-vx-page="owner-tools">
        @forelse ($this->sections() as $label => $tools)
            <section class="vx-owner-section" aria-labelledby="owner-{{ \Illuminate\Support\Str::slug($label) }}">
                <h2 id="owner-{{ \Illuminate\Support\Str::slug($label) }}" class="vx-owner-heading">{{ $label }}</h2>
                <div class="vx-owner-grid">
                    @foreach ($tools as $tool)
                        <a href="{{ $tool['url'] }}" wire:navigate class="vx-owner-card">
                            <span class="vx-owner-icon"><x-filament::icon :icon="$tool['icon']" /></span>
                            <span class="min-w-0">
                                <b>{{ $tool['title'] }}</b>
                                <span>{{ $tool['text'] }}</span>
                            </span>
                        </a>
                    @endforeach
                </div>
            </section>
        @empty
            <div class="vxr-empty">No tools are available to you.</div>
        @endforelse
    </div>
</x-filament-panels::page>
