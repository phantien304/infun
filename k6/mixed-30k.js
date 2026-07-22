import http from 'k6/http';
import { check, sleep, group } from 'k6';
import { Counter, Trend, Rate } from 'k6/metrics';
import { randomItem, randomIntBetween } from 'https://jslib.k6.io/k6-utils/1.4.0/index.js';

/**
 * k6 — MÔ HÌNH 30k USER ACTIVE (traffic hỗn hợp).
 *
 *   browse   ~85%  : xem list / search / khuyến mãi (READ, đi read-replica)
 *   add_cart  ~9%  : thêm giỏ (WRITE nhẹ + reservation lock)
 *   checkout  ~3%  : đặt hàng COD hoàn chỉnh (~900/30000) (WRITE nặng)
 *   flash_sale ~2% : dồn 1 variant tồn nhỏ (tranh chấp lock cực đại)
 *
 * Scale bằng -e TARGET: local đặt nhỏ (300–1000); 30k thật chạy k6-operator/
 * Grafana Cloud với -e TARGET=30000 (một máy KHÔNG kéo nổi 30k VU).
 *
 * Chạy (local, thu nhỏ):
 *   k6 run -e BASE_URL=http://infun.co -e TARGET=500 \
 *          -e PRODUCT_IDS=101,102,103 -e FLASH_PRODUCT_ID=999 \
 *          -e CATEGORY_IDS=1-50 \
 *          -e ZONE_ID=230 -e DISTRICT_ID=1 -e WARD_ID=1 k6/mixed-30k.js
 *
 * BẮT BUỘC trước khi chạy:
 *   - Nới/tắt throttle: add-to-cart (30,1) và save-order (10,1) — nếu không chỉ đo 429.
 *   - Dùng DB staging + payment_code=cod (không đụng ZaloPay). Tắt gửi mail thật
 *     (MAIL_MAILER=log) vì mỗi order dispatch job email. Truncate order sau test.
 *   - Reset tồn kho product test giữa các lượt: `php artisan k6:provision-stock`
 *     (xem docs/CHANGELOG-2026-07-20 mục 8 — default variant dễ cạn qua nhiều lượt
 *     → 422 giả). Command set on_hand cao cho PRODUCT_IDS, khan hiếm cho FLASH.
 *
 * CACHE (đi cùng CachePage query-cache — Track B):
 *   CachePage giờ cache cả trang danh mục/phân trang (filter[category_id], page,
 *   sort, per_page) chứ không chỉ path trần. Test này mô phỏng traffic để ĐO hit
 *   thật ở steady-state:
 *   - setup() ấm cache trước khi đo (production ấm 24/7, đừng đo cold-start).
 *   - browse category dùng Zipf 80/20 trên CATEGORY_IDS + page lệch về 1 → lặp
 *     lại trang hot → HIT. KEYWORD_RATIO phần nhỏ đi search (BYPASS, đo Meili).
 *   Tắt warm-up: -e WARMUP=0. Đổi tỉ lệ search: -e KEYWORD_RATIO=0.25.
 */

const BASE_URL = __ENV.BASE_URL || 'http://infun.co';
const TARGET   = Number(__ENV.TARGET || 500);
const DURATION = __ENV.DURATION || '5m';
const pct = (p) => Math.max(1, Math.round(TARGET * p));

// ── Data phụ thuộc môi trường — ĐẶT theo seed thật ──────────────────────────
const PRODUCT_IDS      = (__ENV.PRODUCT_IDS || '1,2,3,4,5').split(',');
const FLASH_PRODUCT_ID = Number(__ENV.FLASH_PRODUCT_ID || 0); // 1 SP tồn nhỏ, policy Deny
const KEYWORDS         = (__ENV.KEYWORDS || 'seed,áo,ly,quà,set').split(',');
const PER_PAGE         = [20, 24, 48];
// Sort token PHẢI nằm trong allowedSorts() của ProductRepository (nếu không Spatie
// ném InvalidSortQuery). CachePage chỉ cache sort khớp /^-?[a-z_]+$/i.
const SORTS            = (__ENV.SORTS || '-created_at,created_at,price,-price').split(',');
const ZONE_ID          = __ENV.ZONE_ID || '230';
const DISTRICT_ID      = __ENV.DISTRICT_ID || '1';
const WARD_ID          = __ENV.WARD_ID || '1';

