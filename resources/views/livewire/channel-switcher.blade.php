<div class="vx-channel-switcher">
    <x-vx-picker label="Channel" all-label="All Channels" model="activeChannelId" icon="channel"
        :current="$activeChannelId"
        :options="$channels->map(fn ($c) => ['value' => $c->id, 'label' => $c->name, 'color' => \App\Filament\Pages\Shows::channelColor($c->id)])->values()->all()" />
</div>
