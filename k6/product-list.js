import http from 'k6/http';
import { check, sleep, group } from 'k6';
import { Rate } from 'k6/metrics';
import { randomItem, randomIntBetween } from 'https://jslib.k6.io/k6-utils/1.4.0/index.js';

/**
 * k6 load test — trang list sản phẩm (/san-pham).
 *
 * Phủ CẢ HAI nhánh backend:
 *   - Không keyword  → ProductRepository::list (DB) + ProductDTO hydrate
 *   - Có filter[keyword] → nhánh Meilisearch (Product::search)
 * Đa dạng hoá URL (keyword/page/per_page/sort/in_stock) để né mọi tầng cache
 * → đo tải backend THẬT.
 *
 * Chạy:
 *   k6 run k6/product-list.js
 *   k6 run -e BASE_URL=http://infun.co -e VUS=60 -e DURATION=3m k6/product-list.js
 *   k6 run -e KEYWORDS="seed,áo,ly,quà" k6/product-list.js
 *
 * LƯU Ý: tạm bỏ middleware cache_page để đo backend (đang tắt). Nhớ chạy
 * `php artisan queue:work` không cần thiết ở đây — đây chỉ là GET đọc.
 */

const BASE_URL  = __ENV.BASE_URL  || 'http://infun.co';
const LIST_PATH = __ENV.LIST_PATH || '/san-pham';
const VUS       = Number(__ENV.VUS || 40);
const DURATION  = __ENV.DURATION || '2m';

// Đổi theo data thật để keyword khớp nhiều kết quả hơn.
const KEYWORDS  = (__ENV.KEYWORDS || 'seed,áo,ly,cốc,quà,set,hoa,nến').split(',');
const PER_PAGE  = [20, 24, 48];
const SORTS     = ['', '-price', 'price', '-created_at'];

export const options = {
  scenarios: {
    ramp: {
      executor: 'ramping-vus',
      startVUs: 0,
      stages: __ENV.STAGES ? JSON.parse(__ENV.STAGES) : [
        { duration: '30s',     target: Math.ceil(VUS / 4) }, // khởi động + warm cache config/menu
        { duration: DURATION,  target: VUS },                // giữ tải
        { duration: '30s',     target: 0 },                  // hạ tải
      ],
      gracefulStop: '15s',
    },
  },
  thresholds: {
    http_req_failed:   ['rate<0.01'],               // < 1% request lỗi
    http_req_duration: ['p(95)<800', 'p(99)<1500'], // chỉnh ngưỡng theo mục tiêu
    checks:            ['rate>0.99'],
  },
};

const pageErrors = new Rate('page_errors');

function qs(obj) {
  return Object.keys(obj)
    .map((k) => encodeURIComponent(k) + '=' + encodeURIComponent(obj[k]))
    .join('&');
}

function buildListUrl() {
  const p = { page: randomIntBetween(1, 5) };

  if (Math.random() < 0.5) {
    // Nhánh Meilisearch
    const kw = randomItem(KEYWORDS);
    p['query'] = kw;
    p['filter[keyword]'] = kw;
  } else {
    // Nhánh DB list — đôi khi kèm filter/sort
    if (Math.random() < 0.4) p['filter[in_stock]'] = '1';
    const sort = randomItem(SORTS);
    if (sort) p['sort'] = sort;
  }

  if (Math.random() < 0.5) p['per_page'] = randomItem(PER_PAGE);

  return `${BASE_URL}${LIST_PATH}?${qs(p)}`;
}

export default function () {
  group('product_list', () => {
    const url = buildListUrl();
    const res = http.get(url, {
      tags: { name: 'product_list' },
      headers: { 'Accept': 'text/html' },
    });

    const ok = check(res, {
      'status 200': (r) => r.status === 200,
      'có nội dung':  (r) => r.body && r.body.length > 500,
    });
    pageErrors.add(!ok);
  });

  sleep(randomIntBetween(1, 3)); // think time giả lập người dùng
}
