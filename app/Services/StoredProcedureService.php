<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class StoredProcedureService
{
    public static function call(string $procedure, array $params = [], bool $execute = false): mixed
    {
        return self::callOn(null, $procedure, $params, $execute);
    }
    public static function callOn(?string $connection, string $procedure, array $params = [], bool $execute = false): mixed
    {
        $pdo       = DB::connection($connection)->getPdo();
        $paramsIn  = implode(',', array_fill(0, count($params), '?'));
        $stmt      = $pdo->prepare("CALL {$procedure}({$paramsIn})");

        foreach (array_values($params) as $i => $value) {
            $stmt->bindValue($i + 1, $value);
        }

        try {
            $stmt->execute();

            if ($execute) {
                return true;
            }
        } catch (\Exception $e) {
            logError($e->getMessage());
            return $execute ? false : [];
        }

        $results = [];
        do {
            try {
                $results[] = $stmt->fetchAll(\PDO::FETCH_OBJ);
            } catch (\Exception $e) {
                logError($e->getMessage());
            }
        } while ($stmt->nextRowset());

        if (count($results) === 1 && str_contains($procedure, 'sel_rec')) {
            return $results[0];
        }

        return $results;
    }
}
