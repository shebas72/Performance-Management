@extends('pdf.layout', ['title' => $p->t('report.dashboard'), 'subtitle' => $p->t('through', ['period' => $p->raw('months.'.$d['period']['month']).' '.$d['period']['year']])])
@section('content')
@php
    $cur = $d['overall']['current']; $ytd = $d['overall']['ytd']; $comp = $d['data_completeness'];
@endphp

@if ($d['scope']['restricted'])
    <div class="note">
        {{ $p->t('restricted_note') }}
        @foreach ($d['scope']['departments'] as $dep){{ $loop->first ? ' ' : ($p->rtl ? '، ' : ', ') }}{{ $p->name($dep) }}@endforeach
    </div>
@endif

@include('pdf._tiles', ['tiles' => [
    ['label' => $p->t('overall_month'), 'value' => $p->pct($cur['score']), 'color' => $p->color($cur['status']), 'sub' => $p->status($cur['status'])],
    ['label' => $p->t('overall_ytd'),   'value' => $p->pct($ytd['score']), 'color' => $p->color($ytd['status']), 'sub' => $p->status($ytd['status'])],
    ['label' => $p->t('kpis_total'),    'value' => $cur['kpi_count']],
    ['label' => $p->t('data_entered'),  'value' => $p->pct($comp['pct']), 'sub' => $comp['entered'].' / '.$comp['expected']],
]])

<table class="tiles" style="margin-top:8px;">
    @php
        $mix = $p->order([
            ['on_track', $cur['high']], ['at_risk', $cur['medium']], ['behind', $cur['low']], ['not_entered', $cur['not_entered']],
        ]);
    @endphp
    <tr>
        @foreach ($mix as [$st, $count])
            <td style="width:25%;padding:5px 8px;background:{{ \App\Services\Pdf\PdfContext::SOFT[$st] }};text-align:{{ $p->start }};">
                <b style="color:{{ $p->color($st) }};font-size:13px;">{{ $count }}</b>
                <span style="color:{{ $p->color($st) }};"> {{ $p->status($st) }}</span>
            </td>
        @endforeach
    </tr>
</table>

<h2>{{ $p->t('perspective_scorecard') }}</h2>
@php
    $rows = [];
    foreach ($d['perspectives'] as $x) {
        $rows[] = ['cells' => [
            new \Illuminate\Support\HtmlString('<span class="dot" style="background:'.e($x['color'] ?: '#9ca3af').';"></span> '.$p->name($x)),
            $p->num($x['weight'], 0).'%',
            $p->scoreCell($x['current']['score'], $x['current']['status']),
            $p->scoreCell($x['ytd']['score'], $x['ytd']['status']),
            $x['current']['kpi_count'],
            $x['current']['high'].' / '.$x['current']['medium'].' / '.$x['current']['low'],
        ]];
    }
@endphp
@include('pdf._table', [
    'columns' => [
        ['label' => $p->t('col.perspective'), 'w' => '30%'],
        ['label' => $p->t('col.weight'), 'align' => 'center', 'w' => '9%'],
        ['label' => $p->t('col.month'), 'w' => '19%'],
        ['label' => $p->t('col.ytd'), 'w' => '19%'],
        ['label' => $p->t('col.kpis'), 'align' => 'center', 'w' => '8%'],
        ['label' => $p->t('col.mix'), 'align' => 'center', 'w' => '15%'],
    ],
    'rows' => $rows,
])

<h2>{{ $p->t('trend_title') }}</h2>
@include('pdf._trend', ['points' => $d['trend']])

<h2>{{ $p->t('worst_kpis') }}</h2>
@php
    $rows = [];
    foreach ($d['worst_kpis'] as $k) {
        $dep = $depts[$k['department_id']] ?? null;
        $rows[] = ['cells' => [$k['code'], $p->name($k, 38), $dep ? $p->name($dep, 24) : '—', $p->scoreCell($k['score'], 'behind')]];
    }
@endphp
@include('pdf._table', [
    'columns' => [
        ['label' => $p->t('col.code'), 'w' => '14%'],
        ['label' => $p->t('col.kpi'), 'w' => '40%'],
        ['label' => $p->t('col.department'), 'w' => '26%'],
        ['label' => $p->t('col.month'), 'w' => '20%'],
    ],
    'rows' => $rows,
])

@if (! empty($comp['incomplete_reasons']))
    <h2>{{ $p->t('incomplete_title') }}</h2>
    @php
        $rows = [];
        foreach ($comp['incomplete_reasons'] as $reason => $count) $rows[] = ['cells' => [$p->x((string) $reason, 60), $count]];
    @endphp
    @include('pdf._table', [
        'columns' => [['label' => $p->t('col.reason'), 'w' => '80%'], ['label' => $p->t('col.count'), 'align' => 'center', 'w' => '20%']],
        'rows' => $rows,
    ])
@endif
@endsection
