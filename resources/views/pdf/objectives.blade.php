@extends('pdf.layout', ['title' => $p->t('report.objectives'), 'subtitle' => $p->t('through', ['period' => $p->raw('months.'.$meta['month']).' '.$meta['year']])])
@section('content')
@php $cur = $overall['current']; $ytd = $overall['ytd']; @endphp
@include('pdf._tiles', ['tiles' => [
    ['label' => $p->t('overall_month'), 'value' => $p->pct($cur['score']), 'color' => $p->color($cur['status']), 'sub' => $p->status($cur['status'])],
    ['label' => $p->t('overall_ytd'),   'value' => $p->pct($ytd['score']), 'color' => $p->color($ytd['status']), 'sub' => $p->status($ytd['status'])],
    ['label' => $p->t('objectives_total'), 'value' => count($objectives)],
]])

@php $groups = collect($objectives)->groupBy(fn ($o) => $o['perspective']['id'] ?? 0); @endphp
@foreach ($groups as $rowsOf)
    @php $persp = $rowsOf->first()['perspective']; @endphp
    <h2>
        <span class="dot" style="background:{{ $persp['color'] ?? '#9ca3af' }};"></span>
        {{ $persp ? $p->name($persp) : $p->t('no_perspective') }}
    </h2>
    @php
        $rows = [];
        foreach ($rowsOf as $o) {
            $rows[] = ['cells' => [
                $o['code'], $p->name($o, 44), $p->num($o['weight'], 0).'%',
                $p->scoreCell($o['current']['score'], $o['current']['status']),
                $p->scoreCell($o['ytd']['score'], $o['ytd']['status']),
                $o['current']['kpi_count'],
                $o['current']['high'].' / '.$o['current']['medium'].' / '.$o['current']['low'],
            ]];
        }
    @endphp
    @include('pdf._table', [
        'columns' => [
            ['label' => $p->t('col.code'), 'w' => '10%'],
            ['label' => $p->t('col.objective'), 'w' => '31%'],
            ['label' => $p->t('col.weight'), 'align' => 'center', 'w' => '8%'],
            ['label' => $p->t('col.month'), 'w' => '16%'],
            ['label' => $p->t('col.ytd'), 'w' => '16%'],
            ['label' => $p->t('col.kpis'), 'align' => 'center', 'w' => '7%'],
            ['label' => $p->t('col.mix'), 'align' => 'center', 'w' => '12%'],
        ],
        'rows' => $rows,
    ])
@endforeach
@if (empty($objectives))<div class="empty">{{ $p->t('no_data') }}</div>@endif
@endsection
