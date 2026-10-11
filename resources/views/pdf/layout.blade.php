<!DOCTYPE html>
<html lang="{{ $p->lang }}" dir="{{ $p->dir }}">
<head>
<meta charset="utf-8">
<title>{{ strip_tags((string) $title) }}</title>
<style>
    @page { margin: 34px 36px 52px 36px; }
    * { font-family: 'DejaVu Sans', sans-serif; }
    body { font-size: 9px; color: #1f2937; margin: 0; text-align: {{ $p->start }}; }
    h2 { font-size: 11.5px; margin: 16px 0 6px; color: #111827; text-align: {{ $p->start }}; }
    table { width: 100%; border-collapse: collapse; }
    .head td { vertical-align: middle; }
    .title { font-size: 17px; font-weight: bold; color: #111827; }
    .muted { color: #6b7280; }
    .rule { border-bottom: 2px solid #1d4ed8; height: 6px; margin-bottom: 10px; }
    .grid th { background: #f3f4f6; font-size: 8px; color: #374151; padding: 5px 6px; border-bottom: 1px solid #d1d5db; }
    .grid td { padding: 5px 6px; border-bottom: 1px solid #e5e7eb; vertical-align: middle; }
    .grid thead { display: table-header-group; }
    .grid tr { page-break-inside: avoid; }
    .tiles td.tile { border: 1px solid #e5e7eb; padding: 8px 10px; vertical-align: top; }
    .tiles td.gap { width: 8px; border: 0; padding: 0; }
    .tile .v { font-size: 18px; font-weight: bold; }
    .tile .l { font-size: 8px; color: #6b7280; }
    .dot { display: inline-block; width: 7px; height: 7px; }
    .note { background: #fffbeb; border: 1px solid #fde68a; padding: 6px 8px; color: #92400e; margin: 6px 0; }
    .empty { color: #6b7280; padding: 10px 0; }
</style>
</head>
<body>
<table class="head">
    <tr>
        @php
            $logo = ! empty($p->company['logo']) ? '<img src="'.$p->company['logo'].'" style="height:34px;">' : '';
            $titleCell = '<div class="title">'.$title.'</div>'.(isset($subtitle) ? '<div class="muted" style="margin-top:3px;">'.$subtitle.'</div>' : '');
            $nameCell = '<div style="font-weight:bold;font-size:10px;">'.$p->x($p->company['name'] ?? '').'</div><div class="muted" style="font-size:8px;margin-top:2px;">'.$p->t('generated').' '.e($p->generatedAt).'</div>';
            $cells = $p->rtl
                ? [['w' => '30%', 'a' => 'left', 'h' => $nameCell], ['w' => '70%', 'a' => 'right', 'h' => $titleCell]]
                : [['w' => '70%', 'a' => 'left', 'h' => $titleCell], ['w' => '30%', 'a' => 'right', 'h' => $nameCell]];
        @endphp
        @foreach ($cells as $c)
            <td style="width:{{ $c['w'] }};text-align:{{ $c['a'] }};">{!! $c['h'] !!}</td>
        @endforeach
    </tr>
    @if ($logo)
    <tr><td colspan="2" style="text-align:{{ $p->start }};padding-top:6px;">{!! $logo !!}</td></tr>
    @endif
</table>
<div class="rule"></div>

@yield('content')
</body>
</html>
