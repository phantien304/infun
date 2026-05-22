<?php

namespace App\Helpers;

use Illuminate\Support\Facades\DB;

trait DatabaseHelper
{
    public static function callRaw($sProcedure, $aParams = [], $isExecute = false)
    {
        // create database connection
        $db = DB::connection()->getPdo();

        // if any params are present, add them
        $sParamsIn = '';
        if (isset($aParams) && is_array($aParams) && count($aParams) > 0) {
            // loop through params and set
            foreach ($aParams as $sParam) {
                $sParamsIn .= '?,';
            }

            // trim the last comma from the params in string
            $sParamsIn = substr($sParamsIn, 0, strlen($sParamsIn) - 1);
        }

        // create initial stored procedure call
        $stmt = $db->prepare("CALL $sProcedure($sParamsIn)");

        // if any params are present, add them
        if (isset($aParams) && is_array($aParams) && count($aParams) > 0) {
            $iParamCount = 1;

            // loop through params and bind value to the prepare statement
            foreach ($aParams as &$value) {
                $stmt->bindParam($iParamCount, $value);
                $iParamCount++;
            }
        }

        try {

            // execute the stored procedure
            $stmt->execute();
            if ($isExecute) {
                return true;
            }
        } catch (\Exception $exception) {
            logError($exception->getMessage());
            if ($isExecute) {
                return false;
            }
            return [];
        }

        do {
            try {
                $results[] = $stmt->fetchAll(\PDO::FETCH_OBJ);
            } catch (\Exception $exception) {
                logError($exception->getMessage());
            }
        } while ($stmt->nextRowset());


        // if the resultset has only 1 record, check the name of the stored procedure
        // if the name of the procedure has sel_rec within it, just return the one record
        if (count($results) == 1 && strpos($sProcedure, 'sel_rec')) {
            $results = $results[0];
        }

        // return the data
        return $results;
    }
}
