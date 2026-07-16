import http from 'k6/http';
import { check, sleep } from 'k6';
import { Counter, Trend } from 'k6/metrics';

/**
 * k6 — TRANH CHẤP VARIANT khi add-to-cart.
 *
 * Mục tiêu KHÔNG phải throughput mà là ĐÚNG ĐẮN dưới đua lock:
 *   - Nhiều VU cùng giữ chỗ MỘT variant tồn nhỏ (policy Deny).
 *   - Xác minh: KHÔNG oversell (số add thành công ≤ on_hand), không 5xx/deadlock.
 *
 * Chuẩn bị (SQL) — chọn 1 product ĐƠN GIẢN (không variant), set tồn nhỏ:
 *   UPDATE product_stock ps
 *     JOIN product_variant pv ON pv.id = ps.product_variant_id
 *   SET ps.on_hand = 50, ps.reserved = 0, ps.inventory_policy = <Deny>
 *   WHERE pv.product_id = <PRODUCT_ID> AND pv.is_default = 1;
 *   -- và đảm bảo cửa hàng bật kiểm tra tồn (stock checkout enabled).
 *
 * Bỏ chặn trước khi chạy:
 *   - Route add-to-cart đang có middleware('throttle:30,1') → PHẢI nới/tắt,
 *     nếu không test chỉ đo throttle (429). Xem web.php.
 *
 * Chạy:
 *   k6 run -e BASE_URL=http://infun.co -e PRODUCT_ID=123 -e VUS=300 k6/cart-contention.js
 *
 * Sau khi chạy, xác minh oversell (SQL):
 *   SELECT product_variant_id, on_hand, reserved FROM product_stock
 *   WHERE product_variant_id = <default variant id>;
 *   -- reserved PHẢI ≤ on_hand. Và metric add_ok (× QTY) PHẢI ≤ on_hand.
 */

const BASE_URL    = __ENV.BASE_URL    || 'http://infun.co';
const PRODUCT_ID  = Number(__ENV.PRODUCT_ID || 0);
const PRODUCT_URL = __ENV.PRODUCT_URL || `${BASE_URL}/`; // trang bất kỳ có <meta csrf-token>
const QTY         = Number(__ENV.QTY || 1);
const VUS         = Number(__ENV.VUS || 300);
const DURATION    = __ENV.DURATION || '1m';

export const options = {
  scenarios: {
    contention: {
      executor: 'ramping-vus',
      startVUs: 0,
      stages: __ENV.STAGES ? JSON.parse(__ENV.STAGES) : [
        { duration: '10s',    target: VUS }, // dồn nhanh để tạo burst đua lock
        { duration: DURATION, target: VUS },
        { duration: '10s',    target: 0 },
      ],
    },
  },
  thresholds: {
    add_error: ['count<1'],                              // 5xx/deadlock phải = 0
    'http_req_duration{name:add_to_cart}': ['p(95)<2000'],
  },
};

const addOk        = new Counter('add_ok');        // 201 — giữ chỗ OK
const addRejected  = new Counter('add_rejected');  // 422 — hết hàng (ĐÚNG hành vi)
const addThrottled = new Counter('add_throttled'); // 429 — bị throttle (cần tắt)
const addCsrf      = new Counter('add_csrf_fail'); // 419 — CSRF sai
const addError     = new Counter('add_error');     // 5xx/khác — LỖI thật
const addLatency   = new Trend('add_latency', true);

const csrfByVu = {}; // mỗi VU 1 session (cookie jar riêng) → 1 holder riêng

function ensureCsrf() {
  if (csrfByVu[__VU]) return csrfByVu[__VU];
  const res = http.get(PRODUCT_URL, { tags: { name: 'get_csrf' } });
  const m = res.body ? res.body.match(/<meta name="csrf-token" content="([^"]+)"/i) : null;
  csrfByVu[__VU] = m ? m[1] : '';
  return csrfByVu[__VU];
}

export default function () {
  if (!PRODUCT_ID) {
    throw new Error('Thiếu -e PRODUCT_ID=<id sản phẩm đơn giản, tồn nhỏ>');
  }

  const token = ensureCsrf();

  const res = http.post(`${BASE_URL}/add-to-cart`, {
    product_id: String(PRODUCT_ID),
    quantity: String(QTY),
  }, {
    headers: {
      'X-CSRF-TOKEN': token,
      'X-Requested-With': 'XMLHttpRequest',
      'Accept': 'application/json',
    },
    tags: { name: 'add_to_cart' },
  });

  addLatency.add(res.timings.duration);

  if (res.status === 201)      addOk.add(1);
  else if (res.status === 422) addRejected.add(1);
  else if (res.status === 429) addThrottled.add(1);
  else if (res.status === 419) addCsrf.add(1);
  else                         addError.add(1);

  check(res, { 'không 5xx (không deadlock/timeout)': (r) => r.status < 500 });

  sleep(Math.random() * 0.5);
}
