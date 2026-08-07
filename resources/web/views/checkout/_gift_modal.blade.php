<div x-data="giftModal()" @open-gift-modal.window="open()" @remove-gift.window="remove()" x-show="show" x-cloak
    class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-0" style="display:none;">
    <div class="absolute inset-0 bg-black/50" @click="show = false"></div>

    <div class="relative bg-white w-full sm:max-w-2xl sm:rounded-lg sm:max-h-[85vh] flex flex-col shadow-2xl">
        <header class="flex items-center justify-between px-4 py-3 border-b">
            <h3 class="text-lg font-semibold">{{ trans('messages.checkout.gift.modal_title') }}</h3>
            <button @click="show = false" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">×</button>
        </header>

        <div class="flex-1 overflow-y-auto p-3 space-y-4 bg-gray-50">
            @forelse ($gifts as $gift)
                <div class="bg-white rounded shadow-sm border @if (!$gift->availableToCart) opacity-60 @endif">
                    <div class="p-3 border-b">
                        <div class="flex items-start gap-3">
                            <i class="fi-rs-gift text-pink-500 text-2xl flex-shrink-0 mt-1"></i>
                            <div class="flex-1 min-w-0">
                                @if ($gift->badge)
                                    <span
                                        class="inline-block px-2 py-0.5 mb-1 text-xs font-bold bg-pink-100 text-pink-700 rounded">
                                        {{ $gift->badge }}
                                    </span>
                                @endif
                                <h4 class="font-bold text-sm text-gray-900">{{ $gift->name }}</h4>
                                @if ($gift->description)
                                    <p class="text-xs text-gray-600 mt-1">{{ $gift->description }}</p>
                                @endif
                                <div class="flex flex-wrap gap-x-3 gap-y-1 mt-2 text-xs text-gray-500">
                                    <span><i class="fi-rs-shopping-cart"></i> {{ $gift->minSubtotalLabel }}</span>
                                    <span><i class="fi-rs-time"></i> {{ $gift->expiresAtLabel }}</span>
                                    <span class="text-pink-600 font-medium">{{ $gift->pickTypeLabel }}</span>
                                </div>
                                @if (!$gift->availableToCart && $gift->notAvailableReason)
                                    <p class="text-xs text-red-500 mt-2">{{ $gift->notAvailableReason }}</p>
                                @endif
                            </div>
                        </div>
                    </div>

                    @if ($gift->availableToCart && $gift->items->count())
                        @php
                            $pickAuto = \App\Enums\GiftPickType::Auto->value;
                            $pick1OfN = \App\Enums\GiftPickType::PickOneOfN->value;
                            $pickUpToN = \App\Enums\GiftPickType::PickUpToN->value;
                        @endphp
                        <div class="p-3">
                            <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
                                @foreach ($gift->items as $item)
                                    @php
                                        $itemImage = thumbnail((string) ($item->image ?? ''), 80, 80);
                                        $isPicked = in_array($item->id, $gift->pickedItemIds, true);
                                    @endphp
                                    <label class="block gift-item-card relative cursor-pointer">
                                        <div
                                            class="border rounded p-2 transition
                                                    @if ($isPicked) border-pink-500 bg-pink-50 @else border-gray-200 hover:border-pink-300 @endif">
                                            @if ($gift->pickType === $pickAuto)
                                                <input type="checkbox" name="picks[{{ $gift->id }}][]"
                                                    value="{{ $item->id }}" checked disabled
                                                    class="absolute top-1 right-1 w-4 h-4">
                                            @elseif ($gift->pickType === $pick1OfN)
                                                <input type="radio" name="picks[{{ $gift->id }}]"
                                                    value="{{ $item->id }}" @checked($isPicked)
                                                    @change="togglePick({{ $gift->id }}, {{ $item->id }}, 'radio')"
                                                    class="absolute top-1 right-1 w-4 h-4">
                                            @else
                                                <input type="checkbox" name="picks[{{ $gift->id }}][]"
                                                    value="{{ $item->id }}" @checked($isPicked)
                                                    @change="togglePick({{ $gift->id }}, {{ $item->id }}, 'checkbox', {{ $gift->pickLimit ?? 'null' }})"
                                                    class="absolute top-1 right-1 w-4 h-4">
                                            @endif
                                            <img src="{{ $itemImage }}" alt="{{ $item->productName }}"
                                                class="w-full aspect-square object-cover rounded">
                                            <p class="text-xs font-medium mt-2 line-clamp-2">{{ $item->productName }}
                                            </p>
                                            @if ($item->variantName)
                                                <p class="text-xs text-gray-500">{{ $item->variantName }}</p>
                                            @endif
                                            <p class="text-xs text-pink-600 font-bold mt-1">×{{ $item->quantity }}</p>
                                        </div>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            @empty
                <div class="text-center text-gray-400 py-12">
                    <i class="fi-rs-gift text-4xl"></i>
                    <p class="mt-2">{{ trans('messages.checkout.gift.modal_empty') }}</p>
                </div>
            @endforelse
        </div>

        <footer class="px-4 py-3 border-t flex items-center justify-between bg-white">
            <span class="text-sm text-gray-600">
                {{ trans('messages.checkout.gift.modal_picked_prefix') }} <b x-text="totalPicked()"></b>
                {{ trans('messages.checkout.gift.modal_picked_suffix') }}
            </span>
            <div class="flex gap-2">
                <button @click="show = false" type="button" class="btn btn-sm bg-gray-100 text-gray-700">Trở
                    lại</button>
                <button @click="apply()" type="button" :disabled="loading"
                    class="btn btn-sm bg-pink-500 text-white disabled:opacity-50">
                    OK
                </button>
            </div>
        </footer>
    </div>
