@php
    $appliedCount = is_array($appliedCouponCodes ?? null) ? count($appliedCouponCodes) : 0;
    $applicableCount = isset($coupons) ? $coupons->where('applicableToCart', true)->count() : 0;
@endphp

<div x-data class="py-3 my-3 border-top border-bottom">
    <button type="button" class="w-full flex items-center gap-3 px-3 py-2 hover:bg-gray-50 transition rounded"
        @click="$dispatch('open-coupon-modal')">
        <i class="fi-rs-ticket text-brand text-2xl"></i>
        <div class="flex-1 text-left">
            <div class="font-medium text-sm">Mã giảm giá</div>
            @if ($appliedCount > 0)
                <div class="text-xs text-brand">
                    Đã áp {{ $appliedCount }} mã
                </div>
            @elseif ($applicableCount > 0)
                <div class="text-xs text-gray-600">
                    Có {{ $applicableCount }} mã khả dụng — chạm để chọn
                </div>
            @else
                <div class="text-xs text-gray-400">Chọn hoặc nhập mã</div>
            @endif
        </div>
        <i class="fi-rs-angle-small-right text-gray-400 text-xl"></i>
    </button>

    {{-- Hiển thị chip mã đã áp + nút bỏ --}}
    @if ($appliedCount > 0)
        <div class="flex flex-wrap gap-2 mt-2 px-3">
            @foreach ($appliedCouponCodes as $code)
                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-brand/10 text-brand text-xs">
                    <i class="fi-rs-ticket"></i> {{ $code }}
                </span>
            @endforeach
            <button type="button" class="text-xs text-gray-500 hover:text-red-500 underline"
                @click="$dispatch('remove-coupon')">
                Bỏ tất cả
            </button>
        </div>
    @endif
</div>
