@props(['rows' => []])
{{-- Goal-style progress rows: label, figure, a bar with an optional "expected by today" tick. --}}
<div class="vxp">
    @foreach ($rows as $r)
        @php $pct = max(0, min(100, (float) $r['pct'])); $tone = $r['tone'] ?? 'accent'; @endphp
        <div class="vxp-row">
            <div class="vxp-top"><span class="vxp-l">{{ $r['label'] }}@if(! empty($r['sub']))<small>{{ $r['sub'] }}</small>@endif</span><b>{{ $r['value'] }}</b></div>
            <div class="vxp-bar vxp-{{ $tone }}"><i style="width:{{ $pct }}%"></i>@isset($r['expected'])<em style="left:{{ max(0, min(100, $r['expected'])) }}%"></em>@endisset<span>{{ round((float) $r['pct']) }}%</span></div>
        </div>
    @endforeach
</div>
