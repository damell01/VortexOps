@php
    // The sign-in background: a slow wall of Vortex-graded slabs. Each column
    // repeats its list twice so the drift loops without a seam.
    $mons = ['Pikachu', 'Gengar', 'Charizard', 'Eevee', 'Mewtwo', 'Snorlax', 'Umbreon', 'Bulbasaur', 'Squirtle', 'Lucario',
        'Jigglypuff', 'Dragonite', 'Sylveon', 'Mew', 'Psyduck', 'Greninja', 'Haunter', 'Espeon', 'Charmander', 'Mimikyu',
        'Meowth', 'Gyarados', 'Lapras', 'Togepi', 'Vaporeon', 'Jolteon', 'Flareon', 'Blastoise'];
    $columns = 9;
    $perColumn = 4;
    $cols = [];
    for ($c = 0; $c < $columns; $c++) {
        $cols[$c] = [];
        for ($r = 0; $r < $perColumn; $r++) {
            $i = ($c * $perColumn + $r) % count($mons);
            $cols[$c][] = ['name' => $mons[$i], 'cert' => str_pad((string) (40213 + $i * 7919 % 90000), 8, '0', STR_PAD_LEFT), 'year' => 1999 + ($i * 3) % 27];
        }
    }
@endphp
<div class="vx-wall" aria-hidden="true">
    <div class="vx-wall__cols">
        @foreach ($cols as $c => $slabs)
            <div class="vx-wall__col">
                @foreach ([0, 1] as $copy)
                    @foreach ($slabs as $slab)
                        <div class="vx-slab">
                            <div class="vx-slab__label">
                                <span class="vx-slab__brand">VORTEX</span>
                                <span class="vx-slab__name">{{ $slab['year'] }} {{ strtoupper($slab['name']) }}</span>
                                <span class="vx-slab__grade"><small>GEM MT</small>10</span>
                                <span class="vx-slab__cert">#{{ $slab['cert'] }}</span>
                            </div>
                            <div class="vx-slab__window">
                                <img src="{{ asset('img/pokemon3d/' . strtolower($slab['name']) . '.gif') }}" alt="" loading="lazy" decoding="async" width="96" height="96">
                            </div>
                        </div>
                    @endforeach
                @endforeach
            </div>
        @endforeach
    </div>
    <div class="vx-wall__veil"></div>
</div>
