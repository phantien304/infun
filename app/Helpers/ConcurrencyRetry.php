<?php

namespace App\Helpers;

use Illuminate\Database\QueryException;

class ConcurrencyRetry
{
    public static function run(\Closure $callback, int $retries = 2): mixed
    {
        $attempt = 0;

        while (true) {
            try {
                return $callback();
            } catch (QueryException $e) {
                if ($attempt >= $retries || ! self::isLockContention($e)) {
                    throw $e;
                }
                $attempt++;
                usleep(random_int(50_000, 150_000));
            }
        }
    }

    public static function isLockContention(QueryException $e): bool
    {
        $driverCode = (int) ($e->errorInfo[1] ?? 0);
        if (in_array($driverCode, [1205, 1213], true)) {
            return true;
        }

        $message = $e->getMessage();

        return str_contains($message, 'Lock wait timeout exceeded')
            || str_contains($message, 'Deadlock found');
    }
}
