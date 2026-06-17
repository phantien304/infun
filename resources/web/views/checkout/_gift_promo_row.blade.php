{{--
    Gift promo row — Shopee "Quà tặng". Click → mở modal _gift_modal.
    Input: $gifts (Collection<GiftDTO>)
--}}
@php
    $availableCount = isset($gifts) ? $gifts->where('availableToCart', true)->count() : 0;
    $pickedTotal = 0;
    if (isset($gifts)) {
        foreach ($gifts as $gift) {
            if ($gift->availableToCart) {
                $pickedTotal += count($gift->pickedItemIds);
            }
        }
    }
@endphp

<div x-data class="py-3 my-3 border-top border-bottom">
    <button type="button" class="w-full flex items-center gap-3 px-3 py-2 hover:bg-gray-50 transition rounded"
        @click="$dispatch('open-gift-modal')">
        <i class="fi-rs-gift text-pink-500 text-2xl"></i>
        <div class="flex-1 text-left">
            <div class="font-medium text-sm">Quà tặng</div>
            @if ($pickedTotal > 0)
                <div class="text-xs text-pink-600">
                    Đã chọn {{ $pickedTotal }} quà
                </div>
            @elseif ($availableCount > 0)
                <div class="text-xs text-gray-600">
                    Có {{ $availableCount }} quà có thể nhận — chạm để chọn
                </div>
            @else
                <div class="text-xs text-gray-400">Chưa đủ điều kiện nhận quà</div>
            @endif
        </div>
        <i class="fi-rs-angle-small-right text-gray-400 text-xl"></i>
    </button>

    @if ($pickedTotal > 0)
        <div class="flex flex-wrap gap-2 mt-2 px-3">
            <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-pink-100 text-pink-700 text-xs">
                <i class="fi-rs-gift"></i> {{ $pickedTotal }} quà
            </span>
            <button type="button" class="text-xs text-gray-500 hover:text-red-500 underline"
                @click="$dispatch('remove-gift')">
                Bỏ tất cả
            </button>
        </div>
    @endif
</div>
