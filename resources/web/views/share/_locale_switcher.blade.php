<div class="header-action-icon-2 relative" x-data="{ open: false }" @mouseenter="open = true" @mouseleave="open = false">
    <a href="#" class="block" title="Ngôn ngữ" @click.prevent>
        <i class="fi fi-rs-globe"></i>
        <span class="lable ml-0 uppercase">{{ $currentLocale }}</span>
    </a>
    <div class="cart-dropdown-wrap account-dropdown absolute right-0 top-full z-50 mt-1 w-44 bg-white shadow-lg rounded-md py-2"
        x-show="open" x-cloak x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0">
        <ul class="list-none m-0 p-0">
            @foreach ($languages as $language)
                @php $languageCode = strtolower((string) ($language->code ?? '')); @endphp
                <li>
                    <a href="{{ route('locale.language', $language->code) }}" data-lang="{{ $language->code }}"
                        class="flex items-center gap-2 px-4 py-2 hover:bg-gray-50 {{ $languageCode === $currentLocale ? 'font-semibold text-brand' : '' }}">
                        @if (!empty($language->image))
                            <img src="{{ $language->image }}" alt="" width="18" height="12">
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
    <div class="cart-dropdown-wrap account-dropdown absolute right-0 top-full z-50 mt-1 w-48 bg-white shadow-lg rounded-md py-2"
        x-show="open" x-cloak x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0">
        <ul class="list-none m-0 p-0">
            @foreach ($currencies as $currency)
                @php $symbol = trim((string) ($currency->symbol_right ?? '')) ?: trim((string) ($currency->symbol_left ?? '')); @endphp
                <li>
                    <a href="{{ route('locale.currency', $currency->code) }}" data-currency="{{ $currency->code }}"
                        class="flex items-center gap-2 px-4 py-2 hover:bg-gray-50 {{ strtoupper((string) $currency->code) === strtoupper((string) $currentCurrency->code) ? 'font-semibold text-brand' : '' }}">
                        <span class="inline-block w-5 text-center">{{ $symbol }}</span>
                        <span>{{ $currency->code }}</span>
                        <span class="text-gray-400 text-sm ml-auto">{{ $currency->title }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    </div>
</div>
