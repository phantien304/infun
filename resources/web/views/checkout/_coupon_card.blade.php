<label class="block voucher-card relative" x-show="tab === 'all' || {{ $coupon->savedByUser ? 'true' : 'false' }}">
    <div
        class="flex bg-white rounded overflow-hidden shadow-sm border
                @if (!$coupon->applicableToCart) opacity-50 @endif">
        {{-- Left ribbon — type icon + color --}}
        <div
            class="flex-shrink-0 w-20 flex items-center justify-center text-white text-3xl font-bold
                    @if ($coupon->type === \App\Enums\CouponType::Percent->value) bg-orange-500
                    @elseif ($coupon->type === \App\Enums\CouponType::Fixed->value) bg-red-500
                    @else bg-green-500 @endif">
            {{ $coupon->typeIcon }}
        </div>

        {{-- Body --}}
        <div class="flex-1 p-3 min-w-0">
            @if ($coupon->badge)
                <span class="inline-block px-2 py-0.5 mb-1 text-xs font-bold bg-red-100 text-red-700 rounded">
                    {{ $coupon->badge }}
                </span>
            @endif
            <h4 class="font-bold text-sm text-gray-900 truncate">{{ $coupon->discountLabel }}</h4>
            <p class="text-xs text-gray-600 mt-1">{{ $coupon->minSubtotalLabel }}</p>
            <p class="text-xs text-gray-500">{{ $coupon->applyScopeLabel }}</p>
            <p class="text-xs text-gray-400 mt-1">{{ $coupon->expiresAtLabel }}</p>
            @if (!$coupon->applicableToCart && $coupon->notApplicableReason)
                <p class="text-xs text-red-500 mt-1">{{ $coupon->notApplicableReason }}</p>
            @endif
        </div>

        {{-- Action --}}
        <div class="flex-shrink-0 flex flex-col items-end justify-center pr-3 gap-2">
            @if ($coupon->applicableToCart)
                <input type="checkbox" :checked="selected.includes('{{ $coupon->code }}')"
                    @change="toggle('{{ $coupon->code }}')" class="w-5 h-5 text-brand">
            @endif
            @auth
                @if ($coupon->savedByUser)
                    <button type="button" @click="unsave({{ $coupon->id }}, $event)"
                        class="text-xs text-gray-400 hover:text-red-500">
                        <i class="fi-rs-bookmark"></i> Đã lưu
                    </button>
                @else
                    <button type="button" @click="save({{ $coupon->id }}, $event)"
                        class="text-xs text-brand hover:underline">
                        + Lưu
                    </button>
                @endif
            @endauth
        </div>
    </div>
</label>
