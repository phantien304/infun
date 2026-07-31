/* ═══════════════════════════════════════════════════════════════════════
   Store dùng chung cho prototype — Alpine.store('app')
   Nạp TRƯỚC alpinejs (file này chỉ đăng ký listener 'alpine:init').

   Ba việc:
     1. i18n  — t('key') tra từ điển theo locale đang chọn
     2. tiền tệ — money(vnd) quy đổi + format theo currency đang chọn
     3. giỏ hàng — đếm số lượng, đủ để prototype phản hồi khi bấm

   Mô hình tiền tệ bám sát CurrencyService thật trong repo:
     convertPrice = price × value, làm tròn theo decimal_place
     formatPrice  = symbol_left + số + symbol_right
   ═══════════════════════════════════════════════════════════════════════ */

/* sep/dec đi theo TIỀN TỆ chứ không theo ngôn ngữ — giống bảng `currency`
   trong DB (decimal_place, symbol_left, symbol_right). Nếu lấy dấu phân
   cách theo locale thì người Việt xem giá USD sẽ thấy "$98,03", đúng chuẩn
   vi-VN nhưng ai cũng đọc nhầm thành chín mươi tám nghìn. */
const CURRENCIES = {
  VND: { code: 'VND', value: 1,         decimals: 0, sep: '.', dec: ',', left: '',  right: '₫', label: 'VND ₫' },
  USD: { code: 'USD', value: 1 / 25400, decimals: 2, sep: ',', dec: '.', left: '$', right: '',  label: 'USD $' },
  EUR: { code: 'EUR', value: 1 / 27600, decimals: 2, sep: '.', dec: ',', left: '€', right: '',  label: 'EUR €' }
};

const LOCALES = {
  vi: { code: 'vi', label: 'Tiếng Việt', short: 'VI', intl: 'vi-VN' },
  en: { code: 'en', label: 'English',    short: 'EN', intl: 'en-US' }
};

/* Chuỗi giao diện. Trong app thật phần này nằm ở resources/lang/{vi,en};
   nội dung sản phẩm/danh mục thì đã có sẵn các bảng *Description khoá theo
   language_id (ProductDescription, CategoryDescription…) nên đa ngôn ngữ ở
   tầng dữ liệu không phải làm mới. */
