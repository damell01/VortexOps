@php
    $vxLogo = \App\Support\ReportBranding::logoData();
    $vxGenerated = $generatedAt ?? now();
    $vxTitle = $reportTitle ?? 'Report';
    $vxSubtitle = $reportSubtitle ?? null;
@endphp
<table style="width:100%;border-collapse:collapse;margin:0 0 18px 0;background:#0f172a;border-bottom:4px solid #7c3aed;">
    <tr>
        <td style="padding:13px 16px;border:0;vertical-align:middle;width:52%;color:#fff;">
            @if($vxLogo)
                <img src="{{ $vxLogo }}" alt="VortexOps" style="width:46px;height:46px;object-fit:contain;vertical-align:middle;margin-right:9px;">
            @endif
            <span style="display:inline-block;vertical-align:middle;">
                <span style="font-size:19px;font-weight:800;color:#fff;">{{ \App\Support\ReportBranding::name() }}</span><br>
                <span style="font-size:8px;letter-spacing:1px;text-transform:uppercase;color:#c4b5fd;">Operations & Reporting</span>
            </span>
        </td>
        <td style="padding:13px 16px;border:0;vertical-align:middle;width:48%;text-align:right;color:#fff;">
            <div style="font-size:15px;font-weight:800;color:#fff;">{{ $vxTitle }}</div>
            @if($vxSubtitle)<div style="margin-top:2px;font-size:9px;color:#ddd6fe;">{{ $vxSubtitle }}</div>@endif
            <div style="margin-top:4px;font-size:8px;color:#cbd5e1;">{{ \App\Support\ReportBranding::generatedLabel($vxGenerated) }}</div>
        </td>
    </tr>
</table>
