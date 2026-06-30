// k6 load test mẫu cho infun.
//
// Chạy:
//   docker compose run --rm k6 run /scripts/load-test.js
//   docker compose run --rm -e VUS=50 -e DURATION=60s k6 run /scripts/load-test.js
//   docker compose run --rm -e BASE_URL=http://localhost:8000 k6 run /scripts/load-test.js
//
// Thay endpoint trong mảng SCENARIOS bên dưới cho match API thực tế.
// Tip:
//   - Bật cache Redis trước khi đo (warm-up) → reflect production hơn.
//   - Tăng VUS dần (10 → 50 → 200) để tìm điểm gãy throughput.
//   - Xem `docker compose logs infun-web | grep upstream=` để check
//     traffic phân bổ đều cho các replicas chưa.

import http from 'k6/http';
import { check, sleep } from 'k6';
import { Trend, Rate } from 'k6/metrics';

const BASE_URL  = __ENV.BASE_URL  || 'http://infun-web';
const VUS       = parseInt(__ENV.VUS || '20', 10);
const DURATION  = __ENV.DURATION || '30s';

// Custom metric: time per endpoint (k6 default chỉ tổng hợp).
const productListTrend = new Trend('product_list_ms', true);
const productShowTrend = new Trend('product_show_ms', true);
const errorRate        = new Rate('error_rate');

// Sửa thành endpoint thực tế của infun.
// API CMS đang dùng prefix /rcms (xem routes/rcms.php).
const SCENARIOS = [
    { name: 'list',  url: '/rcms/products?page=1',  trend: productListTrend, weight: 7 },
    { name: 'show',  url: '/rcms/products/1',       trend: productShowTrend, weight: 3 },
];

export const options = {
    // Stage: ramp up → sustain → ramp down. Đo trên giai đoạn sustain.
    stages: [
        { duration: '10s',          target: Math.ceil(VUS / 2) },
        { duration: DURATION,       target: VUS },
        { duration: '5s',           target: 0 },
    ],
    thresholds: {
        // Fail test nếu vi phạm — tiện cho CI sau này.
        http_req_failed:   ['rate<0.01'],   // <1% error
        http_req_duration: ['p(95)<800'],   // p95 < 800ms
        error_rate:        ['rate<0.01'],
    },
    summaryTrendStats: ['avg', 'min', 'med', 'p(90)', 'p(95)', 'p(99)', 'max'],
};

// Chọn scenario theo weight (rough — không cần chính xác).
function pickScenario() {
    const total = SCENARIOS.reduce((s, x) => s + x.weight, 0);
    let r = Math.random() * total;
    for (const s of SCENARIOS) {
        r -= s.weight;
        if (r <= 0) return s;
    }
    return SCENARIOS[0];
}

export default function () {
    const sc = pickScenario();
    const res = http.get(`${BASE_URL}${sc.url}`, {
        headers: { 'Accept': 'application/json' },
        tags:    { endpoint: sc.name },
    });

    sc.trend.add(res.timings.duration);
    const ok = check(res, {
        'status 2xx': r => r.status >= 200 && r.status < 300,
    });
    errorRate.add(!ok);

    sleep(0.2 + Math.random() * 0.3); // 200-500ms think time
}

export function handleSummary(data) {
    // In gọn ra console (k6 default summary đã đẹp, đây chỉ thêm marker).
    return {
        'stdout': textSummary(data),
    };
}

// Dùng lại text summary mặc định của k6.
import { textSummary } from 'https://jslib.k6.io/k6-summary/0.0.2/index.js';
