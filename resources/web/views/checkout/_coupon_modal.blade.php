@php
    $couponContext = $couponContext ?? 'cart';
@endphp
<div x-data="couponModal()" @open-coupon-modal.window="open()" @remove-coupon.window="remove()" x-show="show" x-cloak
    class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-0" style="display:none;">
    <div class="absolute inset-0 bg-black/50" @click="show = false"></div>

    <div class="relative bg-white w-full sm:max-w-2xl sm:rounded-lg sm:max-h-[85vh] flex flex-col shadow-2xl">
        <header class="flex items-center justify-between px-4 py-3 border-b">
            <h3 class="text-lg font-semibold">Chọn Voucher</h3>
            <button @click="show = false" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">×</button>
        </header>
        <div class="px-4 py-3 border-b bg-gray-50 flex gap-2">
            <input type="text" x-model="manualCode" placeholder="Nhập mã voucher"
                class="flex-1 form-control h-10 px-3 rounded border-gray-300">
            <button @click="applyManual()" :disabled="!manualCode.trim() || loading"
                class="btn btn-md bg-brand text-white disabled:opacity-50">
                Áp dụng
            </button>
        </div>
        <div class="flex border-b">
            <button @click="tab='all'"
                :class="tab === 'all' ? 'border-b-2 border-brand text-brand' : 'text-gray-500'"
                class="flex-1 py-3 font-medium text-sm transition">
                Mã của Shop
            </button>
            <button @click="tab='mine'"
                :class="tab === 'mine' ? 'border-b-2 border-brand text-brand' : 'text-gray-500'"
                class="flex-1 py-3 font-medium text-sm transition">
                Mã của tôi
            </button>
        </div>
        @php
            $typePercent = (int) getCoreConfig('coupon.type.percent');
            $typeFixed = (int) getCoreConfig('coupon.type.fixed');
            $typeFreeship = (int) getCoreConfig('coupon.type.freeship');
            $discountCoupons = $coupons
                ->filter(fn($c) => in_array($c->type, [$typePercent, $typeFixed], true))
                ->values();
            $shippingCoupons = $coupons->filter(fn($c) => $c->type === $typeFreeship)->values();
            $discountSavedCount = $discountCoupons->where('savedByUser', true)->count();
            $shippingSavedCount = $shippingCoupons->where('savedByUser', true)->count();
        @endphp
        <div class="flex-1 overflow-y-auto p-3 bg-gray-50">
            <div class="mb-4" x-show="tab === 'all' || {{ $discountSavedCount > 0 ? 'true' : 'false' }}">
                <div class="flex items-center gap-2 mb-2 px-1">
                    <i class="fi-rs-ticket text-orange-500"></i>
                    <h4 class="text-sm font-semibold text-gray-700">Mã giảm giá của Shop</h4>
                    <span class="text-xs text-gray-400">({{ $discountCoupons->count() }})</span>
                </div>
                <div class="space-y-3">
                    @forelse ($discountCoupons as $coupon)
                        @include('web::checkout._coupon_card', ['coupon' => $coupon])
                    @empty
                        <div class="text-center text-gray-400 py-6 bg-white rounded">
                            <p class="text-sm">Chưa có mã giảm giá nào</p>
                        </div>
                    @endforelse
                </div>
            </div>
            <div class="mb-4" x-show="tab === 'all' || {{ $shippingSavedCount > 0 ? 'true' : 'false' }}">
                <div class="flex items-center gap-2 mb-2 px-1">
                    <i class="fi-rs-truck text-green-500"></i>
                    <h4 class="text-sm font-semibold text-gray-700">Mã miễn phí vận chuyển</h4>
                    <span class="text-xs text-gray-400">({{ $shippingCoupons->count() }})</span>
                </div>
                <div class="space-y-3">
                    @forelse ($shippingCoupons as $coupon)
                        @include('web::checkout._coupon_card', ['coupon' => $coupon])
                    @empty
                        <div class="text-center text-gray-400 py-6 bg-white rounded">
                            <p class="text-sm">Chưa có mã miễn phí ship nào</p>
                        </div>
                    @endforelse
                </div>
            </div>
            @if ($discountCoupons->isEmpty() && $shippingCoupons->isEmpty())
                <div class="text-center text-gray-400 py-12">
                    <i class="fi-rs-ticket text-4xl"></i>
                    <p class="mt-2">Chưa có voucher nào</p>
                </div>
            @endif
            <div x-show="tab === 'mine' && {{ $discountSavedCount + $shippingSavedCount === 0 ? 'true' : 'false' }}"
                class="text-center text-gray-400 py-12">
                <i class="fi-rs-bookmark text-4xl"></i>
                <p class="mt-2">Bạn chưa lưu voucher nào</p>
                <p class="text-xs mt-1">Bấm "+ Lưu" trên voucher ở tab "Mã của Shop" để lưu lại</p>
            </div>
        </div>
        <footer class="px-4 py-3 border-t flex items-center justify-between bg-white">
            <span class="text-sm text-gray-600">
                Đã chọn <b x-text="selected.length"></b> voucher
            </span>
            <div class="flex gap-2">
                <button @click="show = false" type="button" class="btn btn-sm bg-gray-100 text-gray-700">Trở
                    lại</button>
                <button @click="apply()" type="button" :disabled="!selected.length || loading"
                    class="btn btn-sm bg-brand text-white disabled:opacity-50">
                    OK
                </button>
            </div>
        </footer>
    </div>
