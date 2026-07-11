<div class="dashboard-menu">
    <ul class="nav flex-column" role="tablist">
        <li class="nav-item">
            <a href="{{ route('account.index') }}" title="Thông tin tài khoản"
                class="nav-link @if (request()->routeIs('account.index')) active @endif">
                <i class="fi-rs-settings-sliders mr-10"></i>Chung
            </a>
        </li>
        <li class="nav-item">
            <a href="{{ route('account.edit') }}" title="Thông tin tài khoản"
                class="nav-link @if (request()->routeIs('account.edit')) active @endif">
                <i class="fi-rs-user mr-10"></i>Thông tin tài khoản
            </a>
        </li>
        <li class="nav-item">
            <a href="{{ route('account.password') }}" title="Mật khẩu"
                class="nav-link @if (request()->routeIs('account.password')) active @endif">
                <i class="fas fa-key mr-10"></i>Mật khẩu
            </a>
        </li>
        <li class="nav-item">
            <a href="{{ route('account.address') }}" title="Địa chỉ"
                class="nav-link @if (request()->routeIs('account.address', 'account.address.create', 'account.address.edit')) active @endif">
                <i class="fi-rs-marker mr-10"></i>Địa chỉ
            </a>
        </li>
        <li class="nav-item">
            <a href="{{ route('account.wishlist') }}" title="Sản phẩm yêu thích"
                class="nav-link @if (request()->routeIs('account.wishlist')) active @endif">
                <i class="far fa-star mr-10"></i>Sản phẩm yêu thích
            </a>
        </li>
        <li class="nav-item">
            <a href="{{ route('checkout.cart') }}" title="Giỏ hàng"
                class="nav-link @if (request()->routeIs('checkout.cart')) active @endif">
                <i class="fas fa-cart-arrow-down mr-10"></i>Giỏ hàng
            </a>
        </li>
        <li class="nav-item">
            <a href="{{ route('account.orders') }}" title="Lịch sử mua hàng"
                class="nav-link @if (request()->routeIs('account.orders', 'account.detailOrder')) active @endif">
                <i class="far fa-address-book mr-10"></i>Lịch sử mua hàng
            </a>
        </li>
        <li class="nav-item">
            <a href="{{ route('account.rewards') }}" title="Điểm thưởng"
                class="nav-link @if (request()->routeIs('account.rewards')) active @endif">
                <i class="far fa-star mr-10"></i>Điểm thưởng
            </a>
        </li>
        <li class="nav-item">
            <a href="{{ route('account.affiliate') }}" title="Tiếp thị liên kết"
                class="nav-link @if (request()->routeIs('account.affiliate', 'account.affiliate.links')) active @endif">
                <i class="fas fa-share-alt mr-10"></i>Tiếp thị liên kết
            </a>
        </li>
        <li class="nav-item">
            <a href="{{ route('account.newsletter') }}" title="Đăng ký nhận tin khuyến mãi"
                class="nav-link @if (request()->routeIs('account.newsletter')) active @endif">
                <i class="fas fa-newspaper mr-10"></i>Đăng ký nhận tin khuyến mãi
            </a>
        </li>
        <li class="nav-item">
            <a href="{{ route('account.logout') }}" title="Đăng xuất" class="nav-link">
                <i class="fi-rs-sign-out mr-10"></i>Đăng xuất
            </a>
        </li>
    </ul>
</div>
