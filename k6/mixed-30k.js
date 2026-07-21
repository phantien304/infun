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
 *          -e ZONE_ID=230 -e DISTRICT_ID=1 -e WARD_ID=1 k6/mixed-30k.js
 *
 * BẮT BUỘC trước khi chạy:
 *   - Nới/tắt throttle: add-to-cart (30,1) và save-order (10,1) — nếu không chỉ đo 429.
 *   - Dùng DB staging + payment_code=cod (không đụng ZaloPay). Tắt gửi mail thật
 *     (MAIL_MAILER=log) vì mỗi order dispatch job email. Truncate order sau test.
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
const ZONE_ID          = __ENV.ZONE_ID || '230';
const DISTRICT_ID      = __ENV.DISTRICT_ID || '1';
const WARD_ID          = __ENV.WARD_ID || '1';

// Trang guest browse để đo cache_page (cacheable). ĐẶT slug thật để giống production:
//   -e BROWSE_PATHS="/,/san-pham,/ao-thun-c12,/qua-tang-p101,/khuyen-mai"
// Phân bố Zipf 80/20: 80% traffic dồn vào 20% path đầu (trang hot) — giống thật,
// tạo HIT cache thay vì random đều (né cache) như bản cũ.
const BROWSE_PATHS     = (__ENV.BROWSE_PATHS || '/,/san-pham,/khuyen-mai').split(',');

function zipfPath() {
  const hot = Math.max(1, Math.ceil(BROWSE_PATHS.length * 0.2));
  const pool = Math.random() < 0.8 ? BROWSE_PATHS.slice(0, hot) : BROWSE_PATHS.slice(hot);
  const list = pool.length ? pool : BROWSE_PATHS;
  return list[randomIntBetween(0, list.length - 1)];
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
    // HIT ratio guest browse — cả run (kể cả MISS lúc warm). Tune theo BROWSE_PATHS.
    cache_hit:                          ['rate>0.5'],
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
function listUrl() {
  const p = { page: randomIntBetween(1, 5) };
  if (Math.random() < 0.5) { const k = randomItem(KEYWORDS); p['query'] = k; p['filter[keyword]'] = k; }
  if (Math.random() < 0.5) p['per_page'] = randomItem(PER_PAGE);
  const q = Object.keys(p).map((k) => encodeURIComponent(k) + '=' + encodeURIComponent(p[k])).join('&');
  return `${BASE_URL}/san-pham?${q}`;
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
    // Zipf: đa số hit trang hot (cache_page HIT). ~20% pha keyword/filter (MISS/BYPASS)
    // để vẫn đo đường backend thật khi không trúng cache.
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

// ── Đặt hàng COD hoàn chỉnh ──────────────────────────────────────────────────
export function checkout() {
  postAdd(randomItem(PRODUCT_IDS));
  const res = checkoutCod();

  if (res.status >= 200 && res.status < 300) orderOk.add(1);
  else if (res.status === 422) orderRejected.add(1);
  else if (res.status === 429) throttled.add(1);
  else if (res.status >= 500) orderError.add(1);
  check(res, { 'order không 5xx': (r) => r.status < 500 });
  sleep(randomIntBetween(3, 8));
}

// ── Flash sale: dồn 1 variant tồn nhỏ (tranh chấp lock) ──────────────────────
export function flashSale() {
  if (!FLASH_PRODUCT_ID) return;
  postAdd(FLASH_PRODUCT_ID);   // nạp giỏ (session)
  const res = checkoutCod();  // GET /checkout (hold) → save-order (deduct) — 2 điểm đua lock

  if (res.status >= 200 && res.status < 300) flashOk.add(1);   // đặt được 1 suất tồn
  else if (res.status === 422) flashReject.add(1);             // hết hàng (ĐÚNG — chống oversell)
  else if (res.status === 429) throttled.add(1);
  else if (res.status >= 500) flashError.add(1);               // deadlock/lock-timeout/lỗi thật
  check(res, { 'flash không 5xx': (r) => r.status < 500 }, { action: 'order' });
  sleep(randomIntBetween(0, 1));
}