</div>

<script>
    function couponModal() {
        return {
            show: false,
            tab: 'all',
            manualCode: '',
            loading: false,
            selected: @json($appliedCouponCodes ?? []),
            context: @json($couponContext),

            open() {
                this.show = true;
            },

            toggle(code) {
                const i = this.selected.indexOf(code);
                if (i >= 0) this.selected.splice(i, 1);
                else this.selected.push(code);
            },

            async apply() {
                if (!this.selected.length) return;
                this.loading = true;
                try {
                    const res = await this.post('{{ route('checkout.couponsApply') }}', {
                        codes: this.selected,
                        context: this.context,
                        carrier_code: this.currentCarrierCode(),
                    });
                    if (res.success && res.data) {
                        this.swapDom(res.data);
                        this.show = false;
                    } else {
                        alert(res.message || 'Lỗi áp dụng voucher');
                    }
                } finally {
                    this.loading = false;
                }
            },

            async applyManual() {
                const code = this.manualCode.trim();
                if (!code) return;
                this.loading = true;
                try {
                    const res = await this.post('{{ route('checkout.couponsApply') }}', {
                        code,
                        context: this.context,
                        carrier_code: this.currentCarrierCode(),
                    });
                    if (res.success && res.data) {
                        this.manualCode = '';
                        this.swapDom(res.data);
                        this.show = false;
                    } else {
                        alert(res.message || 'Mã không hợp lệ');
                    }
                } finally {
                    this.loading = false;
                }
            },

            async save(couponId, evt) {
                evt.preventDefault();
                const btn = evt.currentTarget;
                btn.disabled = true;
                const res = await this.post('{{ route('checkout.couponsSave') }}', {
                    coupon_id: couponId
                });
                btn.disabled = false;
                if (res.success) {
                    btn.innerHTML = '<i class="fi-rs-bookmark"></i> Đã lưu';
                    btn.classList.remove('text-brand', 'hover:underline');
                    btn.classList.add('text-gray-400', 'hover:text-red-500');
                } else {
                    alert(res.message || 'Lỗi lưu voucher');
                }
            },

            async unsave(couponId, evt) {
                evt.preventDefault();
                const btn = evt.currentTarget;
                btn.disabled = true;
                const res = await this.post('{{ route('checkout.couponsUnsave') }}', {
                    coupon_id: couponId
                });
                btn.disabled = false;
                if (res.success) {
                    btn.innerHTML = '+ Lưu';
                    btn.classList.remove('text-gray-400', 'hover:text-red-500');
                    btn.classList.add('text-brand', 'hover:underline');
                }
            },

            async remove() {
                const res = await this.post('{{ route('checkout.couponsRemove') }}', {
                    context: this.context,
                    carrier_code: this.currentCarrierCode(),
                });
                if (res.success && res.data) {
                    this.selected = [];
                    this.swapDom(res.data);
                }
            },

            currentCarrierCode() {
                return document.querySelector('input[name="carrier_code"]:checked')?.value || '';
            },

            swapDom(data) {
                if (data.total_data_html) {
                    const tbody = document.querySelector('#total-data');
                    if (tbody) tbody.innerHTML = data.total_data_html;
                }
                if (data.promo_row_html) {
                    const container = document.querySelector('#coupon-promo-row-container');
                    if (container) container.innerHTML = data.promo_row_html;
                }
                if (Array.isArray(data.applied_codes)) {
                    this.selected = data.applied_codes;
                }
            },

            async post(url, data) {
                const fd = new FormData();
                for (const [k, v] of Object.entries(data)) {
                    if (Array.isArray(v)) v.forEach(item => fd.append(k + '[]', item));
                    else fd.append(k, v);
                }
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
    .voucher-card {
        transition: transform 0.15s;
    }

    .voucher-card:hover {
        transform: translateY(-2px);
    }

    [x-cloak] {
        display: none !important;
    }
</style>
