-- ═══════════════════════════════════════════════════════════════════════════
-- VERIFY OVERSELL + GATE-LEAK sau khi chạy k6 (SCALE-30K mục D / FLASH-GATE Phase 4)
-- Chạy trên MASTER (không phải replica — cần số cuối cùng, không chịu lag):
--   mysql -h <master> -u root -p infun < k6/verify-oversell.sql
-- Đặt @variant_id / @initial_on_hand theo variant flash đã seed trước test.
-- ═══════════════════════════════════════════════════════════════════════════

SET @variant_id      = 0;   -- SỬA: default variant của FLASH_PRODUCT_ID
SET @initial_on_hand = 50;  -- SỬA: tồn đã set trước khi chạy test

-- ── 1. BẤT BIẾN TOÀN CỤC: không row nào âm / reserved vượt on_hand ──────────
-- Kỳ vọng: 0 dòng. Có dòng = có bug đường trừ kho (trừ policy Backorder chủ ý
-- và config_stock_checkout tắt — 2 đường DUY NHẤT được phép âm).
SELECT 'VIOLATION: on_hand < 0'  AS what, ps.product_variant_id, ps.warehouse_id, ps.on_hand, ps.reserved
FROM product_stock ps WHERE ps.on_hand < 0
UNION ALL
SELECT 'VIOLATION: reserved > on_hand', ps.product_variant_id, ps.warehouse_id, ps.on_hand, ps.reserved
FROM product_stock ps WHERE ps.reserved > GREATEST(ps.on_hand, 0)
UNION ALL
SELECT 'VIOLATION: reserved < 0', ps.product_variant_id, ps.warehouse_id, ps.on_hand, ps.reserved
FROM product_stock ps WHERE ps.reserved < 0;

-- ── 2. OVERSELL variant flash: Σ qty đơn thành công ≤ tồn ban đầu ───────────
-- ("thành công" = mọi đơn đã ghi — đơn cancel test không hoàn kho tự động)
SELECT
    @initial_on_hand                                        AS initial_on_hand,
    COALESCE(SUM(op.quantity), 0)                           AS total_sold,
    (SELECT COALESCE(SUM(ps.on_hand), 0) FROM product_stock ps
        WHERE ps.product_variant_id = @variant_id)          AS on_hand_now,
    (SELECT COALESCE(SUM(ps.reserved), 0) FROM product_stock ps
        WHERE ps.product_variant_id = @variant_id)          AS reserved_now,
    (SELECT COALESCE(SUM(sr.quantity), 0) FROM stock_reservation sr
        WHERE sr.product_variant_id = @variant_id)          AS live_holds,
    CASE WHEN COALESCE(SUM(op.quantity), 0) <= @initial_on_hand
         THEN 'OK' ELSE '*** OVERSELL ***' END              AS verdict
FROM orders_product op
WHERE op.product_variant_id = @variant_id;

-- ── 3. ĐỐI SOÁT MOVEMENT: on_hand đầu − Σ sale = on_hand hiện tại ───────────
-- Lệch = có đường ghi kho ngoài StockService (đáng điều tra).
SELECT sm.type, COUNT(*) AS moves, SUM(sm.quantity_change) AS total_change
FROM stock_movement sm
WHERE sm.product_variant_id = @variant_id
GROUP BY sm.type ORDER BY sm.type;

-- ── 4. HOLD MỒ CÔI: reservation sống nhưng reserved đã về 0 ─────────────────
-- Kỳ vọng: 0 dòng (release-expired + delete-theo-PK đã chống double-release).
SELECT sr.product_variant_id, sr.warehouse_id,
       SUM(sr.quantity) AS hold_qty, MAX(ps.reserved) AS reserved
FROM stock_reservation sr
JOIN product_stock ps
  ON ps.product_variant_id = sr.product_variant_id AND ps.warehouse_id = sr.warehouse_id
GROUP BY sr.product_variant_id, sr.warehouse_id
HAVING hold_qty > reserved;

-- ── 5. GATE-LEAK (chỉ khi test flash-gate ON) ───────────────────────────────
-- Bất biến: gate_còn + Σ đã_bán + Σ hold_sống = giá_trị_seed
--   • gate_còn:  php artisan flash-gate:status <variant>  (cột "gate còn")
--   • Σ đã_bán:  total_sold ở query 2
--   • Σ hold_sống: live_holds ở query 2
-- Lệch DƯƠNG (tổng > seed) = leak credit (release thừa) — kiểm tra log
-- "FlashGate release". Lệch ÂM = leak debit (suất mất không ai dùng) —
-- reconcile sẽ KHÔNG tự sửa chiều này nếu gate < sellable, xem drift ở
-- flash-gate:status. Drift nhỏ ngay sau test là bình thường nếu còn hold
-- sống chưa hết hạn — chờ TTL + release-expired chạy rồi đo lại.
