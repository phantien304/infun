{!! sprintf(trans('mailer.voucher.greeting'), number_format((float) $voucher->amount, 0, ',', '.') . 'đ') !!}<br>
===============================
<br>
{!! sprintf(trans('mailer.voucher.from'), getConfigDb('config_name')) !!}<br>
{{ trans('mailer.voucher.reward_reason') }}<br>
@if (filled($voucher->message))
{{ trans('mailer.voucher.message') }} {{ $voucher->message }}<br>
@endif
----------------------------<br>
{!! sprintf(trans('mailer.voucher.redeem'), $voucher->code) !!}<br>
<b><a href="{{ route('home') }}">{{ route('home') }}</a></b><br>
----------------------------<br>
@if ($voucher->date_expire)
{!! sprintf(trans('mailer.voucher.expire'), \Illuminate\Support\Carbon::parse($voucher->date_expire)->format('d/m/Y')) !!}<br>
@endif
<br>
{{ trans('mailer.voucher.footer') }}<br>