// Category ID để browse danh mục (cacheable). Default 1..50 theo seed test.
// ĐẶT hot lên đầu — zipfPick lấy 20% ĐẦU làm hot (80% traffic dồn vào đó).
const CATEGORY_IDS     = (__ENV.CATEGORY_IDS || range(1, 50).join(',')).split(',');
// Tỉ lệ browse-list đi search keyword (BYPASS cache → đo Meili). Phần còn lại là
// browse danh mục (cacheable). 0 = mọi list-browse đều cacheable.
const KEYWORD_RATIO    = Number(__ENV.KEYWORD_RATIO || 0.25);
const WARMUP           = String(__ENV.WARMUP || '1') !== '0';

// Trang guest browse path-trần để đo cache_page. ĐẶT slug thật để giống production:
//   -e BROWSE_PATHS="/,/san-pham,/ao-thun-c12,/qua-tang-p101,/khuyen-mai"
// Zipf 80/20: 80% traffic dồn vào 20% path ĐẦU (trang hot) → HIT thay vì random đều.
const BROWSE_PATHS     = (__ENV.BROWSE_PATHS || '/,/san-pham,/khuyen-mai').split(',');

function range(from, to) {
  const out = [];
  for (let i = from; i <= to; i++) out.push(i);
  return out;
}
// Zipf 80/20 tổng quát: 80% bốc từ 20% phần tử ĐẦU (hot), 20% từ phần đuôi.
function zipfPick(list) {
  const hot  = Math.max(1, Math.ceil(list.length * 0.2));
  const pool = Math.random() < 0.8 ? list.slice(0, hot) : list.slice(hot);
  const arr  = pool.length ? pool : list;
  return arr[randomIntBetween(0, arr.length - 1)];
}
function zipfPath()     { return zipfPick(BROWSE_PATHS); }
function zipfCategory() { return zipfPick(CATEGORY_IDS); }
// Người thật đa số xem trang 1; đuôi dài trang sâu. Giữ card cache page nhỏ + HIT cao.
function zipfPage() {
  const r = Math.random();
  if (r < 0.7) return 1;
  if (r < 0.9) return 2;
  return randomIntBetween(3, 5);
}

export const options = {
  scenarios: {
    browse: {
      executor: 'ramping-vus', exec: 'browse', startVUs: 0,
      stages: [{ duration: '1m', target: pct(0.85) }, { duration: DURATION, target: pct(0.85) }, { duration: '30s', target: 0 }],
    },
    add_cart: {
      executor: 'ramping-vus', exec: 'addToCart', startVUs: 0,
      stages: [{ duration: '1m', target: pct(0.09) }, { duration: DURATION, target: pct(0.09) }, { duration: '30s', target: 0 }],
    },
    checkout: {
      executor: 'ramping-vus', exec: 'checkout', startVUs: 0,
      stages: [{ duration: '1m', target: pct(0.03) }, { duration: DURATION, target: pct(0.03) }, { duration: '30s', target: 0 }],
    },
    flash_sale: {
      executor: 'ramping-vus', exec: 'flashSale', startVUs: 0, startTime: '90s', // sau khi hệ thống warm
      stages: [{ duration: '20s', target: Number(__ENV.FLASH_VUS || pct(0.02)) }, { duration: '60s', target: Number(__ENV.FLASH_VUS || pct(0.02)) }, { duration: '10s', target: 0 }],
    },
  },
  thresholds: {
    http_req_failed:                    ['rate<0.02'],
    'http_req_duration{action:browse}': ['p(95)<1000'],
    'http_req_duration{action:add}':    ['p(95)<1500'],
    'http_req_duration{action:order}':  ['p(95)<3000'],
    order_error:                        ['count<1'], // 5xx khi đặt hàng = deadlock/lỗi thật
    flash_error:                        ['count<1'],
    // HIT ratio guest browse. Với setup() warm + browse danh mục cacheable, steady
    // state kỳ vọng >0.9; đặt 0.8 làm ngưỡng bắt regression (cũ 0.5 khi chưa cache
    // category → quá lỏng). Tune theo BROWSE_PATHS/CATEGORY_IDS/KEYWORD_RATIO.
    cache_hit:                          ['rate>0.8'],
  },
};

