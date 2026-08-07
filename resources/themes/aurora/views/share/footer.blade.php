<footer class="bg-white border-t border-line mt-8">
    <div class="mx-auto max-w-[1320px] px-5 py-12 grid grid-cols-2 lg:grid-cols-5 gap-8 text-[14px]">

        <div class="col-span-2 lg:col-span-1">
            <a href="{{ route('home') }}" title="{{ getConfigDb('config_name') }}" class="inline-block mb-4">
                <img src="{{ asset(getConfigDb('config_logo')) }}" alt="{{ getConfigDb('config_name') }}"
                    class="h-10 w-auto object-contain">
            </a>
            @if (getConfigDb('config_telephone'))
                <p class="text-ink-2 leading-relaxed">
                    Hotline
                    <a href="tel:{{ preg_replace('/[^\d+]/', '', getConfigDb('config_telephone')) }}"
                        class="font-semibold text-ink hover:text-brand">{{ getConfigDb('config_telephone') }}</a>
                </p>
            @endif
            @if (getConfigDb('config_opening_time'))
                <p class="text-ink-3 text-[13px] mt-1">{{ getConfigDb('config_opening_time') }}</p>
            @endif

            <div class="flex gap-2 mt-5">
                @foreach ([['config_facebook', 'icon-facebook-white.svg', 'Facebook'], ['config_instagram', 'icon-instagram-white.svg', 'Instagram'], ['config_tiktok', 'icons8-tiktok.svg', 'TikTok'], ['config_threads', 'icons8-threads.svg', 'Threads']] as [$key, $icon, $label])
                    @if (getConfigDb($key))
                        <a href="{{ getConfigDb($key) }}" title="{{ $label }}" target="_blank" rel="noopener"
                            class="w-9 h-9 rounded-xl bg-ink grid place-items-center hover:bg-brand transition">
                            <img src="/web/images/theme/icons/{{ $icon }}" alt="{{ $label }}" class="w-4 h-4">
                        </a>
                    @endif
                @endforeach
            </div>
        </div>

        @foreach (['config_footer1', 'config_footer2', 'config_footer3', 'config_footer4'] as $block)
            @if (getConfigDb($block))
                <div class="aurora-footer-col text-ink-2 leading-relaxed">
                    {!! getConfigDb($block) !!}
                </div>
            @endif
        @endforeach
    </div>

    <div class="border-t border-line">
        <div
            class="mx-auto max-w-[1320px] px-5 min-h-14 py-3 flex flex-wrap items-center justify-between gap-3 text-[13px] text-ink-3">
            <span>© {{ date('Y') }} {{ getConfigDb('config_name') }}</span>
            <div class="flex items-center gap-4">
                <a href="{{ route('contact.index') }}" class="hover:text-ink">Liên hệ</a>
                <a href="{{ route('order.search') }}" class="hover:text-ink">Tra cứu đơn hàng</a>
            </div>
        </div>
    </div>
</footer>
