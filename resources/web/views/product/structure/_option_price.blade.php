@php $amount = (float) ($amount ?? 0); @endphp
@if ($amount != 0)
    <span class="option-price ml-1 text-sm font-medium"
        style="color: {{ $amount > 0 ? '#ee4d2d' : '#26aa99' }};">{{ $amount > 0 ? '+' : '−' }}{{ money(abs($amount)) }}</span>
@endif
