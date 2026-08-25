{{--
    The printed inventory handbook.

    Written for dompdf, which is not a browser: no flexbox, no grid, no gap,
    and CSS is applied per element rather than cascading the way you expect.
    Everything here is blocks, tables and explicit margins on purpose — the
    moment this uses a modern layout property it silently renders as one long
    left-aligned column.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>{{ $title }}</title>
<style>
    @page { margin: 22mm 16mm 18mm 16mm; }

    body {
        font-family: DejaVu Sans, sans-serif;
        font-size: 10pt;
        line-height: 1.55;
        color: #1f2937;
    }

    /* Running footer. dompdf supports fixed positioning against the page box,
       which is the only way to get something onto every page. */
    .page-footer {
        position: fixed;
        bottom: -12mm; left: 0; right: 0;
        font-size: 7.5pt;
        color: #9ca3af;
        border-top: 0.5pt solid #e5e7eb;
        padding-top: 4px;
    }
    .page-footer .right { float: right; }

    h1 { font-size: 26pt; margin: 0 0 6px; color: #111827; }
    h2 {
        font-size: 15pt; margin: 0 0 4px; color: #4c1d95;
        border-bottom: 1.5pt solid #ddd6fe; padding-bottom: 5px;
    }
    h3 { font-size: 11.5pt; margin: 0 0 2px; color: #111827; }

    p { margin: 0 0 7px; }

    .cover { text-align: center; padding-top: 55mm; }
    .cover .brand { font-size: 10pt; letter-spacing: 3px; color: #7c3aed; margin-bottom: 14px; }
    .cover .sub { font-size: 11.5pt; color: #6b7280; margin: 10px 60px 0; line-height: 1.6; }
    .cover .meta { margin-top: 34mm; font-size: 8.5pt; color: #9ca3af; }

    .toc-row td { padding: 5px 0; border-bottom: 0.5pt dotted #e5e7eb; font-size: 10.5pt; }
    .toc-row td.num { width: 22px; color: #7c3aed; font-weight: bold; }

    /* A section starts on a fresh page: a heading stranded at the foot of the
       previous one reads as though it belongs to what came before it. */
    .section { page-break-before: always; }
    .section-blurb { color: #6b7280; margin: 0 0 16px; font-size: 10pt; }

    /* A step and its screenshot must not be split — half an instruction on one
       page and its picture on the next is the failure mode of every generated
       manual. */
    .step { page-break-inside: avoid; margin-bottom: 18px; }
    .step-head { margin-bottom: 5px; }
    .step-num { color: #7c3aed; font-weight: bold; margin-right: 4px; }
    .where { font-size: 8.5pt; color: #7c3aed; margin: 0 0 6px 14px; font-weight: bold; }
    .body { margin-left: 14px; }
    .body p { font-size: 9.5pt; }

    .note {
        margin: 8px 0 0 14px; padding: 7px 10px;
        background: #fffbeb; border-left: 2.5pt solid #f59e0b;
        font-size: 8.5pt; color: #78350f;
    }

    .shot { margin: 9px 0 0 14px; }
    .shot img { width: 166mm; border: 0.5pt solid #d1d5db; }

    .trouble td {
        padding: 7px 8px; border-bottom: 0.5pt solid #e5e7eb;
        vertical-align: top; font-size: 9.5pt;
    }
    .trouble td.q { width: 34%; font-weight: bold; color: #111827; }

    .rule-of-thumb {
        margin-top: 14px; padding: 10px 12px;
        background: #f5f3ff; border-left: 3pt solid #7c3aed;
        font-size: 9.5pt; color: #4c1d95;
    }
</style>
</head>
<body>

<div class="page-footer">
    {{ $brand }} · {{ $title }}
    <span class="right">Generated {{ $generatedAt }}</span>
</div>

{{-- ── Cover ─────────────────────────────────────────────────────────── --}}
<div class="cover">
    <div class="brand">{{ strtoupper($brand) }}</div>
    <h1>{{ $title }}</h1>
    <div class="sub">{{ $subtitle }}</div>
    <div class="meta">
        Generated from this installation on {{ $generatedAt }}<br>
        Every screenshot is of your own system, not a demo.
    </div>
</div>

{{-- ── Contents ──────────────────────────────────────────────────────── --}}
<div class="section">
    <h2>Contents</h2>
    <table width="100%" cellspacing="0" cellpadding="0">
        @foreach ($sections as $i => $section)
            <tr class="toc-row">
                <td class="num">{{ $i + 1 }}</td>
                <td>
                    <b>{{ $section['title'] }}</b><br>
                    <span style="color:#6b7280;font-size:9pt">{{ $section['blurb'] }}</span>
                </td>
            </tr>
        @endforeach
        <tr class="toc-row">
            <td class="num">{{ count($sections) + 1 }}</td>
            <td><b>When something looks wrong</b><br>
                <span style="color:#6b7280;font-size:9pt">The things people hit, and what each one actually means.</span></td>
        </tr>
        <tr class="toc-row">
            <td class="num">{{ count($sections) + 2 }}</td>
            <td><b>Every screen, and what it is for</b><br>
                <span style="color:#6b7280;font-size:9pt">The whole module on one page, for when you just need to know where something lives.</span></td>
        </tr>
    </table>

    <div class="rule-of-thumb">
        <b>One rule underneath all of this.</b> A quantity never changes by being typed over.
        It changes by receiving, transferring, adjusting or reconciling — and each of those
        records what happened and who did it. That record is the difference between a
        discrepancy you can trace and one you can only argue about.
    </div>
</div>

{{-- ── Sections ──────────────────────────────────────────────────────── --}}
@foreach ($sections as $i => $section)
    <div class="section">
        <h2>{{ $i + 1 }}. {{ $section['title'] }}</h2>
        <p class="section-blurb">{!! $section['blurb'] !!}</p>

        @foreach ($section['steps'] as $n => $step)
            <div class="step">
                <div class="step-head">
                    <h3><span class="step-num">{{ $n + 1 }}.</span>{{ $step['title'] }}</h3>
                </div>

                @if ($step['where'])
                    <div class="where">{{ $step['where'] }}</div>
                @endif

                <div class="body">
                    @foreach ($step['body'] as $paragraph)
                        <p>{!! $paragraph !!}</p>
                    @endforeach
                </div>

                @if ($step['note'])
                    <div class="note">{!! $step['note'] !!}</div>
                @endif

                @if ($step['shot'] && isset($images[$step['shot']]))
                    <div class="shot"><img src="{{ $images[$step['shot']] }}" alt=""></div>
                @endif
            </div>
        @endforeach
    </div>
@endforeach

{{-- ── Troubleshooting ───────────────────────────────────────────────── --}}
<div class="section">
    <h2>{{ count($sections) + 1 }}. When something looks wrong</h2>
    <p class="section-blurb">Each of these has one cause far more often than any other.</p>

    <table width="100%" cellspacing="0" cellpadding="0" class="trouble">
        @foreach ($troubleshooting as [$question, $answer])
            <tr>
                <td class="q">{{ $question }}</td>
                <td>{{ $answer }}</td>
            </tr>
        @endforeach
    </table>
</div>

{{-- ── Screen index ──────────────────────────────────────────────────── --}}
<div class="section">
    <h2>{{ count($sections) + 2 }}. Every screen, and what it is for</h2>
    <p class="section-blurb">For when you know what you want and only need to be told where it lives.</p>

    <table width="100%" cellspacing="0" cellpadding="0" class="trouble">
        @foreach ($screenIndex as [$screen, $purpose])
            <tr>
                <td class="q">{{ $screen }}</td>
                <td>{{ $purpose }}</td>
            </tr>
        @endforeach
    </table>
</div>

</body>
</html>
