<?php

namespace App\Repositories\Concerns;

use App\Helpers\CacheGate;
use Illuminate\Database\Eloquent\Model;

trait CacheableRepository
{
    protected function rememberCache(string $key, \Closure $resolver, $ttl = null, bool $perLocale = true, array $tags = [])
    {
        $store = CacheGate::store();
        if (! $store) {
            return $resolver();
        }
        return $this->doRemember($store, $key, $resolver, $ttl, $perLocale, $tags);
    }

    protected function rememberSystem(string $key, \Closure $resolver, $ttl = null, bool $perLocale = true)
    {
        return $this->doRemember(CacheGate::systemStore(), $key, $resolver, $ttl, $perLocale, []);
    }

    /**
     * Như rememberSystem() nhưng cho resolver trả Collection<Model> — lưu Redis
     * dạng mảng thuần (attributes + relations) thay vì để cache driver
     * serialize() nguyên object graph Eloquent. Đọc lại rehydrate Collection<Model>
     * y hệt bản gốc (kể cả relation đã eager-load) nên caller KHÔNG cần đổi gì.
     *
     * Lý do: unserialize() 1 object Eloquent phải tái tạo toàn bộ state nội bộ
     * (fillable/guarded/casts/relations/...) cho TỪNG instance — với collection
     * vài trăm row (category/manufacturer/filter/zone load mọi page) chi phí
     * CPU này nhân lên đáng kể dưới tải đồng thời (đo thực tế qua k6).
     * newFromBuilder() rẻ hơn nhiều vì chỉ gán thẳng attributes, đúng cách
     * Eloquent tự hydrate từ DB.
     */
    protected function rememberSystemModels(string $key, \Closure $resolver, $ttl = null, bool $perLocale = true): \Illuminate\Database\Eloquent\Collection
    {
        $rows = $this->rememberSystem($key, function () use ($resolver) {
            return $resolver()->map(fn (Model $model) => $this->modelToCacheArray($model))->all();
        }, $ttl, $perLocale);

        return new \Illuminate\Database\Eloquent\Collection(
            collect($rows)->map(fn (array $row) => $this->modelFromCacheArray($row))->all()
        );
    }

    private function modelToCacheArray(Model $model): array
    {
        return [
            'class' => get_class($model),
            'attributes' => $model->getAttributes(),
            'relations' => collect($model->getRelations())->map(function ($relation) {
                if ($relation instanceof \Illuminate\Database\Eloquent\Collection) {
                    return ['type' => 'many', 'items' => $relation->map(fn (Model $m) => $this->modelToCacheArray($m))->all()];
                }
                if ($relation instanceof Model) {
                    return ['type' => 'one', 'item' => $this->modelToCacheArray($relation)];
                }
                return ['type' => 'raw', 'value' => $relation];
            })->all(),
        ];
    }

    private function modelFromCacheArray(array $row): Model
    {
        $model = (new $row['class'])->newFromBuilder($row['attributes']);

        foreach ($row['relations'] as $name => $relation) {
            $model->setRelation($name, match ($relation['type']) {
                'many' => new \Illuminate\Database\Eloquent\Collection(
                    collect($relation['items'])->map(fn (array $r) => $this->modelFromCacheArray($r))->all()
                ),
                'one' => $this->modelFromCacheArray($relation['item']),
                default => $relation['value'],
            });
        }

        return $model;
    }

    protected function rememberEntity(
        Model $entity,
        string $prefix,
        \Closure $resolver,
        $ttl = null,
        array $tags = [],
        ?bool $perLocale = null,
    ) {
        $suffix = method_exists($entity, 'getKeyAsString')
            ? $entity->getKeyAsString()
            : (string) $entity->getKey();
        $perLocale ??= ! is_array($entity->getKeyName());
        return $this->rememberCache($prefix . $suffix, $resolver, $ttl, $perLocale, $tags);
    }

    protected function forgetCache(string $key, bool $perLocale = true): void
    {
        $store = CacheGate::store();
        if (! $store) {
            return;
        }
        $this->eachLocale($key, $perLocale, fn (string $k) => $store->forget($k));
    }

    protected function forgetCacheTagged(array $tags): void
    {
        $store = CacheGate::store();
        if (! $store || ! CacheGate::supportsTags($store)) {
            return;
        }
        $store->getStore()->tags(array_merge(CacheGate::globalTags(), $tags))->flush();
    }

    protected function forgetSystem(string $key, bool $perLocale = true): void
    {
        $store = CacheGate::systemStore();
        $this->eachLocale($key, $perLocale, fn (string $k) => $store->forget($k));
    }

    private function doRemember($store, string $key, \Closure $resolver, $ttl, bool $perLocale, array $tags)
    {
        $key = $this->localized($key, $perLocale);
        $ttl ??= $this->defaultTtl();
        if (! $tags || ! CacheGate::supportsTags($store)) {
            return $store->remember($key, $ttl, $resolver);
        }
        $allTags = array_merge(CacheGate::globalTags(), $tags);
        return $store->getStore()->tags($allTags)->remember($key, $ttl, $resolver);
    }

    private function defaultTtl(): \DateTimeInterface
    {
        return now()->addDays(30);
    }

    private function localized(string $key, bool $perLocale): string
    {
        return $perLocale ? $key . app()->getLocale() : $key;
    }

    private function eachLocale(string $key, bool $perLocale, callable $forget): void
    {
        if (! $perLocale) {
            $forget($key);
            return;
        }
        foreach (config('app.locales', [app()->getLocale()]) as $locale) {
            $forget($key . $locale);
        }
    }
}
