<?php

namespace App\Models\Traits;

trait HasAuditColumns
{
    protected bool $stopFillActionAt = true;
    protected bool $hasActionBy = false;
    protected bool $allowOverride = true;

    private static array $columnCache = [];
    private ?int $cachedUserId = null;

    public static function bootHasAuditColumns(): void
    {
        $class = static::class;
        if (!isset(self::$columnCache[$class])) {
            self::$columnCache[$class] = [
                'created_by' => getCreatedByColumn() ?: null,
                'updated_by' => getUpdatedByColumn() ?: null,
                'deleted_by' => getDeletedByColumn() ?: null,
            ];
        }
        static::bootAuditByColumns();
    }

    private function col(string $key): ?string
    {
        return self::$columnCache[static::class][$key] ?? null;
    }
    private function currentUserId(): ?int
    {
        return $this->cachedUserId ??= getCurrentUserId();
    }
    protected function allowFillActionAt(): bool
    {
        return $this->stopFillActionAt;
    }
    protected function allowFillActionBy(): bool
    {
        return $this->hasActionBy && $this->allowOverride;
    }
    public function isHasActionBy(): bool
    {
        return $this->hasActionBy;
    }
    public function setHasActionBy(bool $v): void
    {
        $this->hasActionBy = $v;
    }
    public function isStopFillActionAt(): bool
    {
        return $this->stopFillActionAt;
    }
    public function setStopFillActionAt(bool $v): static
    {
        $this->stopFillActionAt = $v;
        return $this;
    }
    public function isAllowOverride(): bool
    {
        return $this->allowOverride;
    }
    public function setAllowOverride(bool $v): void
    {
        $this->allowOverride = $v;
    }
    public static function bootAuditByColumns(): void
    {
        static::creating(function (self $model) {
            if ($model->allowFillActionBy() && $col = $model->col('created_by')) {
                $model->attributes[$col] = $model->currentUserId();
            }
        });

        static::updating(function (self $model) {
            $model->fillUpdatedBy();
        });
    }
    public function setDeletedAt($value): static
    {
        $this->attributes['deleted_at'] = $value;
        $this->fillDeletedBy();
        return $this;
    }
    public function fillUpdatedBy(bool $fromRelation = false): static
    {
        if ((!$this->isEmptyKey() || $fromRelation)
            && $this->allowFillActionBy()
            && $col = $this->col('updated_by')
        ) {
            $this->attributes[$col] = $this->currentUserId();
        }
        return $this;
    }
    public function fillDeletedBy(): static
    {
        if ($this->allowFillActionBy() && $col = $this->col('deleted_by')) {
            $this->attributes[$col] = $this->currentUserId();
        }
        return $this;
    }
}
