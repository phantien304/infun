<?php

namespace App\Helpers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;

class ThemeManager
{
    protected ?string $current = null;
    protected bool $applied = false;
    protected bool $fromQuery = false;
    protected ?string $view = null;
    protected ?string $signature = null;

    protected static function instance(): self
    {
        return app(static::class);
    }

    public static function resolve(Request $request): ?string
    {
        $self = self::instance();
        $self->fromQuery = false;

        if (config('theme.allow_query_override')) {
            $fromQuery = self::sanitize($request->query('theme'));
            if ($fromQuery !== null) {
                $self->fromQuery = true;

                return $fromQuery;
            }
        }

        $host = strtolower($request->getHost());
        $hosts = array_change_key_case((array) config('theme.hosts', []), CASE_LOWER);
        if (isset($hosts[$host])) {
            return self::sanitize($hosts[$host]);
        }

        return self::sanitize(config('theme.active'));
    }

    public static function sanitize(mixed $theme): ?string
    {
        if (! is_string($theme) || $theme === '') {
            return null;
        }

        return in_array($theme, (array) config('theme.available', []), true) ? $theme : null;
    }

    public static function current(): ?string
    {
        return self::instance()->current;
    }

    public static function isPreview(): bool
    {
        $self = self::instance();

        return $self->fromQuery && $self->current !== null;
    }

    public static function setView(?string $view): void
    {
        self::instance()->view = $view;
    }

    public static function applyForView(?string $view): void
    {
        $self = self::instance();
        $self->view = $view;

        self::apply($self->current, self::ownsView($view));
    }

    public static function ownsView(?string $view = null): bool
    {
        $self = self::instance();
        $theme = $self->current;
        $view ??= $self->view;

        if ($theme === null || $view === null) {
            return false;
        }

        if (! str_starts_with($view, 'web::')) {
            return false;
        }

        $relative = str_replace('.', '/', substr($view, 5));

        return is_file(self::viewsPath($theme) . DIRECTORY_SEPARATOR . $relative . '.blade.php');
    }

    public static function wants(string $block): bool
    {
        return in_array($block, self::resolvedBlocks(self::instance()->current), true);
    }

    private static function resolvedBlocks(?string $theme, array $chain = []): array
    {
        if ($theme === null || in_array($theme, $chain, true)) {
            return array_values((array) config('theme.blocks.base', []));
        }

        $diff   = (array) config('theme.blocks.' . $theme, []);
        $parent = is_string($diff['extends'] ?? null) ? $diff['extends'] : null;

        $blocks = self::resolvedBlocks($parent, [...$chain, $theme]);
        $blocks = array_values(array_diff($blocks, (array) ($diff['remove'] ?? [])));

        foreach ((array) ($diff['add'] ?? []) as $block) {
            if (! in_array($block, $blocks, true)) {
                $blocks[] = $block;
            }
        }

        return $blocks;
    }

    public static function viewsPath(string $theme): string
    {
        return rtrim((string) config('theme.path'), '/\\') . DIRECTORY_SEPARATOR . $theme . DIRECTORY_SEPARATOR . 'views';
    }

    public static function viteEntry(): string
    {
        $theme = self::instance()->current;
        if ($theme !== null && self::ownsView()) {
            $entry = "resources/themes/{$theme}/css/app.css";
            if (is_file(base_path($entry))) {
                return $entry;
            }
        }

        return 'resources/web/css/app.css';
    }

    public static function options(): array
    {
        $labels       = (array) config('theme.labels', []);
        $descriptions = (array) config('theme.descriptions', []);
        $swatches     = (array) config('theme.swatches', []);
        $current      = self::instance()->current;

        $items = [[
            'slug'        => null,
            'label'       => $labels['default'] ?? 'Mặc định',
            'description' => $descriptions['default'] ?? '',
            'swatch'      => $swatches['default'] ?? '#9ca3af',
            'active'      => $current === null,
        ]];

        foreach ((array) config('theme.available', []) as $slug) {
            $items[] = [
                'slug'        => $slug,
                'label'       => $labels[$slug] ?? ucfirst($slug),
                'description' => $descriptions[$slug] ?? '',
                'swatch'      => $swatches[$slug] ?? '#9ca3af',
                'active'      => $current === $slug,
            ];
        }

        return $items;
    }

    public static function shouldShowSwitcher(): bool
    {
        return (bool) config('theme.allow_query_override') && (array) config('theme.available', []) !== [];
    }

    public static function urlWithTheme(?string $theme, ?string $baseUrl = null): string
    {
        $url   = $baseUrl ?? url()->current();
        $query = request()->except('theme');

        if ($theme !== null) {
            $query['theme'] = $theme;
        }

        return $query === [] ? $url : $url . '?' . http_build_query($query);
    }

    public static function apply(?string $theme, bool $useThemePath = true): void
    {
        $self  = self::instance();
        $theme = self::sanitize($theme);
        $base  = resource_path('web/views');

        $paths = ($theme !== null && $useThemePath)
            ? [self::viewsPath($theme), $base]
            : [$base];

        $signature = implode('|', $paths);
        if ($self->applied && $signature === $self->signature) {
            $self->current = $theme;

            return;
        }

        $self->applied   = true;
        $self->current   = $theme;
        $self->signature = $signature;

        View::replaceNamespace('web', $paths);
        View::getFinder()->flush();
    }
}