const addOk        = new Counter('add_ok');
const addRejected  = new Counter('add_rejected');
const orderOk      = new Counter('order_ok');
const orderRejected= new Counter('order_rejected');
const orderError   = new Counter('order_error');
const flashOk      = new Counter('flash_ok');
const flashReject  = new Counter('flash_reject');
const flashError   = new Counter('flash_error');
const throttled    = new Counter('throttled_429');
const cacheHit     = new Rate('cache_hit'); // tỉ lệ browse trúng cache_page (X-Cache: HIT)

const csrfByVu = {}; // mỗi VU 1 session (cookie jar riêng) → 1 holder riêng

// ── Warm cache_page TRƯỚC khi đo ────────────────────────────────────────────
// Production cache ấm 24/7 (TTL 24h); test 1-vài phút đang đo cold-start. setup()
// chạy 1 lần, ấm các entry cacheable phổ biến nhất trên page store (server-side,
// chia sẻ mọi VU) → cache_hit phản ánh steady-state.
export function setup() {
  if (!WARMUP) return {};
  BROWSE_PATHS.forEach((path) => http.get(`${BASE_URL}${path}`, { tags: { name: 'warmup' } }));
  const hot = CATEGORY_IDS.slice(0, Math.max(1, Math.ceil(CATEGORY_IDS.length * 0.2)));
  hot.forEach((cid) => {
    http.get(`${BASE_URL}/san-pham?filter[category_id]=${cid}&page=1`, { tags: { name: 'warmup' } });
    SORTS.forEach((s) => http.get(`${BASE_URL}/san-pham?filter[category_id]=${cid}&page=1&sort=${s}`, { tags: { name: 'warmup' } }));
  });
  return {};
}

function csrf() {
  if (csrfByVu[__VU]) return csrfByVu[__VU];
  return refreshCsrf();
}
// VU sống nhiều phút, lặp hàng chục request — token cache-1-lần-cho-cả-đời-VU
// dính 419 khi session/token phía server đổi giữa chừng (cookie jar k6 vẫn
// đúng, chỉ là token cũ không còn khớp). refreshCsrf() lấy token mới, dùng
// làm bước retry-1-lần trong postAdd/checkoutCod bên dưới — đúng hành vi
// browser thật (trang nào cũng có token hiện hành, không cache xuyên session).
function refreshCsrf() {
  // Lấy token từ /give-me-csrf (route NGOÀI group cache_page → luôn tươi). KHÔNG
  // scrape <meta> của GET / vì trang đó có thể bị cache_page → token guest khác.
  const r = http.get(`${BASE_URL}/give-me-csrf`, { tags: { name: 'get_csrf' }, headers: { 'Accept': 'application/json' } });
  let token = '';
  try { token = r.json('data') || ''; } catch (e) { token = ''; }
  csrfByVu[__VU] = token;
  return token;
}
function headers(token) {
  return { headers: { 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } };
}
function qs(params) {
  return Object.keys(params).map((k) => encodeURIComponent(k) + '=' + encodeURIComponent(params[k])).join('&');
}
// Browse danh mục — CACHEABLE (filter[category_id]/page/sort/per_page thuộc whitelist
// CachePage). Zipf trên category + page lệch về 1 → lặp trang hot → HIT.
function categoryListUrl() {
  const p = { 'filter[category_id]': zipfCategory(), page: zipfPage() };
  if (Math.random() < 0.5) p['sort'] = randomItem(SORTS);
  if (Math.random() < 0.2) p['per_page'] = randomItem(PER_PAGE);
  return `${BASE_URL}/san-pham?${qs(p)}`;
}
// Search keyword — BYPASS cache (free-text → Meilisearch). Đo đường backend thật.
function keywordSearchUrl() {
  const p = { 'filter[keyword]': randomItem(KEYWORDS), page: zipfPage() };
  return `${BASE_URL}/san-pham?${qs(p)}`;
}
// List browse: đa số danh mục cacheable, KEYWORD_RATIO phần đi search uncacheable.
function listUrl() {
  return Math.random() < KEYWORD_RATIO ? keywordSearchUrl() : categoryListUrl();
}
function postAdd(productId) {
  const body = { product_id: String(productId), quantity: '1' };
  const opts = { tags: { name: 'add_to_cart', action: 'add' } };
  let res = http.post(`${BASE_URL}/checkout/add-to-cart`, body, { ...headers(csrf()), ...opts });
  if (res.status === 419) {
    res = http.post(`${BASE_URL}/checkout/add-to-cart`, body, { ...headers(refreshCsrf()), ...opts });
  }
  return res;
}

