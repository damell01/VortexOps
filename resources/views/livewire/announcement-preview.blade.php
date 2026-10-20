<div wire:poll.visible.30s class="vxc-banners">
    @foreach ($banners as $banner)
        <aside class="vxc-banner vxc-tone-{{ $banner->tone }}" wire:key="preview-banner-{{ $banner->id }}" role="status">
            <x-filament::icon icon="heroicon-o-megaphone" />
            <div><small>Owner preview · Ends {{ \App\Support\BusinessTime::local($banner->ends_at)->format('M j, g:i a T') }}</small><strong>{{ $banner->title }}</strong><p>{{ $banner->body }}</p></div>
            <a href="{{ \App\Filament\Pages\Communications::getUrl(panel: 'admin') }}" wire:navigate>Manage</a>
            @if ($banner->dismissible)<button type="button" wire:click="dismiss({{ $banner->id }})" aria-label="Dismiss {{ $banner->title }}" class="vxc-dismiss"><x-filament::icon icon="heroicon-o-x-mark" /></button>@endif
        </aside>
    @endforeach
</div>
