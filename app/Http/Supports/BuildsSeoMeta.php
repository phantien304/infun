<?php

namespace App\Http\Supports;

trait BuildsSeoMeta
{
    protected function processMetaSeo(string|callable $func, string $title, string $description)
    {
        list($titleSeo, $descriptionSeo, $linkCanonical) = is_callable($func)
            ? $func($title, $description)
            : $this->{$func}($title, $description);
        $this->setViewData([
            'titleSeo' => $titleSeo,
            'descriptionSeo' => $descriptionSeo,
            'linkCanonical' => $linkCanonical,
        ]);
    }
    protected function buildForSeoBySetting(string $keyTitle, string $keyDescription): array
    {
        return $this->appendPageInfo(
            getConfigDb($keyTitle),
            getConfigDb($keyDescription)
        );
    }
    protected function buildForSeoByConfig(string $keyTitle, string $keyDescription): array
    {
        return $this->appendPageInfo(
            trans('messages.seo.' . $keyTitle),
            trans('messages.seo.' . $keyDescription)
        );
    }
    protected function buildForSeoByData(string $title, string $description): array
    {
        return $this->appendPageInfo($title, $description);
    }
    private function appendPageInfo(string $title, string $description): array
    {
        $page = (int) data_get($this->getParams(), 'page', 1);
        $link = $this->getCanonicalLink();
        if ($page <= 1) {
            return [$title, $description, $link];
        }
        $suffix = ' - Trang ' . $page;
        return [
            $title . $suffix,
            $description . $suffix,
            $link . '?page=' . $page,
        ];
    }
    private function getCanonicalLink(): string
    {
        return str_replace('public/index.php/', '', request()->url());
    }
}
