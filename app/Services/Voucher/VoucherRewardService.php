<?php

namespace App\Services\Voucher;

use App\Enums\VoucherStatus;
use App\Jobs\VoucherRewardSendEmailJob;
use App\Models\Entities\Orders;
use App\Models\Entities\Voucher;
use App\Models\Entities\VoucherRewardGrant;
use App\Models\Entities\VoucherRewardRule;
use App\Repositories\Interfaces\VoucherRepositoryInterface;
use App\Repositories\Interfaces\VoucherRewardGrantRepositoryInterface;
use App\Repositories\Interfaces\VoucherRewardRuleRepositoryInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use RuntimeException;

class VoucherRewardService
{
    protected const CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    protected const CODE_LENGTH = 12;
    protected const CODE_MAX_ATTEMPTS = 5;
    private const ROLLBACK_SENTINEL = '__voucher_reward_rollback__';

    public function __construct(
        protected VoucherRewardRuleRepositoryInterface $voucherRewardRuleRepo,
        protected VoucherRewardGrantRepositoryInterface $voucherRewardGrantRepo,
        protected VoucherRepositoryInterface $voucherRepo,
    ) {
    }

    public function grantForOrder(Orders $order): void
    {
        $email = trim(strtolower((string) $order->email));
        if ($email === '') {
            logInfo(sprintf('VoucherReward: order %d không có email — bỏ qua grant.', (int) $order->id));
            return;
        }

        $rewardGrants = $this->voucherRewardGrantRepo
            ->rewardGrantsForOrder((int) $order->id)
            ->keyBy(fn (VoucherRewardGrant $grant) => (int) $grant->rule_id);

        foreach ($this->voucherRewardRuleRepo->listRunningRewardRules() as $rewardRule) {
            $this->grantOne($rewardRule, $order, $email, $rewardGrants);
        }
    }

    public function revokeForOrder(Orders $order): void
    {
        $revoked = 0;
        foreach ($this->voucherRewardGrantRepo->rewardGrantsForOrder((int) $order->id) as $grant) {
            $voucher = $grant->voucher;
            if (! $voucher) {
                continue;
            }

            if ((float) $voucher->redeemed_balance > 0) {
                logError(sprintf(
                    'VoucherReward: order %d hủy nhưng voucher %s (id %d) đã dùng %.0f/%.0f — CS xử lý tay.',
                    (int) $order->id,
                    (string) $voucher->code,
                    (int) $voucher->id,
                    (float) $voucher->redeemed_balance,
                    (float) $voucher->amount,
                ));

                continue;
            }

            $affected = $this->voucherRewardRuleRepo->transaction(function () use ($grant, $voucher) {
                $affected = $this->voucherRepo->revokeUnused(
                    [(int) $voucher->id],
                    VoucherStatus::Active->value,
                    VoucherStatus::Revoked->value,
                );
                if ($affected > 0) {
                    $this->voucherRewardRuleRepo->decrementRewardRuleCount((int) $grant->rule_id, $affected);
                }

                return $affected;
            });
            $revoked += (int) $affected;
        }

        if ($revoked > 0) {
            $this->voucherRepo->flushCache();
        }
    }

