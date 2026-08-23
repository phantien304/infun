<?php

namespace App\Models\Traits;

use Carbon\Carbon;

trait HasCascadeRelations
{
    protected static array $destroyRelations = [];
    protected static array $updateRelations  = [];
    protected ?Carbon $cascadeUpdateTime = null;
    protected array $delRelationsTmp = [];
    public static function bootHasCascadeRelations(): void
    {
        static::deleting(function (self $model) {
            $action = method_exists($model, 'isForceDeleting') && $model->isForceDeleting()
                ? 'forceDelete'
                : 'delete';

            $model->runCascadeDestroy($action, $model);
        });
        static::updating(function (self $model) {
            $model->cascadeUpdateTime = Carbon::now();
            $model->runCascadeUpdate($model);
        });
    }
    public static function getDestroyRelations(): array
    {
        return static::$destroyRelations;
    }
    public static function setDestroyRelations(array $destroyRelations): void
    {
        static::$destroyRelations = $destroyRelations;
    }
    public static function getUpdateRelations(): array
    {
        return (static::$updateRelations[0] ?? null) === 'byDestroy'
            ? static::getDestroyRelations()
            : static::$updateRelations;
    }
    public static function setUpdateRelations(array $updateRelations): void
    {
        static::$updateRelations = $updateRelations;
    }
    public function getDelRelationsTmp(): array
    {
        return $this->delRelationsTmp;
    }
    public function setDelRelationsTmp(array $delRelationsTmp): void
    {
        $this->delRelationsTmp = $delRelationsTmp;
    }
    protected function runCascadeDestroy(string $action, self $entity, array $parentIds = [], bool $isFirst = true, int $depth = 0): bool
    {
        if ($depth > 5) {
            throw new \RuntimeException('Max cascade depth (5) exceeded on ' . static::class);
        }
        $relations = $entity::getDestroyRelations();
        if (empty($relations)) {
            return true;
        }
        if ($isFirst) {
            $parentIds = $entity->getKeyWithName();
        }
        foreach ($relations as $relation) {
            $related           = $entity->$relation()->getRelated();
            $relatedRelations  = $related::getDestroyRelations();
            if (empty($relatedRelations)) {
                if (!empty($parentIds)) {
                    $this->cascadeDestroyLeaf($action, $entity, $relation, $parentIds);
                }
                continue;
            }
            $childIds = $this->resolveChildIds($entity, $relation, $parentIds, $relatedRelations);
            if (empty($childIds)) {
                continue;
            }
            $this->runCascadeDestroy($action, $related, $childIds, false, $depth + 1);
        }
        if ($isFirst || empty($parentIds)) {
            return true;
        }
        return $entity->withKeysIn($parentIds)->$action();
    }
    protected function cascadeDestroyLeaf(string $action, self $parent, string $relation, array $parentKeys): void
    {
        $fkMap = $this->buildForeignKeyMap($parent, $relation, $parentKeys);

        $parent->$relation()->getRelated()->scopeWithParentKeysIn(
            $parent->$relation()->getRelated()->newQuery(),
            $fkMap
        )->$action();
    }
    protected function runCascadeUpdate(self $entity, array $parentIds = [], bool $isFirst = true, int $depth = 0): bool
    {
        if ($depth > 5) {
            throw new \RuntimeException('Max cascade depth (5) exceeded on ' . static::class);
        }
        $relations = $entity::getUpdateRelations();

        if (empty($relations)) {
            return true;
        }
        if ($isFirst) {
            $parentIds = $entity->getKeyWithName();
        }
        foreach ($relations as $relation) {
            $related          = $entity->$relation()->getRelated();
            $relatedRelations = $related::getUpdateRelations();
            if (empty($relatedRelations)) {
                if (!empty($parentIds)) {
                    $this->cascadeUpdateLeaf($entity, $relation, $parentIds);
                }
                continue;
            }
            $childIds = $this->resolveChildIds($entity, $relation, $parentIds, $relatedRelations);
            if (empty($childIds)) {
                continue;
            }
            $this->runCascadeUpdate($related, $childIds, false, $depth + 1);
        }

        if ($isFirst || empty($parentIds)) {
            return true;
        }

        return $entity->withKeysIn($parentIds)
            ->update([getSystemConfig('updated_at_column.field') => $this->cascadeUpdateTime]);
    }
    protected function cascadeUpdateLeaf(self $parent, string $relation, array $parentKeys): void
    {
        $fkMap = $this->buildForeignKeyMap($parent, $relation, $parentKeys);
        $parent->$relation()->getRelated()->scopeWithParentKeysIn(
            $parent->$relation()->getRelated()->newQuery(),
            $fkMap
        )->update([getSystemConfig('updated_at_column.field') => $this->cascadeUpdateTime]);
    }
    protected function resolveChildIds(self $entity, string $relation, array $parentIds, array $childRelations): array
    {
        $related    = $entity->$relation()->getRelated();
        $fkMap      = $this->buildForeignKeyMap($entity, $relation, $parentIds);
        $fkCols     = $this->resolveParentKeyNames($entity, $relation);

        $selectCols = $this->resolveSelectColumns($related, $childRelations, $fkCols);
        $query = $related->newQuery();
        foreach ($fkMap as $col => $values) {
            $query->whereIn($col, (array) $values);
        }
        /**
         * BUG đã fix (2026-08): `array_merge_recursive($result, (array)$row->
         * getAttributes())` chỉ "gói" giá trị trùng cột thành mảng khi CÓ va
         * chạm key giữa 2 lần merge — với ĐÚNG 1 dòng con khớp (vd carrier
         * chỉ có 1 CarrierOrderStatus, mà CarrierOrderStatus lại có tiếp
         * $destroyRelations riêng nên đi vào nhánh resolveChildIds() này),
         * $result nhận nguyên giá trị SCALAR (vd ['id' => 30]) thay vì mảng
         * (['id' => [30]]) → array_map('array_unique', $result) ném
         * TypeError "must be of type array, int given" ngay khi soft-delete
         * — chặn đứng việc xoá Carrier có con (đã tái hiện thật lúc build
         * tính năng Carrier CRUD). Sửa bằng cách gom giá trị từng cột vào
         * mảng TƯỜNG MINH thay vì dựa vào hành vi ẩn của array_merge_recursive
         * — cùng shape trả về [col => [values...]] như code cũ ở trường hợp
         * ≥2 dòng (không đổi hành vi cho các model khác đang dùng trait này).
         */
        $result = [];
        $query->select($selectCols)->get()->each(function ($row) use (&$result) {
            foreach ($row->getAttributes() as $col => $value) {
                $result[$col][] = $value;
            }
        });

        return array_map('array_unique', $result);
    }
    protected function buildForeignKeyMap(self $parent, string $relation, array $parentKeys): array
    {
        $parentKeyNames  = $this->resolveParentKeyNames($parent, $relation);
        $foreignKeyNames = $this->resolveForeignKeyNames($parent, $relation);
        $map = [];
        foreach ($foreignKeyNames as $idx => $fk) {
            $pk       = $parentKeyNames[$idx] ?? null;
            $map[$fk] = $pk ? ($parentKeys[$pk] ?? []) : [];
        }
        return $map;
    }
    protected function resolveParentKeyNames(self $entity, string $relation): array
    {
        $table = $entity->getTable();
        return array_map(
            fn($k) => str_replace("{$table}.", '', $k),
            (array) $entity->$relation()->getQualifiedParentKeyName()
        );
    }
    protected function resolveForeignKeyNames(self $entity, string $relation): array
    {
        $table = $entity->$relation()->getRelated()->getTable();

        return array_map(
            fn($k) => str_replace("{$table}.", '', $k),
            (array) $entity->$relation()->getQualifiedForeignKeyName()
        );
    }
    protected function resolveSelectColumns(self $entity, array $relations, array  $otherKeys = []): array
    {
        if (empty($relations)) {
            return [];
        }

        $cols = array_merge_recursive($otherKeys, (array) $entity->getKeyName());
        foreach ($relations as $relation) {
            $cols = array_merge_recursive(
                $cols,
                (array) $entity->$relation()->getQualifiedParentKeyName()
            );
        }
        $table = $entity->getTable();
        return array_unique(array_map(
            fn($k) => str_replace("{$table}.", '', $k),
            $cols
        ));
    }
    public function getRelationOrNew(string $relation, int $offset = 0, array $attr = [], bool $resetOffset = false): mixed
    {
        if (!$this->getKey() && empty($this->relationsToArray())) {
            return $this->{$relation}()->getRelated()->setRawAttributes($attr);
        }

        try {
            $result = $this->getRelationValue($relation);

            if (isCollection($result) && $resetOffset) {
                $result = $result->values();
            }

            $entity = isCollection($result) ? $result->offsetGet($offset) : $result;

            if ($entity) {
                return $entity;
            }
        } catch (\Exception) {
        }

        return $this->{$relation}()->getRelated()->setRawAttributes($attr);
    }
    public function tryGet(string $relation, int $offset = 0, bool $resetOffset = false): mixed
    {
        return $this->getRelationOrNew($relation, $offset, [], $resetOffset);
    }
}
