@extends('pdf.layout', ['title' => $p->name($k, 60), 'subtitle' => new \Illuminate\Support\HtmlString(e($k['code']).' · '.$k['year'])])
@section('content')
@php
    $fmt = function ($v) use ($k, $p) {
        if ($v === null) return '—';
        $n = number_format((float) $v, 2, '.', ',');
        $n = rtrim(rtrim($n, '0'), '.');

        return $p->x($n.($k['value_type'] === 'percentage' ? '%' : ($k['unit'] ? ' '.$k['unit'] : '')));
    };
    $dash = fn ($v) => filled($v) ? $v : '—';
@endphp

@include('pdf._tiles', ['tiles' => [
    ['label' => $p->t('kpi.score_month', ['month' => $p->raw('months.'.$summary['month'])]), 'value' => $p->pct($summary['score']), 'color' => $p->color($summary['status']), 'sub' => $p->status($summary['status'])],
    ['label' => $p->t('overall_ytd'), 'value' => $p->pct($summary['ytd']), 'color' => $p->color($summary['ytd_status']), 'sub' => $p->status($summary['ytd_status'])],
    ['label' => $p->t('kpi.annual_target'), 'value' => $fmt($k['annual_target'])],
    ['label' => $p->t('kpi.weight'), 'value' => $p->num($k['weight'], 0).'%'],
]])

<h2>{{ $p->t('kpi.details') }}</h2>
@php
    $pairs = [
        [$p->t('col.department'), $k['department'] ? $p->name($k['department']) : '—', $p->t('kpi.owner'), $k['owner'] ? $p->x($k['owner']) : '—'],
        [$p->t('col.objective'), $k['objective'] ? $p->x(($k['objective']['code'] ?? '').' '.($p->rtl && filled($k['objective']['name_ar'] ?? null) ? $k['objective']['name_ar'] : $k['objective']['name']), 40) : '—', $p->t('col.perspective'), $k['perspective'] ? $p->name($k['perspective']) : '—'],
        [$p->t('kpi.type'), $p->t('kpi_type.'.$k['type']), $p->t('kpi.frequency'), $p->t('frequency.'.$k['frequency'])],
        [$p->t('kpi.direction'), $p->t('direction.'.$k['direction']), $p->t('kpi.thresholds'), $p->num($k['threshold_red'], 0).'% / '.$p->num($k['threshold_yellow'], 0).'%'],
    ];
    $descr = $p->rtl && filled($k['description_ar']) ? $k['description_ar'] : $k['description'];
@endphp
<table class="grid">
    @foreach ($pairs as $row)
        @php $row = $p->rtl ? array_reverse($row) : $row; @endphp
        {{-- Arabic: the table is read from the right, so each label sits right of its value --}}
        <tr>
            <td style="width:16%;color:#6b7280;text-align:{{ $p->start }};">{{ $row[0] }}</td>
            <td style="width:34%;text-align:{{ $p->start }};">{{ $row[1] }}</td>
            <td style="width:16%;color:#6b7280;text-align:{{ $p->start }};">{{ $row[2] }}</td>
            <td style="width:34%;text-align:{{ $p->start }};">{{ $row[3] }}</td>
        </tr>
    @endforeach
    @if (filled($descr))
        @php
            $dl = new \Illuminate\Support\HtmlString('<td style="width:16%;color:#6b7280;text-align:'.$p->start.';">'.$p->t('kpi.description').'</td>');
            $dv = new \Illuminate\Support\HtmlString('<td colspan="3" style="text-align:'.$p->start.';">'.$p->x($descr, 110).'</td>');
        @endphp
        <tr>{!! $p->rtl ? $dv.$dl : $dl.$dv !!}</tr>
    @endif
</table>

<h2>{{ $p->t('kpi.monthly') }}</h2>
@include('pdf._trend', ['points' => collect($rows)->map(fn ($r) => ['month' => $r['month'], 'score' => $r['score']])->all()])

@php
    $tableRows = [];
    foreach ($rows as $r) {
        if ($r['target'] === null && $r['actual'] === null) continue;
        $note = $r['incomplete_reason'] ? $p->raw('kpi.incomplete').': '.$r['incomplete_reason'] : ($r['note'] ?? '');
        $tableRows[] = ['cells' => [
            $p->month($r['month'], false), $fmt($r['target']), $fmt($r['actual']), $p->pct($r['achievement']),
            $r['status'] === 'not_entered' ? '—' : $p->badge($r['status']), $p->x((string) $note, 36),
        ]];
    }
@endphp
@include('pdf._table', [
    'columns' => [
        ['label' => $p->t('col.month'), 'w' => '14%'],
        ['label' => $p->t('kpi.target'), 'w' => '15%'],
        ['label' => $p->t('kpi.actual'), 'w' => '15%'],
        ['label' => $p->t('kpi.achievement'), 'w' => '13%'],
        ['label' => $p->t('col.status'), 'w' => '13%'],
        ['label' => $p->t('kpi.note'), 'w' => '30%'],
    ],
    'rows' => $tableRows,
])

<h2>{{ $p->t('kpi.history') }}</h2>
@php
    $hRows = [];
    foreach ($history as $h) {
        $what = $p->raw('actions.'.$h['action']);
        if ($h['field']) $what .= ' · '.$p->raw('fields.'.$h['field']);
        if ($h['month']) $what .= ' ('.$p->raw('months_short.'.$h['month']).' '.$h['year'].')';
        // Old and new are shaped separately so the pair reads in the page's direction: old → new, or new ← old when Arabic.
        $old = $p->x($h['old'] ?? '—', 30); $new = $p->x($h['new'] ?? '—', 30);
        $change = $h['action'] === 'kpi_created' ? '' : new \Illuminate\Support\HtmlString($p->rtl ? $new.' ← '.$old : $old.' → '.$new);
        $hRows[] = ['cells' => [$p->date($h['at']), $p->x($h['user'] ?? '—'), $p->x($what, 40), $change]];
    }
@endphp
@include('pdf._table', [
    'columns' => [
        ['label' => $p->t('col.date'), 'w' => '13%'],
        ['label' => $p->t('col.user'), 'w' => '18%'],
        ['label' => $p->t('col.change'), 'w' => '37%'],
        ['label' => $p->t('col.values'), 'w' => '32%'],
    ],
    'rows' => $hRows,
])
@if ($creator)
    <div class="muted" style="margin-top:6px;font-size:8px;">{{ $p->t('kpi.created_by', ['name' => $creator['name'], 'date' => $p->date($creator['at'])]) }}</div>
@endif
@endsection
