<?php

use App\Helpers\Facades\ChannelLog;
use App\Helpers\Facades\CustomStorage;
use Illuminate\Support\Facades\DB;

function respondSuccess($data = null, string $message = '', int $status = 200, array $meta = []): \Illuminate\Http\JsonResponse
{
    $payload = [
        'success' => true,
        'message' => $message,
        'data'    => $data,
    ];
    if (! empty($meta)) {
        $payload['meta'] = $meta;
    }

    return response()->json($payload, $status);
}

function respondCreated($data = null, string $message = ''): \Illuminate\Http\JsonResponse
{
    return respondSuccess($data, $message, 201);
}

function respondAccepted($data = null, string $message = ''): \Illuminate\Http\JsonResponse
{
    return respondSuccess($data, $message, 202);
}

function respondMessage(string $message = '', int $status = 200): \Illuminate\Http\JsonResponse
{
    return respondSuccess(null, $message, $status);
}

function respondError(string $message = '', int $status = 400, array $errors = []): \Illuminate\Http\JsonResponse
{
    $payload = [
        'success' => false,
        'message' => $message,
    ];
    if (! empty($errors)) {
        $payload['errors'] = $errors;
    }

    return response()->json($payload, $status);
}

function respondNotFound(string $message = ''): \Illuminate\Http\JsonResponse
{
    return respondError($message, 404);
}

function respondUnprocessable(string $message = '', array $errors = []): \Illuminate\Http\JsonResponse
{
    return respondError($message, 422, $errors);
}

function getForwardedIp()
{
    if (filled(request()->server('HTTP_X_FORWARDED_FOR'))) {
        $forwardedIp = request()->server('HTTP_X_FORWARDED_FOR');
    } elseif (filled(request()->server('HTTP_CLIENT_IP'))) {
        $forwardedIp = request()->server('HTTP_CLIENT_IP');
    } else {
        $forwardedIp = '';
    }
    return $forwardedIp;
}

function getCookie($cookieName = '', $default = null)
{
    if (empty($cookieName)) {
        return request()->cookie();
    }

    return request()->cookie($cookieName) ? request()->cookie($cookieName) : $default;
}

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
    $configs = $configService->getConfigs();
    if (filled($key)) {
        return data_get($configs, $key, $default);
    }

    return $configs;
}
function money($price, ?string $code = null): string
{
    $svc = app(\App\Services\Currency\CurrencyService::class);
    $currency = $code ? $svc->findCurrency($code) : null;

    return $svc->formatPrice((float) $price, $currency);
}
function currencyBase(): \App\Models\Entities\Currency
{
    return app(\App\Services\Currency\CurrencyService::class)->baseCurrency();
}
function moneyAtBuy($price, ?string $currencyCode, $currencyValue): string
{
    return app(\App\Services\Currency\CurrencyService::class)
        ->formatSnapshot((float) $price, $currencyCode, (float) $currencyValue);
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
function buildUrl(?string $slug, ?string $moduleKey, ?int $id, ?string $prefix = ''): string
{
    $url = '/' . $slug . '-' . $moduleKey . $id;
    return filled($prefix) ? url('/' . $prefix . '/' . $url) : url($url);
}
function thumbnail(string $image, int $width = 400, int $height = 400, string $module = 'web'): string
{
    return CustomStorage::getStorage(config('media.image_disk', 'image'))
        ->resizeImage($image, $width, $height, $module);
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
    $route = request()->route();
    if ($route) {
        return $route->getAction('area') ?: 'web';
    }
    return 'web';
}
function isCms()
{
    return getCurrentArea() === 'cms';
}
function isApi()
{
    return getCurrentArea() === 'api';
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
    return rtrim(config('app.url'), '/') . '/' . $url;
}
function getIpVisitor()
{
    if (isset($_SERVER["HTTP_CF_CONNECTING_IP"])) {
        $_SERVER['REMOTE_ADDR'] = $_SERVER["HTTP_CF_CONNECTING_IP"];
        $_SERVER['HTTP_CLIENT_IP'] = $_SERVER["HTTP_CF_CONNECTING_IP"];
    }
    $client = @$_SERVER['HTTP_CLIENT_IP'];
    $forward = @$_SERVER['HTTP_X_FORWARDED_FOR'];
    $remote = isset($_SERVER["REMOTE_ADDR"]) ? $_SERVER["REMOTE_ADDR"] : '127.0.0.1';
    if (strpos($forward, ':')) {
        $arrForward = explode(':', $forward);
        $forward = $arrForward[0];
    }
    if (filter_var($client, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ||
        filter_var($client, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        $ip = $client;
    } elseif (filter_var($forward, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ||
        filter_var($forward, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        $ip = $forward;
    } else {
        $ip = $remote;
    }
    return $ip;
}
function string2Stars($string = '', $first = 0, $last = 0, $rep = 'x')
{
    $string = (string) $string;
    $begin  = substr($string, 0, $first);
    $middle = str_repeat($rep, strlen(substr($string, $first, $last)));
    $end    = substr($string, $last);

    return $begin . $middle . ' ' . $end;
}