// Checkout COD đầy đủ — kích ĐÚNG 2 điểm lock:
//   GET /checkout   → reserveCheckout()  (FOR UPDATE, giữ chỗ product_stock.reserved)
//   POST save-order → deductForOrder()   (FOR UPDATE, trừ on_hand, guard oversell)
// Trả về response của save-order để phân loại. Retry 1 lần khi 419 (xem
// refreshCsrf()) — cùng lý do với postAdd().
function checkoutCod() {
  http.get(`${BASE_URL}/checkout`, { tags: { name: 'checkout_page', action: 'order' } });
  const body = {
    full_name: 'K6 Tester', telephone: '0900000000', email: 'k6@test.local',
    address: 'Load test address', zone_id: ZONE_ID, district_id: DISTRICT_ID, ward_id: WARD_ID,
    payment_code: 'cod',
  };
  const opts = { tags: { name: 'save_order', action: 'order' } };
  let res = http.post(`${BASE_URL}/checkout/save-order`, body, { ...headers(csrf()), ...opts });
  if (res.status === 419) {
    res = http.post(`${BASE_URL}/checkout/save-order`, body, { ...headers(refreshCsrf()), ...opts });
  }
  return res;
}

// ── Nhánh READ (đa số) ──────────────────────────────────────────────────────
export function browse() {
  group('browse', () => {
    // Zipf: đa số hit trang hot path-trần (cache_page HIT). Phần listUrl là browse
    // danh mục cacheable + 1 lát search (BYPASS) để vẫn đo backend thật.
    const url = Math.random() < 0.8 ? `${BASE_URL}${zipfPath()}` : listUrl();
    const res = http.get(url, { tags: { name: 'list', action: 'browse' }, headers: { 'Accept': 'text/html' } });
    const xcache = res.headers['X-Cache'] || res.headers['x-cache'] || '';
    cacheHit.add(xcache === 'HIT');
    check(res, { 'browse 200': (r) => r.status === 200 }, { action: 'browse' });
  });
  sleep(randomIntBetween(2, 5));
}

// ── Thêm giỏ ────────────────────────────────────────────────────────────────
export function addToCart() {
  const res = postAdd(randomItem(PRODUCT_IDS));
  if (res.status === 201) addOk.add(1);
  else if (res.status === 422) addRejected.add(1);
  else if (res.status === 429) throttled.add(1);
  sleep(randomIntBetween(1, 4));
}

// Đơn THẬT chỉ khi save-order redirect landing tại `checkout/success`. save-order
// LUÔN trả 302: thành công → checkout/success; giỏ rỗng/hết hàng/coupon hết →
// redirect về /checkout. k6 tự follow redirect nên chỉ có `res.url` phân biệt được
// (status 2xx ở CẢ 2 trường hợp → đếm theo status sẽ phồng như flash_ok=1675).
function orderCreated(res) {
  return res.status < 400 && (res.url || '').indexOf('/checkout/success') !== -1;
}

// ── Đặt hàng COD hoàn chỉnh ──────────────────────────────────────────────────
export function checkout() {
  postAdd(randomItem(PRODUCT_IDS));
  const res = checkoutCod();

  if (res.status === 429) throttled.add(1);
  else if (res.status >= 500) orderError.add(1);
  else if (orderCreated(res)) orderOk.add(1);       // đơn thật (kể cả idempotency replay về success)
  else orderRejected.add(1);                        // redirect về /checkout: rỗng/hết hàng/coupon hết
  check(res, { 'order không 5xx': (r) => r.status < 500 });
  sleep(randomIntBetween(3, 8));
}

// ── Flash sale: dồn 1 variant tồn nhỏ (tranh chấp lock) ──────────────────────
export function flashSale() {
  if (!FLASH_PRODUCT_ID) return;
  postAdd(FLASH_PRODUCT_ID);   // nạp giỏ (session)
  const res = checkoutCod();  // GET /checkout (hold) → save-order (deduct) — 2 điểm đua lock

  if (res.status === 429) throttled.add(1);
  else if (res.status >= 500) flashError.add(1);    // deadlock/lock-timeout/lỗi thật
  else if (orderCreated(res)) flashOk.add(1);       // đặt được 1 suất tồn THẬT (khớp DB)
  else flashReject.add(1);                          // hết hàng → redirect về checkout (ĐÚNG, chống oversell)
  check(res, { 'flash không 5xx': (r) => r.status < 500 }, { action: 'order' });
  sleep(randomIntBetween(0, 1));
}
