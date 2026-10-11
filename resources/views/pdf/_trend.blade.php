{{-- $points: [['month' => 1..12, 'score' => float|null], ...]; draws one bar per month --}}
@php
    $pts = $p->order($points);
    $max = max(100, (int) ceil(collect($points)->max('score') ?? 100));
    $h = 80;
    $w = floor(100 / max(count($pts), 1));
@endphp
<table style="width:100%;">
    <tr>
        @foreach ($pts as $pt)
            @php $bh = $pt['score'] === null ? 0 : max(1, round(min($pt['score'], $max) / $max * $h)); @endphp
            <td style="width:{{ $w }}%;height:{{ $h + 14 }}px;vertical-align:bottom;text-align:center;">
                @if ($pt['score'] !== null)
                    <div style="font-size:7px;color:#374151;">{{ $p->num($pt['score'], 0) }}</div>
                    <div style="width:55%;height:{{ $bh }}px;background:{{ $p->color($p->scoreStatus($pt['score'])) }};margin:0 auto;"></div>
                @endif
            </td>
        @endforeach
    </tr>
    <tr>
        @foreach ($pts as $pt)
            <td style="text-align:center;font-size:7px;color:#6b7280;border-top:1px solid #d1d5db;padding-top:2px;">{{ $p->month($pt['month']) }}</td>
        @endforeach
    </tr>
</table>
