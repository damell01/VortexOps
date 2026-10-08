@auth
    <div id="vx-guide-shell" data-help-url="{{ \App\Filament\Pages\HelpCenter::getUrl(panel:'admin') }}">
        <script id="vx-guide-data" type="application/json">{!! json_encode(\App\Support\GuidedHelp::tours(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
        <nav class="vx-guide-launcher" aria-label="Help"><button type="button" data-vx-guide-start hidden>Walk me through this page</button><a href="{{ \App\Filament\Pages\HelpCenter::getUrl(panel:'admin') }}">Help & Guides</a></nav>
    </div>
@endauth
