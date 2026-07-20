<?php

namespace App\Services\Stock;

use Illuminate\Redis\Connections\Connection;
use Illuminate\Redis\Connections\PredisConnection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * Redis atomic gate cho flash-sale (docs/FLASH-GATE.md).
 *
 * Vai trò: admission control TRƯỚC khi mở transaction DB trong
 * StockService::reserveCheckout. Variant được seed quota (opt-in per-variant,
 * `flash-gate:seed`) thì chỉ người "có suất" mới đi tiếp vào đường reservation
 * DB; hết suất bị từ chối bằng 1 lệnh Lua (~µs) — N nghìn request không còn
 * xếp hàng giữ php-fpm worker + connection chờ `FOR UPDATE`.
 *
 * Gate KHÔNG thay thế DB lock — chỉ giảm tranh chấp. Chống oversell cuối vẫn
 * là `deductForOrder`/`reserveOne` dưới lock.
 *
 * An toàn:
 *  - FAIL-OPEN: mọi lỗi Redis → log warning → trả null (coi như not-gated).
 *  - Lua atomic: không có race DECR-âm-rồi-INCR-trả.
 *  - release() chỉ credit khi key còn tồn tại → không hồi sinh key đã teardown.
 *  - Key nằm trên connection noeviction (config flash_gate.redis_connection).
 */
class FlashGateService
{
    /** KEYS[1]=quota, ARGV[1]=qty. Trả -1: không gated, 1: lấy được, 0: hết suất. */
    private const LUA_ACQUIRE = <<<'LUA'
local v = redis.call('GET', KEYS[1])
if v == false then return -1 end
if tonumber(v) >= tonumber(ARGV[1]) then
    redis.call('DECRBY', KEYS[1], ARGV[1])
    return 1
end
return 0
LUA;

    /** Credit chỉ khi key tồn tại (không hồi sinh key đã teardown). Trả -1 nếu không gated. */
    private const LUA_RELEASE = <<<'LUA'
if redis.call('EXISTS', KEYS[1]) == 1 then
    return redis.call('INCRBY', KEYS[1], ARGV[1])
end
return -1
LUA;

    /**
     * Reconcile: CHỈ clamp xuống (gate > db_sellable → SET db_sellable).
     * Không tự nâng — CMS nhập thêm hàng thì chạy lại flash-gate:seed.
     * Trả số suất đã cắt (0 = không lệch, -1 = không gated).
     */
    private const LUA_CLAMP_DOWN = <<<'LUA'
local v = redis.call('GET', KEYS[1])
if v == false then return -1 end
v = tonumber(v)
local target = tonumber(ARGV[1])
if v > target then
    redis.call('SET', KEYS[1], target)
    return v - target
end
return 0
LUA;

    public function enabled(): bool
    {
        return (bool) config('flash_gate.enabled');
    }

    public function isGated(int $variantId): ?bool
    {
        try {
            return (bool) $this->redis()->exists($this->key($variantId));
        } catch (\Throwable $e) {
            $this->warn('isGated', $variantId, $e);

            return null;
        }
    }

    /**
     * Xin $qty suất. true = có suất (đã DECRBY), false = HẾT suất (chặn),
     * null = variant không gated / gate tắt / Redis lỗi → caller đi đường DB.
     */
    public function tryAcquire(int $variantId, int $qty): ?bool
    {
        if (! $this->enabled() || $qty <= 0) {
            return null;
        }

        try {
            $r = (int) $this->evalLua(self::LUA_ACQUIRE, $this->key($variantId), $qty);

            return $r === -1 ? null : $r === 1;
        } catch (\Throwable $e) {
            $this->warn('tryAcquire', $variantId, $e);

            return null; // fail-open
        }
    }

    /**
     * Trả $qty suất (hold bị hủy/hết hạn/DB từ chối). Cố ý KHÔNG check
     * enabled — credit theo key tồn tại, để toggle env giữa sale không gây
     * drift một chiều. Lỗi Redis chỉ log (reconcile sẽ tự cân lại).
     */
    public function release(int $variantId, int $qty): void
    {
        if ($qty <= 0) {
            return;
        }

        try {
            $this->evalLua(self::LUA_RELEASE, $this->key($variantId), $qty);
        } catch (\Throwable $e) {
            $this->warn('release', $variantId, $e);
        }
    }

    /** Suất còn lại; null = không gated / Redis lỗi. */
    public function remaining(int $variantId): ?int
    {
        try {
            $v = $this->redis()->get($this->key($variantId));

            return $v === false || $v === null ? null : (int) $v;
        } catch (\Throwable $e) {
            $this->warn('remaining', $variantId, $e);

            return null;
        }
    }

    /** SET quota + ghi variant vào index. Gọi từ flash-gate:seed (đã tính sellable dưới lock DB). */
    public function seed(int $variantId, int $qty): void
    {
        $redis = $this->redis();
        $redis->set($this->key($variantId), max(0, $qty));
        $redis->sadd($this->indexKey(), $variantId);
    }

    /** DEL key + rút khỏi index → variant quay về đường DB thuần. */
    public function teardown(int $variantId): void
    {
        $redis = $this->redis();
        $redis->del($this->key($variantId));
        $redis->srem($this->indexKey(), $variantId);
    }

    /** Danh sách variant đang gated (đọc từ index SET — không SCAN). */
    public function gatedVariantIds(): array
    {
        try {
            return array_map('intval', (array) $this->redis()->smembers($this->indexKey()));
        } catch (\Throwable $e) {
            $this->warn('gatedVariantIds', 0, $e);

            return [];
        }
    }

    /** Clamp quota xuống $target. Trả số suất bị cắt, null = không gated / lỗi. */
    public function clampDown(int $variantId, int $target): ?int
    {
        try {
            $r = (int) $this->evalLua(self::LUA_CLAMP_DOWN, $this->key($variantId), max(0, $target));

            return $r === -1 ? null : $r;
        } catch (\Throwable $e) {
            $this->warn('clampDown', $variantId, $e);

            return null;
        }
    }

    public function key(int $variantId): string
    {
        return config('flash_gate.key_prefix', 'gate:stock:') . $variantId;
    }

    public function indexKey(): string
    {
        return config('flash_gate.key_prefix', 'gate:stock:') . 'index';
    }

    private function redis(): Connection
    {
        return Redis::connection((string) config('flash_gate.redis_connection', 'default'));
    }

    private function evalLua(string $script, string $key, int|string ...$args): mixed
    {
        $connection = $this->redis();

        if ($connection instanceof PredisConnection) {
            return $connection->eval($script, 1, $key, ...$args);
        }

        return $connection->eval($script, array_merge([$key], $args), 1);
    }

    private function warn(string $op, int $variantId, \Throwable $e): void
    {
        try {
            Log::channel(config('flash_gate.log_channel'))
                ->warning("FlashGate {$op} fail-open (variant {$variantId}): " . $e->getMessage());
        } catch (\Throwable) {
            // logging không được phép làm gãy checkout
        }
    }
}
