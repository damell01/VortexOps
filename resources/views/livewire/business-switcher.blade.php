<div class="vx-channel-switcher vx-business-switcher">
    <x-vx-picker label="Business" all-label="All Businesses" model="activeBusinessId" icon="business"
        :current="$current?->id"
        :options="$businesses->map(fn ($b) => ['value' => $b->id, 'label' => $b->name, 'color' => $b->color])->values()->all()" />
</div>
