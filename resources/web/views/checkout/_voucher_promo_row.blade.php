{{--
    Promo row "Thẻ quà tặng" (voucher gift card).
    Input:
      $myVouchers (Collection<VoucherDTO>) — chỉ có khi user logged in
      $appliedVoucherCodes (array<string>)
--}}
@php
    $appliedCount = is_array($appliedVoucherCodes ?? null) ? count($appliedVoucherCodes) : 0;
    $myRedeemableCount = isset($myVouchers) ? $myVouchers->where('redeemable', true)->count() : 0;
@endphp

<div x-data class="py-3 my-3 border-top border-bottom">
    <button type="button" class="w-full flex items-center gap-3 px-3 py-2 hover:bg-gray-50 transition rounded"
        @click="$dispatch('open-voucher-modal')">
        <i class="fi-rs-credit-card text-purple-500 text-2xl"></i>
        <div class="flex-1 text-left">
            <div class="font-medium text-sm">Thẻ quà tặng</div>
            @if ($appliedCount > 0)
                <div class="text-xs text-purple-600">
                    Đã áp {{ $appliedCount }} thẻ
                </div>
            @elseif ($myRedeemableCount > 0)
                <div class="text-xs text-gray-600">
                    Bạn có {{ $myRedeemableCount }} thẻ khả dụng — chạm để chọn
                </div>
            @else
                <div class="text-xs text-gray-400">Nhập mã hoặc xem thẻ của tôi</div>
            @endif
        </div>
        <i class="fi-rs-angle-small-right text-gray-400 text-xl"></i>
    </button>

    @if ($appliedCount > 0)
        <div class="flex flex-wrap gap-2 mt-2 px-3">
            @foreach ($appliedVoucherCodes as $code)
                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-purple-100 text-purple-700 text-xs">
                    <i class="fi-rs-credit-card"></i> {{ $code }}
                </span>
            @endforeach
            <button type="button" class="text-xs text-gray-500 hover:text-red-500 underline"
                @click="$dispatch('remove-voucher')">
                Bỏ tất cả
            </button>
        </div>
    @endif
</div>