const DICT = {
  vi: {
    'bar.ship': 'Miễn phí vận chuyển cho đơn từ 299.000₫',
    'bar.track': 'Tra cứu đơn hàng', 'bar.sell': 'Bán hàng cùng chúng tôi', 'bar.help': 'Hỗ trợ',
    'nav.all': 'Tất cả danh mục', 'nav.new': 'Hàng mới về',
    'search.ph': 'Tìm sản phẩm, thương hiệu, danh mục…', 'search.btn': 'Tìm',
    'acc.title': 'Tài khoản', 'menu.featured': 'Đang được quan tâm', 'menu.viewall': 'Xem tất cả',
    'hero.badge': 'BỘ SƯU TẬP THÁNG 8', 'hero.h': 'Mọi thứ bạn cần, một nơi duy nhất',
    'hero.sub': 'Hơn 3.000 sản phẩm từ 120 thương hiệu, giao trong 24 giờ nội thành.',
    'hero.cta': 'Khám phá ngay',
    'flash.golden': 'GIỜ VÀNG', 'flash.off': 'Giảm đến 50%', 'flash.ends': 'Kết thúc sau',
    'flash.title': 'Flash sale', 'flash.sold': 'Đã bán',
    'vou.new': 'Cho khách mới', 'new.badge': 'VỪA LÊN KỆ', 'new.count': '142 sản phẩm mới',
    'cat.title': 'Danh mục nổi bật', 'sec.viewall': 'Xem tất cả →',
    'best.badge': 'BÁN CHẠY NHẤT', 'best.h': 'Top 100 sản phẩm tuần này',
    'trust.fast': 'Giao 24h', 'trust.fastsub': 'Nội thành',
    'trust.ret': 'Đổi trả 30 ngày', 'trust.retsub': 'Miễn phí',
    'blog.badge': 'CẨM NANG MUA SẮM', 'blog.h': '5 điều cần biết trước khi chọn máy lọc không khí',
    'news.h': 'Nhận ưu đãi sớm nhất', 'news.btn': 'Gửi',
    'grid.title': 'Gợi ý cho bạn', 'grid.all': 'Tất cả', 'grid.best': 'Bán chạy', 'grid.cheap': 'Giá tốt',
    'rev.count': 'đánh giá',
    'ft.shop': 'Mua sắm', 'ft.help': 'Hỗ trợ', 'ft.about': 'Về chúng tôi', 'ft.pay': 'Thanh toán',
    'ft.tagline': 'Sàn thương mại điện tử đa ngành hàng. Giao nhanh, đổi trả dễ.',
    'ft.note': '© 2026 infun. Prototype giao diện — dữ liệu minh hoạ.',
    'pd.home': 'Trang chủ', 'pd.instock': 'Còn hàng', 'pd.lowstock': 'Sắp hết',
    'pd.oos': 'Hết hàng', 'pd.sold': 'đã bán', 'pd.sku': 'Mã',
    'pd.qty': 'Số lượng', 'pd.addcart': 'Thêm vào giỏ', 'pd.buynow': 'Mua ngay',
    'pd.wh': 'Tồn theo kho', 'pd.save': 'Tiết kiệm',
    'pd.tab.desc': 'Mô tả', 'pd.tab.spec': 'Thông số', 'pd.tab.rev': 'Đánh giá',
    'pd.related': 'Sản phẩm tương tự', 'pd.ship': 'Giao 24h nội thành',
    'pd.ret': 'Đổi trả 30 ngày', 'pd.auth': 'Hàng chính hãng 100%',
    'pd.revtitle': 'Khách hàng nói gì', 'pd.of5': 'trên 5',
    'cl.filters': 'Bộ lọc', 'cl.clearall': 'Xoá tất cả', 'cl.apply': 'Xem kết quả',
    'cl.type': 'Loại sản phẩm', 'cl.brand': 'Thương hiệu', 'cl.color': 'Màu sắc',
    'cl.price': 'Khoảng giá', 'cl.rating': 'Đánh giá', 'cl.status': 'Tình trạng',
    'cl.instock': 'Chỉ hàng có sẵn', 'cl.onsale': 'Đang giảm giá',
    'cl.up': 'trở lên', 'cl.from': 'Từ', 'cl.to': 'đến',
    'cl.sort': 'Sắp xếp', 'cl.s.relevant': 'Liên quan nhất', 'cl.s.new': 'Mới nhất',
    'cl.s.popular': 'Bán chạy', 'cl.s.rating': 'Đánh giá cao', 'cl.s.asc': 'Giá thấp → cao',
    'cl.s.desc': 'Giá cao → thấp',
    'cl.found': 'sản phẩm', 'cl.showing': 'Đang xem', 'cl.of': 'trong',
    'cl.more': 'Xem thêm', 'cl.none': 'Không tìm thấy sản phẩm nào',
    'cl.nonesub': 'Thử bỏ bớt bộ lọc hoặc đổi từ khoá tìm kiếm.',
    'cl.qph': 'Lọc trong danh mục này…', 'cl.sale': 'Giảm', 'cl.soldn': 'đã bán'
  },
  en: {
    'bar.ship': 'Free shipping on orders over 299,000₫',
    'bar.track': 'Track order', 'bar.sell': 'Sell with us', 'bar.help': 'Support',
    'nav.all': 'All categories', 'nav.new': 'New arrivals',
    'search.ph': 'Search products, brands, categories…', 'search.btn': 'Search',
    'acc.title': 'Account', 'menu.featured': 'Trending now', 'menu.viewall': 'View all',
    'hero.badge': 'AUGUST COLLECTION', 'hero.h': 'Everything you need, in one place',
    'hero.sub': 'Over 3,000 products from 120 brands, delivered within 24 hours in the city.',
    'hero.cta': 'Start exploring',
    'flash.golden': 'GOLDEN HOUR', 'flash.off': 'Up to 50% off', 'flash.ends': 'Ends in',
    'flash.title': 'Flash sale', 'flash.sold': 'Sold',
    'vou.new': 'For new customers', 'new.badge': 'JUST LANDED', 'new.count': '142 new products',
    'cat.title': 'Top categories', 'sec.viewall': 'View all →',
    'best.badge': 'BEST SELLERS', 'best.h': 'Top 100 products this week',
    'trust.fast': '24h delivery', 'trust.fastsub': 'In city',
    'trust.ret': '30-day returns', 'trust.retsub': 'Free',
    'blog.badge': 'BUYING GUIDE', 'blog.h': '5 things to know before choosing an air purifier',
    'news.h': 'Get offers first', 'news.btn': 'Send',
    'grid.title': 'Picked for you', 'grid.all': 'All', 'grid.best': 'Best sellers', 'grid.cheap': 'Best value',
    'rev.count': 'reviews',
    'ft.shop': 'Shop', 'ft.help': 'Support', 'ft.about': 'About us', 'ft.pay': 'Payment',
    'ft.tagline': 'A multi-category marketplace. Fast delivery, easy returns.',
    'ft.note': '© 2026 infun. Interface prototype — sample data.',
    'pd.home': 'Home', 'pd.instock': 'In stock', 'pd.lowstock': 'Low stock',
    'pd.oos': 'Out of stock', 'pd.sold': 'sold', 'pd.sku': 'SKU',
    'pd.qty': 'Quantity', 'pd.addcart': 'Add to cart', 'pd.buynow': 'Buy now',
    'pd.wh': 'Stock by warehouse', 'pd.save': 'You save',
    'pd.tab.desc': 'Description', 'pd.tab.spec': 'Specifications', 'pd.tab.rev': 'Reviews',
    'pd.related': 'Similar products', 'pd.ship': '24h city delivery',
    'pd.ret': '30-day returns', 'pd.auth': '100% authentic',
    'pd.revtitle': 'What customers say', 'pd.of5': 'out of 5',
    'cl.filters': 'Filters', 'cl.clearall': 'Clear all', 'cl.apply': 'Show results',
    'cl.type': 'Product type', 'cl.brand': 'Brand', 'cl.color': 'Colour',
    'cl.price': 'Price range', 'cl.rating': 'Rating', 'cl.status': 'Availability',
    'cl.instock': 'In stock only', 'cl.onsale': 'On sale',
    'cl.up': 'and up', 'cl.from': 'From', 'cl.to': 'to',
    'cl.sort': 'Sort by', 'cl.s.relevant': 'Most relevant', 'cl.s.new': 'Newest',
    'cl.s.popular': 'Best selling', 'cl.s.rating': 'Top rated', 'cl.s.asc': 'Price low → high',
    'cl.s.desc': 'Price high → low',
    'cl.found': 'products', 'cl.showing': 'Showing', 'cl.of': 'of',
    'cl.more': 'Load more', 'cl.none': 'No products found',
    'cl.nonesub': 'Try removing some filters or changing your search terms.',
    'cl.qph': 'Filter within this category…', 'cl.sale': 'Save', 'cl.soldn': 'sold'
  }
};

