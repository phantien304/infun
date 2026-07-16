<div x-data="voucherModal()" @open-voucher-modal.window="open()" @remove-voucher.window="removeAll()" x-show="show" x-cloak
    class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-0" style="display:none;">
    <div class="absolute inset-0 bg-black/50" @click="show = false"></div>

    <div class="relative bg-white w-full sm:max-w-2xl sm:rounded-lg sm:max-h-[85vh] flex flex-col shadow-2xl">
        <header class="flex items-center justify-between px-4 py-3 border-b">
            <h3 class="text-lg font-semibold">Thẻ quà tặng</h3>
            <button @click="show = false" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">×</button>
        </header>

        <div class="px-4 py-3 border-b bg-gray-50 flex gap-2">
            <input type="text" x-model="manualCode" placeholder="Nhập mã voucher"
                class="flex-1 form-control h-10 px-3 rounded border-gray-300">
            <button @click="applyManual()" :disabled="!manualCode.trim() || loading"
                class="btn btn-md bg-purple-500 text-white disabled:opacity-50">
                Áp dụng
            </button>
        </div>

        @if (!empty($appliedVoucherCodes))
            <div class="px-4 py-2 border-b">
                <div class="text-xs text-gray-500 mb-1">Đã áp dụng:</div>
                <div class="flex flex-wrap gap-2">
                    @foreach ($appliedVoucherCodes as $code)
                        <span
                            class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-purple-100 text-purple-700 text-xs">
                            {{ $code }}
                            <button type="button" @click="removeOne('{{ $code }}')"
                                class="ml-1 text-purple-500 hover:text-red-500">×</button>
                        </span>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="flex-1 overflow-y-auto p-3 space-y-3 bg-gray-50">
            @auth
                @forelse ($myVouchers as $voucher)
                    <div
                        class="bg-white rounded shadow-sm border overflow-hidden
                                @if (!$voucher->redeemable) opacity-60 @endif">
                        <div class="flex">
                            <div class="flex-shrink-0 w-24 bg-purple-100 flex items-center justify-center">
                                @if ($voucher->themeImage)
                                    <img src="{{ thumbnail($voucher->themeImage, 96, 96) }}" alt="theme"
                                        class="w-full h-full object-cover">
                                @else
                                    <i class="fi-rs-credit-card text-purple-500 text-3xl"></i>
                                @endif
                            </div>

                            <div class="flex-1 p-3 min-w-0">
                                <div class="flex items-start justify-between gap-2">
                                    <h4 class="font-bold text-sm text-gray-900 truncate">{{ $voucher->code }}</h4>
                                    <span
                                        class="text-xs px-2 py-0.5 rounded
                                                @if ($voucher->status === \App\Enums\VoucherStatus::Active->value) bg-green-100 text-green-700
                                                @else bg-gray-100 text-gray-500 @endif">
                                        {{ $voucher->statusLabel }}
                                    </span>
                                </div>
                                @if ($voucher->fromName)
                                    <p class="text-xs text-gray-500 mt-1">Từ: {{ $voucher->fromName }}</p>
                                @endif
                                @if ($voucher->message)
                                    <p class="text-xs text-gray-600 italic mt-1 line-clamp-2">"{{ $voucher->message }}"</p>
                                @endif
                                <div class="mt-2 flex items-baseline gap-2">
                                    <span class="text-lg font-bold text-purple-600">{{ $voucher->availableLabel }}</span>
                                    <span class="text-xs text-gray-400">/ {{ $voucher->amountLabel }}</span>
                                </div>
                                <p class="text-xs text-gray-400">{{ $voucher->dateExpireLabel }}</p>
                                @if (!$voucher->redeemable && $voucher->notRedeemableReason)
                                    <p class="text-xs text-red-500 mt-1">{{ $voucher->notRedeemableReason }}</p>
                                @endif
                            </div>

                            <div class="flex-shrink-0 flex items-center pr-3">
                                @if ($voucher->redeemable && !in_array($voucher->code, $appliedVoucherCodes, true))
                                    <button type="button" @click="applyByCode('{{ $voucher->code }}')"
                                        class="btn btn-sm bg-purple-500 text-white">
                                        Áp dụng
                                    </button>
                                @elseif (in_array($voucher->code, $appliedVoucherCodes, true))
                                    <span class="text-xs text-green-600 font-medium">✓ Đã áp</span>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-center text-gray-400 py-12">
                        <i class="fi-rs-credit-card text-4xl"></i>
                        <p class="mt-2">Bạn chưa có thẻ quà tặng nào</p>
                        <p class="text-xs mt-1">Có mã? Nhập ở ô phía trên</p>
                    </div>
                @endforelse
            @else
                <div class="text-center text-gray-400 py-12">
                    <i class="fi-rs-credit-card text-4xl"></i>
                    <p class="mt-2">Đăng nhập để xem thẻ quà tặng của bạn</p>
                    <p class="text-xs mt-1">Hoặc nhập mã thẻ ở ô phía trên</p>
                </div>
            @endauth
        </div>

        <footer class="px-4 py-3 border-t flex items-center justify-end bg-white">
            <button @click="show = false" type="button" class="btn btn-sm bg-purple-500 text-white">Xong</button>
        </footer>
    </div>
</div>

<script>
    function voucherModal() {
        return {
            show: false,
            loading: false,
            manualCode: '',

            open() {
                this.show = true;
            },

            async applyManual() {
                const code = this.manualCode.trim();
                if (!code) return;
                await this.apply(code);
                this.manualCode = '';
            },

            async applyByCode(code) {
                await this.apply(code);
            },

            async apply(code) {
                this.loading = true;
                try {
                    const res = await this.post('{{ route('checkout.vouchersApply') }}', {
                        code
                    });
                    if (res.success && res.data?.reload) {
                        window.location.reload();
                    } else {
                        alert(res.message || 'Mã không hợp lệ');
                    }
                } finally {
                    this.loading = false;
                }
            },

            async removeOne(code) {
                const res = await this.post('{{ route('checkout.vouchersRemove') }}', {
                    code
                });
                if (res.success && res.data?.reload) window.location.reload();
            },

            async removeAll() {
                const res = await this.post('{{ route('checkout.vouchersRemove') }}', {});
                if (res.success && res.data?.reload) window.location.reload();
            },

            async post(url, data) {
                const fd = new FormData();
                for (const [k, v] of Object.entries(data)) fd.append(k, v);
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
