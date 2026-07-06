<?php

/**
 * Mô phỏng thuật toán chống oversell (guard-in-lock + reservation) mà
 * StockService triển khai — chạy độc lập, KHÔNG cần Laravel/DB, để kiểm chứng
 * phần logic số học dưới điều kiện "đồng thời" (các request được serialize qua
 * lock như lockForUpdate làm trong DB).
 *
 * Chạy:  php storage/_oversell_sim.php
 */

const POLICY_DENY = 0;
const POLICY_BACKORDER = 1;

/** Kho trong bộ nhớ — đại diện 1 row product_stock được khoá khi ghi. */
class Stock
{
    public function __construct(
        public int $onHand,
        public int $reserved = 0,
        public int $policy = POLICY_DENY,
        public bool $enforce = true,
    ) {}
}

class InsufficientStock extends \RuntimeException {}

/** Giữ chỗ 1 lượng cho 1 holder (idempotent theo holder qua $prev). */
function reserveOne(Stock $s, int $prev, int $want): array
{
    if ($s->policy !== POLICY_DENY) {
        return ['ok' => true, 'reserved' => $prev];
    }
    $maxForHolder = $s->onHand - ($s->reserved - $prev);
    if ($want > $maxForHolder) {
        return ['ok' => false, 'available' => max(0, $maxForHolder)];
    }
    $s->reserved += ($want - $prev);
    return ['ok' => true, 'reserved' => $want];
}

/** Trừ tồn khi tạo đơn — GUARD chặn oversell với policy DENY. */
function deductForOrder(Stock $s, int $qty, int $heldByThisHolder): void
{
    $newOnHand = $s->onHand - $qty;
    if ($newOnHand < 0 && $s->policy === POLICY_DENY && $s->enforce) {
        throw new InsufficientStock("oversell blocked: onHand={$s->onHand} qty={$qty}");
    }
    $release = min($heldByThisHolder, max(0, $qty));
    $s->onHand = $newOnHand;
    $s->reserved = max(0, $s->reserved - $release);
}

function assertTrue(bool $c, string $msg): void
{
    if (! $c) { fwrite(STDERR, "FAIL: $msg\n"); exit(1); }
    echo "  ok: $msg\n";
}

// ---------------------------------------------------------------------------
echo "== Test 1: 100 đơn tranh 10 tồn (DENY) — không được oversell ==\n";
$s = new Stock(onHand: 10);
$success = 0; $rejected = 0;
for ($i = 0; $i < 100; $i++) {          // serialize như lockForUpdate
    try { deductForOrder($s, 1, 0); $success++; }
    catch (InsufficientStock) { $rejected++; }
}
assertTrue($s->onHand === 0, "on_hand không âm (=".$s->onHand.")");
assertTrue($success === 10, "đúng 10 đơn thành công (=$success)");
assertTrue($rejected === 90, "90 đơn bị chặn (=$rejected)");

echo "== Test 2: mua nhiều hơn tồn trong 1 đơn — bị chặn, tồn giữ nguyên ==\n";
$s = new Stock(onHand: 3);
try { deductForOrder($s, 5, 0); assertTrue(false, "phải ném exception"); }
catch (InsufficientStock) { assertTrue(true, "ném InsufficientStock khi qty>onHand"); }
assertTrue($s->onHand === 3, "on_hand không đổi sau khi rollback (=".$s->onHand.")");

echo "== Test 3: BACKORDER được phép âm (bán khống có chủ đích) ==\n";
$s = new Stock(onHand: 2, policy: POLICY_BACKORDER);
deductForOrder($s, 5, 0);
assertTrue($s->onHand === -3, "backorder cho phép on_hand=-3 (=".$s->onHand.")");

echo "== Test 4: reservation giảm sellable & không tự chặn chính chủ ==\n";
$s = new Stock(onHand: 1);
$r = reserveOne($s, 0, 1);                       // A giữ 1
assertTrue($r['ok'] === true, "A giữ được 1");
assertTrue($s->reserved === 1, "reserved=1");
$sellableForOther = $s->onHand - $s->reserved;   // người khác thấy 0
assertTrue($sellableForOther === 0, "khách khác thấy sellable=0");
$sellableForA = $s->onHand - $s->reserved + 1;   // cộng ngược hold của A
assertTrue($sellableForA === 1, "A vẫn mua được 1 (không tự chặn)");
// B cố giữ khi đã hết
$r2 = reserveOne($s, 0, 1);
assertTrue($r2['ok'] === false, "B không giữ được (available=".$r2['available'].")");

echo "== Test 5: A đặt đơn từ hold — on_hand & reserved nhất quán ==\n";
deductForOrder($s, 1, 1);                          // A consume hold của mình
assertTrue($s->onHand === 0, "on_hand=0 sau khi A mua (=".$s->onHand.")");
assertTrue($s->reserved === 0, "reserved trả về 0 (=".$s->reserved.")");

echo "== Test 6: hold hết hạn được nhả lại làm tồn khả bán phục hồi ==\n";
$s = new Stock(onHand: 5);
reserveOne($s, 0, 3);                               // giữ 3
assertTrue($s->onHand - $s->reserved === 2, "sellable=2 khi đang giữ 3");
$s->reserved = max(0, $s->reserved - 3);           // releaseExpired nhả 3
assertTrue($s->onHand - $s->reserved === 5, "sellable phục hồi=5 sau khi nhả hạn");

echo "\nALL SIMULATION TESTS PASSED\n";
