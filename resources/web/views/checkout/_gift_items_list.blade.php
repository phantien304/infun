{{--
    Shopee-style "Quà tặng kèm" section trong danh sách SP cart/checkout.
    Read-only — gift items KHÔNG có nút edit qty / remove. User muốn đổi quà
    phải qua modal pick.

    Input: $giftItems (array)
--}}
@if (! empty($giftItems))
    <div class="mt-3 mb-3 border rounded p-3 bg-pink-50">
        <div class="flex items-center gap-2 mb-3">
            <i class="fi-rs-gift text-pink-500 text-xl"></i>
            <h6 class="font-semibold text-pink-700 m-0">Quà tặng kèm</h6>
            <span class="text-xs text-gray-500">({{ count($giftItems) }} món)</span>
        </div>
        <div class="space-y-2">
            @foreach ($giftItems as $gift)
                <div class="flex items-center gap-3 bg-white rounded p-2">
                    <img src="{{ thumbnail((string) ($gift['image'] ?? ''), 60, 60) }}"
                        alt="{{ $gift['name'] }}"
                        class="w-12 h-12 object-cover rounded flex-shrink-0">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-900 truncate m-0">
                            {{ $gift['name'] }}
                        </p>
                        @if (filled($gift['variant_label']))
                            <p class="text-xs text-gray-500 m-0">{{ $gift['variant_label'] }}</p>
                        @endif
                        <p class="text-xs text-gray-400 m-0">
                            Từ chương trình: {{ $gift['gift_name'] }}
                        </p>
                    </div>
                    <div class="text-right flex-shrink-0">
                        <span class="inline-block px-2 py-1 bg-pink-100 text-pink-700 text-xs font-bold rounded">
                            Miễn phí
                        </span>
                        <p class="text-xs text-gray-500 mt-1 m-0">×{{ $gift['quantity'] }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endif
