/**
 * menuConfig.js — Tách data của menuLeft ra file riêng
 * -----------------------------------------------------------
 * Vue 2: data trong menu_left.vue trộn với UI.
 * React: tách config sang file riêng cho dễ chỉnh.
 *
 * Lưu ý: trường `name` tham chiếu tới route name của vue-router cũ.
 * React Router v6 KHÔNG có "name" — chỉ có path. Em đã map sang
 * `path` trực tiếp, đúng với router/index.jsx mới.
 * -----------------------------------------------------------
 */

const MENU = [
    {
        id: 'product',
        label: 'Product',
        icon: 'fa fa-clipboard-list',
        children: [
            { path: '/category/list', label: 'Category' },
            { path: '/product/list', label: 'Product' },
            { path: '/product-draft/list', label: 'ProductDraft' },
            { path: '/filter/list', label: 'Filter' },
            { path: '/attribute/list', label: 'Attribute' },
            { path: '/option/list', label: 'Option' },
            { path: '/manufacturer/list', label: 'Manufacture' },
            { path: '/review/list', label: 'Review' },
            { path: '/store-review/list', label: 'StoreReview' },
            { path: '/information/list', label: 'Information' },
            { path: '/banner/list', label: 'Banner' },
            { path: '/ingredient/list', label: 'Ingredient' },
            { path: '/contact/list', label: 'Contact' },
        ],
    },
    {
        id: 'order',
        label: 'Order',
        icon: 'fa fa-cart-plus',
        children: [
            { path: '/order/list', label: 'Order' },
            { path: '/order-status/list', label: 'OrderStatus' },
        ],
    },
    {
        id: 'marketing',
        label: 'Marketing',
        icon: 'fa fa-bullhorn',
        children: [
            { path: '/coupon/list', label: 'Coupon' },
            { path: '/voucher/list', label: 'GiftVoucher' },
            { path: '/voucher-theme/list', label: 'VoucherTheme' },
            { path: '/mail/send', label: 'SendMail' },
        ],
    },
    {
        id: 'carrier',
        label: 'Carrier',
        icon: 'fa fa-truck',
        children: [
            { path: '/carrier/list', label: 'Carrier' },
            { path: '/carrier-order-status/list', label: 'CarrierOrderStatus' },
        ],
    },
    {
        id: 'payment',
        label: 'Payment',
        icon: 'fa fa-money-bill-alt',
        children: [{ path: '/payment/list', label: 'Payment' }],
    },
    {
        id: 'user',
        label: 'Users',
        icon: 'mdi mdi-account-multiple',
        children: [
            { path: '/role/list', label: 'Role' },
            { path: '/user/list', label: 'User' },
            { path: '/user-group/list', label: 'UserGroup' },
        ],
    },
    {
        id: 'blog',
        label: 'Blog',
        icon: 'fa fa-tags',
        children: [
            { path: '/blog/list', label: 'Blog' },
            { path: '/blog-category/list', label: 'BlogCategory' },
            { path: '/blog-tag/list', label: 'BlogTag' },
        ],
    },
    {
        id: 'resource',
        label: 'Resource',
        icon: 'fa fa-map',
        children: [
            { path: '/language/list', label: 'Language' },
            { path: '/currency/list', label: 'Currency' },
            { path: '/stock-status/list', label: 'StockStatus' },
            { path: '/length-class/list', label: 'LengthClass' },
            { path: '/weight-class/list', label: 'WeightClass' },
            { path: '/country/list', label: 'Country' },
            { path: '/zone/list', label: 'Zone' },
            { path: '/district/list', label: 'District' },
            { path: '/ward/list', label: 'Ward' },
            { path: '/tax-class/list', label: 'TaxClass' },
            { path: '/tax-rate/list', label: 'TaxRate' },
            { path: '/geo-zone/list', label: 'GeoZone' },
            { path: '/skincare/list', label: 'Skincare' },
            { path: '/safety/list', label: 'Safety' },
            { path: '/effect/list', label: 'Effect' },
        ],
    },
    {
        id: 'systemConfig',
        label: 'SystemConfig',
        icon: 'mdi mdi-settings',
        children: [
            { path: '/setting/detail', label: 'Setting' },
            { path: '/menu/list', label: 'Menu' },
        ],
    },
    {
        // Menu lá (không có children) — click thẳng vào path
        id: 'report',
        label: 'Report',
        path: '/report/list',
        icon: 'far fa-chart-bar',
    },
];

export default MENU;
