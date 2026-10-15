@php($season = \App\Support\Season::current())
@if ($season === 'halloween')
    {{-- October: Gengar peeks out from under the top bar now and then; a click sends him away for this session. --}}
    <div class="vx-season" data-season="halloween" aria-hidden="true"
         x-data="{ gone: (() => { try { return sessionStorage.getItem('vx-gengar-gone') === '1' } catch (e) { return false } })() }"
         x-show="! gone">
        <button type="button" class="vx-gengar-peek" tabindex="-1" title="Boo! (click to send Gengar away)"
                @click="gone = true; try { sessionStorage.setItem('vx-gengar-gone', '1') } catch (e) {}">
            <img src="/img/pokemon/gengar.gif" alt="">
        </button>
    </div>
@endif
