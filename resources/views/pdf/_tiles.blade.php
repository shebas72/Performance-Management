{{-- $tiles: [['label' => string|HtmlString, 'value' => string|HtmlString, 'sub' => optional, 'color' => optional hex], ...] --}}
@php $tiles = $p->order($tiles); $n = count($tiles); @endphp
<table class="tiles">
    <tr>
        @foreach ($tiles as $k => $t)
            <td class="tile" style="width:{{ floor(100 / max($n, 1)) - 2 }}%;text-align:{{ $p->start }};">
                <div class="l">{{ $t['label'] }}</div>
                <div class="v" @if (! empty($t['color'])) style="color:{{ $t['color'] }};" @endif>{{ $t['value'] }}</div>
                @if (! empty($t['sub']))<div class="l">{{ $t['sub'] }}</div>@endif
            </td>
            @if ($k < $n - 1)<td class="gap"></td>@endif
        @endforeach
    </tr>
</table>
