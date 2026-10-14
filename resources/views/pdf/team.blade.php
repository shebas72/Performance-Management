@extends('pdf.layout', ['title' => $p->t('report.team'), 'subtitle' => $p->t('through', ['period' => $p->raw('months.'.$t['period']['month']).' '.$t['period']['year']])])
@section('content')
@php $s = $t['summary']; @endphp
@include('pdf._tiles', ['tiles' => [
    ['label' => $p->t('team.people'), 'value' => $s['people']],
    ['label' => $p->t('kpis_total'), 'value' => $s['kpi_count']],
    ['label' => $p->t('overall_month'), 'value' => $p->pct($s['current']['score']), 'color' => $p->color($s['current']['status']), 'sub' => $p->status($s['current']['status'])],
    ['label' => $p->t('overall_ytd'), 'value' => $p->pct($s['ytd']['score']), 'color' => $p->color($s['ytd']['status']), 'sub' => $p->status($s['ytd']['status'])],
    ['label' => $p->t('team.data_entry'), 'value' => $p->pct($s['data']['pct']), 'sub' => $p->t('team.missing', ['n' => $s['data']['missing']])],
]])

@if (! empty($t['teams']))
    <h2>{{ $p->t('team.teams') }}</h2>
    @php
        $rows = [];
        foreach ($t['teams'] as $tm) {
            $deps = collect($tm['departments'])->map(fn ($d) => $p->rtl && filled($d['name_ar']) ? $d['name_ar'] : $d['name'])->implode($p->rtl ? '، ' : ', ');
            $rows[] = ['cells' => [
                $p->x($tm['manager']['name']), $p->x($deps, 40), $tm['member_count'], $tm['kpi_count'],
                $p->scoreCell($tm['current']['score'], $tm['current']['status']), $p->scoreCell($tm['ytd']['score'], $tm['ytd']['status']),
                $p->pct($tm['data']['pct']),
            ]];
        }
    @endphp
    @include('pdf._table', [
        'columns' => [
            ['label' => $p->t('team.manager'), 'w' => '18%'],
            ['label' => $p->t('col.department'), 'w' => '24%'],
            ['label' => $p->t('team.members'), 'align' => 'center', 'w' => '8%'],
            ['label' => $p->t('col.kpis'), 'align' => 'center', 'w' => '7%'],
            ['label' => $p->t('col.month'), 'w' => '14%'],
            ['label' => $p->t('col.ytd'), 'w' => '14%'],
            ['label' => $p->t('team.data_entry'), 'align' => 'center', 'w' => '10%'],
        ],
        'rows' => $rows,
    ])
@endif

<h2>{{ $p->t('team.people_title') }}</h2>
@php
    $rows = [];
    foreach ($t['people'] as $r) {
        $name = $r['unassigned'] ? $p->t('team.unassigned') : $p->x($r['name']);
        $role = ! empty($r['role_label']) ? $p->name($r['role_label']) : '';
        $deps = collect($r['departments'])->map(fn ($d) => $p->rtl && filled($d['name_ar']) ? $d['name_ar'] : $d['name'])->implode($p->rtl ? '، ' : ', ');
        $cell = new \Illuminate\Support\HtmlString('<b>'.$name.'</b>'.($role !== '' ? '<div class="muted" style="font-size:7.5px;">'.$role.'</div>' : ''));
        $w = fn ($b) => $b['count'] ? $b['count'].($b['avg_pct'] !== null ? ' · '.$p->pct($b['avg_pct'], 0) : '') : '—';
        $tasks = $r['tasks']['count'] ? $r['tasks']['count'].($r['tasks']['overdue'] ? ' ('.$r['tasks']['overdue'].' '.$p->raw('team.overdue').')' : '') : '—';
        $rows[] = ['bg' => $r['is_me'] ? '#eff6ff' : null, 'cells' => [
            $cell, $p->x($deps, 28), $r['kpi_count'],
            $p->scoreCell($r['current']['score'], $r['current']['status']), $p->scoreCell($r['ytd']['score'], $r['ytd']['status']),
            $p->pct($r['data']['pct']), $w($r['projects']), $w($r['initiatives']), $p->x($tasks),
        ]];
    }
@endphp
@include('pdf._table', [
    'columns' => [
        ['label' => $p->t('team.person'), 'w' => '15%'],
        ['label' => $p->t('col.department'), 'w' => '15%'],
        ['label' => $p->t('col.kpis'), 'align' => 'center', 'w' => '6%'],
        ['label' => $p->t('col.month'), 'w' => '12%'],
        ['label' => $p->t('col.ytd'), 'w' => '12%'],
        ['label' => $p->t('team.data_entry'), 'align' => 'center', 'w' => '8%'],
        ['label' => $p->t('team.projects'), 'align' => 'center', 'w' => '10%'],
        ['label' => $p->t('team.initiatives'), 'align' => 'center', 'w' => '10%'],
        ['label' => $p->t('team.tasks'), 'align' => 'center', 'w' => '12%'],
    ],
    'rows' => $rows,
])
@endsection
