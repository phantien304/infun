@if (!empty($rewardEnabled))
    <div x-data="rewardBox()" class="py-3 my-3 border-top border-bottom">
        <div class="w-full flex items-center gap-3 px-3 py-2">
            <i class="fi-rs-star text-yellow-500 text-2xl"></i>
            <div class="flex-1 text-left">
                <div class="font-medium text-sm">Điểm thưởng</div>
                @guest
                    <div class="text-xs text-gray-400">Đăng nhập để dùng điểm thưởng</div>
                @else
                    @if ($rewardApplied > 0)
                        <div class="text-xs text-yellow-600">
                            Đang dùng {{ number_format($rewardApplied, 0, '', ',') }} điểm
                            (còn {{ number_format($rewardBalance, 0, '', ',') }} điểm)
                        </div>
                    @elseif ($rewardBalance > 0)
                        <div class="text-xs text-gray-600">
                            Bạn có {{ number_format($rewardBalance, 0, '', ',') }} điểm khả dụng
                        </div>
                    @else
                        <div class="text-xs text-gray-400">Chưa có điểm khả dụng</div>
                    @endif
                @endguest
            </div>

            @auth
                @if ($rewardApplied > 0)
                    <button type="button" @click="remove()" :disabled="loading"
                        class="text-xs text-gray-500 hover:text-red-500 underline disabled:opacity-50">
                        Bỏ áp dụng
                    </button>
                @endif
            @endauth
        </div>

        @auth
            @if ($rewardApplied <= 0 && $rewardBalance > 0)
                <div class="flex gap-2 mt-2 px-3">
                    <input type="number" x-model.number="points" min="1" max="{{ $rewardBalance }}"
                        placeholder="Số điểm muốn dùng" class="flex-1 form-control h-10 px-3 rounded border-gray-300">
                    <button type="button" @click="apply()" :disabled="!points || points < 1 || loading"
                        class="btn btn-md bg-yellow-500 text-white disabled:opacity-50">
                        Dùng điểm
                    </button>
                    <button type="button" @click="points = {{ $rewardBalance }}"
                        class="btn btn-md border text-xs text-gray-600">
                        Tối đa
                    </button>
                </div>
                <p x-show="error" x-text="error" class="text-xs text-red-500 mt-1 px-3" x-cloak></p>
            @endif
        @endauth
    </div>

    <script>
        function rewardBox() {
            return {
                loading: false,
                points: null,
                error: '',

                async apply() {
                    if (!this.points || this.points < 1) return;
                    this.loading = true;
                    this.error = '';
                    try {
                        const res = await this.post('{{ route('checkout.rewardApply') }}', {
                            points: this.points
                        });
                        if (res.success && res.data?.reload) {
                            window.location.reload();
                        } else {
                            this.error = res.message || 'Không áp dụng được điểm';
                        }
                    } finally {
                        this.loading = false;
                    }
                },

                async remove() {
                    this.loading = true;
                    try {
                        const res = await this.post('{{ route('checkout.rewardRemove') }}', {});
                        if (res.success && res.data?.reload) window.location.reload();
                    } finally {
                        this.loading = false;
                    }
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
@endif