</div>

<script>
    function giftModal() {
        return {
            show: false,
            loading: false,
            picks: @json($gifts->mapWithKeys(fn($g) => [(int) $g->id => array_map('intval', $g->pickedItemIds)])->all()),

            open() {
                this.show = true;
            },

            togglePick(giftId, itemId, mode, limit = null) {
                const key = String(giftId);
                if (!Array.isArray(this.picks[key])) this.picks[key] = [];

                if (mode === 'radio') {
                    this.picks[key] = [itemId];
                    return;
                }
                // checkbox
                const i = this.picks[key].indexOf(itemId);
                if (i >= 0) {
                    this.picks[key].splice(i, 1);
                } else {
                    if (limit !== null && this.picks[key].length >= limit) {
                        alert(@json(trans('messages.checkout.gift.js_max_per_gift')).replace('%s', limit));
                        event.target.checked = false;
                        return;
                    }
                    this.picks[key].push(itemId);
                }
            },

            totalPicked() {
                let n = 0;
                for (const k in this.picks) {
                    n += (this.picks[k] || []).length;
                }
                return n;
            },

            async apply() {
                this.loading = true;
                try {
                    const res = await this.post('{{ route('checkout.giftsPick') }}', {
                        picks: this.picks
                    });
                    if (res.success && res.data?.reload) {
                        window.location.reload();
                    } else {
                        alert(res.message || @json(trans('messages.checkout.gift.js_pick_error')));
                    }
                } finally {
                    this.loading = false;
                }
            },

            async remove() {
                const res = await this.post('{{ route('checkout.giftsRemove') }}', {});
                if (res.success && res.data?.reload) {
                    window.location.reload();
                }
            },

            async post(url, data) {
                const fd = new FormData();
                const append = (k, v) => {
                    if (Array.isArray(v)) {
                        v.forEach(item => fd.append(k + '[]', item));
                    } else if (v && typeof v === 'object') {
                        for (const ck in v) append(k + '[' + ck + ']', v[ck]);
                    } else {
                        fd.append(k, v);
                    }
                };
                for (const [k, v] of Object.entries(data)) append(k, v);
                const csrf = document.querySelector('meta[name="csrf-token"]')?.content ||
                    document.querySelector('input[name="_token"]')?.value;
                if (csrf) fd.append('_token', csrf);

                const r = await fetch(url, {
                    method: 'POST',
                    body: fd,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    },
                    credentials: 'same-origin',
                });
                return r.json();
            },
        };
    }
</script>

<style>
    .gift-item-card .line-clamp-2 {
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
</style>
