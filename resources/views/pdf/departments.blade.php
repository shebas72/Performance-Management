@extends('pdf.layout', ['title' => $p->t('report.departments'), 'subtitle' => $p->t('through', ['period' => $p->raw('months.'.$meta['month']).' '.$meta['year']])])
@section('content')
@php
    // Parents first, each followed by its sub-departments (indented).
    $byParent = collect($departments)->groupBy(fn ($d) => $d['parent_id'] ?? 0);
    $ordered = [];
    $walk = function ($parent, $depth) use (&$walk, $byParent, &$ordered) {
        foreach ($byParent->get($parent, collect()) as $d) { $ordered[] = [$d, $depth]; $walk($d['id'], $depth + 1); }
    };
    $walk(0, 0);

    $rows = [];
    foreach ($ordered as [$d, $depth]) {
        $pad = $depth * 12;
        $label = new \Illuminate\Support\HtmlString('<div style="'.($p->rtl ? 'padding-right:' : 'padding-left:').$pad.'px;'.($depth === 0 ? 'font-weight:bold;' : '').'">'.$p->name($d, 34).'</div>');
        $rows[] = ['bg' => $depth === 0 ? '#f9fafb' : null, 'cells' => [
            $d['code'], $label,
            $p->scoreCell($d['current']['score'], $d['current']['status']),
            $p->scoreCell($d['ytd']['score'], $d['ytd']['status']),
            $d['current']['kpi_count'],
            $d['current']['high'].' / '.$d['current']['medium'].' / '.$d['current']['low'],
        ]];
    }
@endphp
@include('pdf._table', [
    'columns' => [
        ['label' => $p->t('col.code'), 'w' => '10%'],
        ['label' => $p->t('col.department'), 'w' => '32%'],
        ['label' => $p->t('col.month'), 'w' => '18%'],
        ['label' => $p->t('col.ytd'), 'w' => '18%'],
        ['label' => $p->t('col.kpis'), 'align' => 'center', 'w' => '8%'],
        ['label' => $p->t('col.mix'), 'align' => 'center', 'w' => '14%'],
    ],
    'rows' => $rows,
])
<div class="muted" style="margin-top:6px;font-size:8px;">{{ $p->t('dept_rollup_note') }}</div>
@endsection
