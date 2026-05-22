<?php

use App\Helpers\Facades\ChannelLog;
use App\Helpers\Facades\CustomStorage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

function getDeletedByColumn($key = 'field')
{
    return getSystemConfig('deleted_by_column.' . $key, getUpdatedByColumn());
}
function getCreatedByColumn($key = 'field')
{
    return getSystemConfig('created_by_column.' . $key);
}
function getUpdatedByColumn($key = 'field')
{
    return getSystemConfig('updated_by_column.' . $key);
}
function isMobile()
{
    $detect = new \App\Http\Supports\MobileDetect();
    if ($detect->isMobile()) {
        return true;
    }
    return false;
}
function getConfigDb($key = '', $default = '')
{
    $configService = app(\App\Services\ConfigDbService::class);
    $config = $configService->getConfig();
    if (filled($key)) {
        return data_get($config, $key, $default);
    }

    return $config;
}
function getCoreConfig($key, $default = null, $flip = false)
{
    $env = config('app.env');
    $basePath = "core.config.{$key}";
    $envPath = "core.{$env}.config.{$key}";
    $result = config($envPath, config($basePath, $default));
    if ($flip && is_array($result)) {
        return array_flip($result);
    }
    return $result;
}
function resolveSlug(?string $slug, ?string $title): string
{
    return filled($slug) ? $slug : \Illuminate\Support\Str::slug((string) $title);
}
function buildUrl(?string $slug, ?string $moduleKey, ?int $id): string
{
    return url('/' . $slug . '-' . $moduleKey . $id);
}
function thumbnail(string $image, int $width = 400, int $height = 400, string $module = 'web'): string
{
    return CustomStorage::getStorage('public')->resizeImage($image, $width, $height, $module);
}
function setting($key, $default = null)
{
    $dbValue = getConfigDb($key);

    if (!is_null($dbValue) && $dbValue !== '') {
        return $dbValue;
    }
    return getCoreConfig($key, $default);
}
function getModuleConfig($key, $module = 'web', $default = null, $flip = false)
{
    $key = 'config.' . $key;
    $dirDefault = "module.{$module}.";
    $dirWithEnv = $dirDefault . config('app.env') . '.';
    $r = (config()->has($dirWithEnv . $key))
        ? config($dirWithEnv . $key, $default)
        : config($dirDefault . $key, $default);
    if ($flip) {
        return array_flip($r);
    }
    return $r;
}
function getEventName($key, $default = null)
{
    return config('events.' . $key, $default);
}
function getConstant($key, $default = null)
{
    return config('constant.' . $key, $default);
}
function logInfo($message, array $context = [])
{
    ChannelLog::info('info', $message, $context);
}
function logError($message, array $context = [])
{
    ChannelLog::error('error', $message, $context);
}
function logDebug($message, array $context = [])
{
    ChannelLog::debug('debug', $message, $context);
}
function getSystemConfig($key, $default = null, $flip = false)
{
    return config('system.' . $key, $default);
}
function storageLog()
{
    return \App\Helpers\Facades\CustomStorage::getStorage('logs');
}
function storageData()
{
    return \App\Helpers\Facades\CustomStorage::getStorage('data');
}
function storageImage()
{
    return \App\Helpers\Facades\CustomStorage::getStorage('image');
}
function getCurrentArea()
{
    if (app()->runningInConsole()) {
        return 'batch';
    }
    if (isCms()) {
        return 'cms';
    }
    if (isApi()) {
        return 'api';
    }
    return 'web';
}
function isCms()
{
    return request()->routeIs('cms.*');
}
function isApi()
{
    return request()->routeIs('api.*');
}
function routeArea($name, $params = [])
{
    if (str_starts_with($name, 'http')) {
        return $name;
    }
    $area = getCurrentArea();
    $targetName = $name;
    if (!str_contains($name, $area . '.')) {
        $targetName = $area . '.' . $name;
    }
    if (Route::has($targetName)) {
        return route($targetName, $params);
    }
    if (Route::has($name)) {
        return route($name, $params);
    }
    return url($name);
}
function getTmpUploadDir($file = null)
{
    $dir = getSystemConfig('tmp_upload_dir', 'tmp_upload');
    return $file ? $dir . '/' . ltrim($file, '/') : $dir;
}
function getMediaDir($file = null)
{
    $dir = getSystemConfig('media_dir', 'media');
    return $file ? $dir . '/' . ltrim($file, '/') : $dir;
}
function getCurrentUserId()
{
    if (app()->runningInConsole()) {
        return null;
    }
    return auth()->user()?->id ?? null;
}
function getUserGroupId()
{
    return auth()->check()
        ? auth()->user()->user_group_id
        : getConfigDb('config_user_group_id');
}
function getUserType()
{
    return auth()->check()
        ? auth()->user()->type
        : getCoreConfig('user.type.member');
}
function transm($id = null, $replace = [], $locale = null)
{
    return trans('models.' . $id, $replace, $locale);
}
function attr($attrs = array())
{
    return array_merge(config('entity.attributes', []), $attrs);
}
function isCollection($value)
{
    return $value instanceof Illuminate\Support\Collection || $value instanceof Illuminate\Database\Eloquent\Collection;
}
function sql_binding($sql, $bindings)
{
    $boundSql = str_replace(['%', '?'], ['%%', '%s'], $sql);
    $isConnectPgSql = DB::connection()->getDriverName() == 'pgsql';
    foreach ($bindings as &$binding) {
        if ($binding instanceof \DateTime) {
            $binding = $binding->format('\'Y-m-d H:i:s\'');
        } elseif (is_string($binding)) {
            $binding = "'$binding'";
        } elseif (is_bool($binding) && $isConnectPgSql) {
            $binding = json_encode($binding);
        }
    }
    $boundSql = vsprintf($boundSql, $bindings);
    return $boundSql;
}
function publicUrl($url)
{
    if (strpos($url, 'http') !== false) {
        return $url;
    }

    $appURL = request()->getSchemeAndHttpHost();
    $str = substr($appURL, strlen($appURL) - 1, 1);
    if ($str != '/') {
        $appURL .= '/';
    }
    if (\Illuminate\Support\Facades\Request::secure()) {
        $appURL = str_replace('http://', 'https://', $appURL);
    }
    return $appURL . $url;
}
