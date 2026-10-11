{{-- $columns: [['label' => string|HtmlString, 'align' => start|end|center, 'w' => '20%'], ...]
     $rows:    [['cells' => [cell per column], 'bg' => '#fff' (optional)], ...]   cells are HtmlString or plain text --}}
@php $idx = $p->order(array_keys($columns)); @endphp
<table class="grid">
    <thead>
        <tr>
            @foreach ($idx as $i)
                <th style="text-align:{{ $p->align($columns[$i]['align'] ?? 'start') }};{{ isset($columns[$i]['w']) ? 'width:'.$columns[$i]['w'].';' : '' }}">{{ $columns[$i]['label'] }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @foreach ($rows as $r)
            <tr @if (! empty($r['bg'])) style="background:{{ $r['bg'] }};" @endif>
                @foreach ($idx as $i)
                    <td style="text-align:{{ $p->align($columns[$i]['align'] ?? 'start') }};">{{ $r['cells'][$i] ?? '' }}</td>
                @endforeach
            </tr>
        @endforeach
    </tbody>
</table>
@if (empty($rows))
    <div class="empty">{{ $p->t('no_data') }}</div>
@endif