    protected function grantOne(VoucherRewardRule $rewardRule, Orders $order, string $email, Collection $rewardGrants): void
    {
        if (! $rewardRule->matchesOrderTotal((float) $order->total)) {
            return;
        }

        $rewardGrant = $rewardGrants->get((int) $rewardRule->id);
        if ($rewardGrant !== null) {
            $this->reactivateVoucherIfRevoked($rewardRule, $rewardGrant);

            return;
        }

        $userId = (int) ($order->user_id ?? 0);
        $userId = $userId > 0 ? $userId : null;

        if ($rewardRule->max_per_user !== null
            && $this->voucherRewardGrantRepo->countRewardGrantsForUser((int) $rewardRule->id, $userId, $email) >= (int) $rewardRule->max_per_user) {
            return;
        }

        try {
            $voucher = $this->voucherRewardRuleRepo->transaction(function () use ($rewardRule, $order, $email, $userId) {
                if ($this->voucherRewardRuleRepo->incrementRewardRuleCount((int) $rewardRule->id) === 0) {
                    return null;
                }

                $voucher = $this->createVoucher($rewardRule, $order, $email);

                $this->voucherRewardGrantRepo->createRewardGrant([
                    'rule_id'    => (int) $rewardRule->id,
                    'order_id'   => (int) $order->id,
                    'user_id'    => $userId,
                    'email'      => $email,
                    'voucher_id' => (int) $voucher->id,
                ]);

                return $voucher;
            });
        } catch (QueryException $e) {
            if ($this->isDuplicateKey($e)) {
                return;
            }
            throw $e;
        }

        if ($voucher === null) {
            logInfo(sprintf('VoucherReward: rule %d hết quota — order %d không được tặng.', (int) $rewardRule->id, (int) $order->id));

            return;
        }

        $this->voucherRepo->flushCache();
        $this->notifyGranted($voucher);
    }

    protected function reactivateVoucherIfRevoked(VoucherRewardRule $rewardRule, VoucherRewardGrant $rewardGrant): void
    {
        $voucher = $rewardGrant->voucher;
        if (! $voucher
            || (int) $voucher->status !== VoucherStatus::Revoked->value
            || (float) $voucher->redeemed_balance > 0) {
            return;
        }

        try {
            $this->voucherRewardRuleRepo->transaction(function () use ($rewardRule, $voucher) {
                if ($this->voucherRewardRuleRepo->incrementRewardRuleCount((int) $rewardRule->id) === 0) {
                    return;
                }

                $affected = $this->voucherRepo->reactivateRevoked(
                    [(int) $voucher->id],
                    VoucherStatus::Revoked->value,
                    VoucherStatus::Active->value,
                );
                if ($affected === 0) {
                    throw new RuntimeException(self::ROLLBACK_SENTINEL);
                }
            });
            $this->voucherRepo->flushCache();
        } catch (RuntimeException $e) {
            if ($e->getMessage() !== self::ROLLBACK_SENTINEL) {
                throw $e;
            }
        }
    }

    protected function createVoucher(VoucherRewardRule $rewardRule, Orders $order, string $email): Voucher
    {
        return $this->voucherRepo->createVoucher([
            'order_id'         => null,
            'code'             => $this->generateUniqueCode(),
            'from_name'        => (string) getConfigDb('config_name'),
            'from_email'       => (string) getConfigDb('config_email'),
            'to_name'          => (string) $order->full_name,
            'to_email'         => $email,
            'message'          => (string) $rewardRule->name,
            'amount'           => (float) $rewardRule->reward_amount,
            'status'           => VoucherStatus::Active->value,
            'redeemed_balance' => 0,
            'date_expire'      => Carbon::now()->addDays((int) $rewardRule->reward_expire_days)->toDateString(),
        ]);
    }

    protected function generateUniqueCode(): string
    {
        $alphabetMax = strlen(self::CODE_ALPHABET) - 1;

        for ($attempt = 0; $attempt < self::CODE_MAX_ATTEMPTS; $attempt++) {
            $code = '';
            for ($i = 0; $i < self::CODE_LENGTH; $i++) {
                $code .= self::CODE_ALPHABET[random_int(0, $alphabetMax)];
            }

            if (! $this->voucherRepo->findByCode($code)) {
                return $code;
            }
        }

        throw new RuntimeException('VoucherReward: không sinh được code unique sau ' . self::CODE_MAX_ATTEMPTS . ' lần.');
    }

    protected function isDuplicateKey(QueryException $e): bool
    {
        return (int) ($e->errorInfo[1] ?? 0) === 1062; // MySQL/MariaDB ER_DUP_ENTRY
    }

    protected function notifyGranted(Voucher $voucher): void
    {
        dispatch(new VoucherRewardSendEmailJob((int) $voucher->id));
    }
}
