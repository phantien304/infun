<?php

namespace App\Models\Traits;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

trait HasAdvancedScopes
{
    public function scopeWithKeysIn(Builder $query, array $keys)
    {
        $keyNames = (array) $this->getKeyName();

        if (array_is_list($keys)) {
            return $query->whereIn($this->getKeyName(true), $keys);
        }

        foreach ($keys as $col => $values) {
            if (in_array($col, $keyNames, true)) {
                $query->whereIn($col, (array) $values);
            }
        }

        return $query;
    }
    public function scopeWithParentKeysIn(Builder $query, array $keys)
    {
        if (array_is_list($keys)) {
            return $query->whereIn($this->getKeyName(true), $keys);
        }

        foreach ($keys as $col => $values) {
            $query->whereIn($col, (array) $values);
        }

        return $query;
    }
    public function scopeDateStartToEnd(Builder $query): Builder
    {
        $now   = Carbon::now();
        $table = $this->getTable();

        return $query
            ->where(
                fn(Builder $q) => $q
                    ->where("{$table}.date_start", '<=', $now)
                    ->orWhereNull("{$table}.date_start")
            )
            ->where(
                fn(Builder $q) => $q
                    ->where("{$table}.date_end", '>', $now)
                    ->orWhereNull("{$table}.date_end")
            );
    }
    public function scopeDateAvailable(Builder $query)
    {
        if (getUserType() === getCoreConfig('user.type.admin')) return $query;

        return $query->where(function ($q) {
            $q->where('date_available', '<=', now())->orWhereNull('date_available');
        });
    }
    public function scopeForLocale(Builder $query, string $table = '')
    {
        $col = $table ? "{$table}.language_code" : 'language_code';

        return $query->where($col, app()->getLocale());
    }
    public function scopeLanguageCode(Builder $query, string $table = '')
    {
        return $this->scopeForLocale($query, $table);
    }
    public function scopeForUserGroup(Builder $query, int $userGroupId): Builder
    {
        return $query->where("{$this->getTable()}.user_group_id", $userGroupId);
    }
}
