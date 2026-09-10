@php $vxGeneratedFooter = $generatedAt ?? now(); @endphp
<div style="margin-top:18px;padding-top:8px;border-top:1px solid #e5e7eb;text-align:center;font-size:8px;color:#94a3b8;">
    {{ \App\Support\ReportBranding::name() }} &bull; {{ $footerLabel ?? 'Operational Report' }} &bull; {{ $vxGeneratedFooter->format('M j, Y') }}
</div>
