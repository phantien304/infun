<div class="header-action-icon-2 relative" x-data="{ open: false }" @mouseenter="open = true" @mouseleave="open = false">
    <a href="#" class="block" title="Ngôn ngữ" @click.prevent>
        <i class="fi fi-rs-globe"></i>
        <span class="lable ml-0 uppercase">{{ $currentLocale }}</span>
    </a>
    <div class="absolute right-0 top-full z-50 w-48 rounded-lg border border-gray-100 bg-white pb-2 pt-3 shadow-lg"
        x-show="open" x-cloak style="display:none" x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0">
        <ul class="m-0 list-none p-0">
            @foreach ($languages as $language)
                @php $languageCode = strtolower((string) ($language->code ?? '')); @endphp
                <li>
                    <a href="{{ route('locale.language', $language->code) }}" data-lang="{{ $language->code }}"
                        class="flex items-center gap-2 whitespace-nowrap px-4 py-2 hover:bg-gray-50 {{ $languageCode === $currentLocale ? 'font-semibold text-brand' : '' }}">
                        @if (!empty($language->image))
                            <img src="{{ $language->image }}" alt="" width="18" height="12" class="flex-shrink-0">
                        @endif
                        <span>{{ $language->title ?? ($language->name ?? strtoupper((string) ($language->code ?? ''))) }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    </div>
</div>

<div class="header-action-icon-2 relative" x-data="{ open: false }" @mouseenter="open = true" @mouseleave="open = false">
    <a href="#" class="block" title="Tiền tệ" @click.prevent>
        <i class="fi fi-rs-coins"></i>
        <span class="lable ml-0">{{ $currencySymbol }} {{ $currentCurrency->code }}</span>
    </a>
    <div class="absolute right-0 top-full z-50 w-52 rounded-lg border border-gray-100 bg-white pb-2 pt-3 shadow-lg"
        x-show="open" x-cloak style="display:none" x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0">
        <ul class="m-0 list-none p-0">
            @foreach ($currencies as $currency)
                @php $symbol = trim((string) ($currency->symbol_right ?? '')) ?: trim((string) ($currency->symbol_left ?? '')); @endphp
                <li>
                    <a href="{{ route('locale.currency', $currency->code) }}" data-currency="{{ $currency->code }}"
                        class="flex items-center gap-2 whitespace-nowrap px-4 py-2 hover:bg-gray-50 {{ strtoupper((string) $currency->code) === strtoupper((string) $currentCurrency->code) ? 'font-semibold text-brand' : '' }}">
                        <span class="inline-block w-5 flex-shrink-0 text-center">{{ $symbol }}</span>
                        <span class="flex-shrink-0">{{ $currency->code }}</span>
                        <span class="ml-auto truncate text-sm text-gray-400">{{ $currency->title }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    </div>
</div>
