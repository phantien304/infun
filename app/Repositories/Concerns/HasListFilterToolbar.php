<?php

namespace App\Repositories\Concerns;

use Illuminate\Support\Arr;
use Spatie\QueryBuilder\AllowedSort;

trait HasListFilterToolbar
{
    protected function sortMenu(): array
    {
        return [];
    }
    protected function perPageOptions(): array
    {
        return [50, 100, 150, 200];
    }
    public function getSortMenu(): array
    {
        $menu = $this->sortMenu();
        if ($menu === []) {
            return [];
        }

        $allowed = $this->allowedSortNames();
        $current = (string) (request()->query('sort') ?: $menu[0]);
        $items   = [];

        foreach ($menu as $token) {
            $token  = (string) $token;
            $column = ltrim($token, '-');
            if (!in_array($column, $allowed, true)) {
                if (config('app.debug')) {
                    throw new \LogicException(sprintf(
                        "Sort '%s' chưa được khai báo trong allowedSorts() của %s",
                        $token,
                        static::class
                    ));
                }
                continue;
            }

            $items[] = [
                'token'  => $token,
                'label'  => $this->sortLabel($token),
                'active' => $current === $token,
                'url'    => $this->buildListUrl(['sort' => $token]),
            ];
        }

        return $items;
    }
    public function getPerPageMenu(): array
    {
        $current = (int) request()->query('per_page', $this->defaultPerPage);
        $items   = [];

        foreach ($this->perPageOptions() as $size) {
            $size = (int) $size;

            $items[] = [
                'value'  => $size,
                'active' => $current === $size,
                'url'    => $this->buildListUrl(['per_page' => $size]),
            ];
        }

        return $items;
    }
    protected function allowedSortNames(): array
    {
        return array_map(
            fn($sort) => $sort instanceof AllowedSort ? $sort->getName() : (string) $sort,
            $this->allowedSorts()
        );
    }
    protected function sortLabel(string $token): string
    {
        $key   = 'sort.' . $token;
        $label = trans($key);

        return $label === $key ? $token : $label;
    }
    protected function buildListUrl(array $params): string
    {
        $query = array_merge(Arr::except(request()->query(), 'page'), $params);

        return request()->url() . '?' . http_build_query($query);
    }
}