document.addEventListener('alpine:init', () => {
  Alpine.store('app', {
    locale: 'vi',
    currency: 'VND',
    cart: 3,
    locales: LOCALES,
    currencies: CURRENCIES,

    get L() { return LOCALES[this.locale]; },
    get C() { return CURRENCIES[this.currency]; },

    setLocale(code) { if (LOCALES[code]) this.locale = code; },
    setCurrency(code) { if (CURRENCIES[code]) this.currency = code; },

    t(key) { return (DICT[this.locale] && DICT[this.locale][key]) || DICT.vi[key] || key; },

    /* Chọn chuỗi theo locale từ object { vi: '…', en: '…' } — mô phỏng cách
       các bảng *Description trả về nội dung theo language_id. */
    tr(obj) { return (obj && (obj[this.locale] ?? obj.vi)) ?? ''; },

    money(vnd) {
      const c = this.C;
      const n = Math.abs(Number((vnd * c.value).toFixed(c.decimals)));
      const [i, f] = n.toFixed(c.decimals).split('.');
      const int = i.replace(/\B(?=(\d{3})+(?!\d))/g, c.sep);
      const s = f ? int + c.dec + f : int;
      return (vnd < 0 ? '-' : '') + c.left + s + c.right;
    },

    num(n) { return Number(n).toLocaleString(this.L.intl); },
    addToCart(q = 1) { this.cart += q; }
  });
});
