<?php

namespace App\Services;

use Illuminate\Support\Facades\Input;

/**
 * Class LaravelLogViewer
 * @package Rap2hpoutre\LaravelLogViewer
 */
class LogViewerService
{
    /**
     * @var string file
     */
    private static $file;

    private static $levels_classes = [
        'debug' => 'info',
        'info' => 'info',
        'notice' => 'info',
        'warning' => 'warning',
        'error' => 'danger',
        'critical' => 'danger',
        'alert' => 'danger',
        'emergency' => 'danger',
        'processed' => 'info',
    ];

    private static $levels_imgs = [
        'debug' => 'info',
        'info' => 'info',
        'notice' => 'info',
        'warning' => 'warning',
        'error' => 'warning',
        'critical' => 'warning',
        'alert' => 'warning',
        'emergency' => 'warning',
        'processed' => 'info'
    ];

    /**
     * Log levels that are used
     * @var array
     */
    private static $log_levels = [
        'emergency',
        'alert',
        'critical',
        'error',
        'warning',
        'notice',
        'info',
        'debug',
        'processed'
    ];

    public static $apiLog = false;
    public static $pointLog = false;

    const MAX_FILE_SIZE = 52428800; // Why? Uh... Sorry

    public static function getCurrentStorageLog()
    {
        return storageLog()->setCurrentArea(getCurrentArea());
    }

    /**
     * @param $file
     * @throws \Exception
     */
    public static function setFile($file)
    {
        $file = self::pathToLogFile($file);

        if (self::getCurrentStorageLog()->existsWithArea($file)) {
            self::$file = $file;
        }
    }

    /**
     * @return string
     */
    public static function getFileName()
    {
        $file = explode('logs/', self::$file);
        return last($file);
    }

    /**
     * @return array
     */
    public static function all()
    {
        $log = array();

        $patternSystemLog = '/\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}([\+-]\d{4})?\].*/';
        $patternApiLog = '/\"\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}([\+-]\d{4})?\".*/';
        $patternPointLog = '/\d.*/';

        if (!self::$file) {
            $log_file = self::getFiles();
            if (!count($log_file)) {
                return [];
            }
            self::$file = $log_file[0];
        }

        if (self::getCurrentStorageLog()->sizeWithArea(self::$file) > self::MAX_FILE_SIZE) return null;

        $file = self::getCurrentStorageLog()->getWithArea(self::$file);

        preg_match_all($patternSystemLog, $file, $headings);
        preg_match_all($patternApiLog, $file, $headingsApi);
        preg_match_all($patternPointLog, $file, $headingsPoint);

        if (!is_array($headings)) return $log;
        if (!is_array($headingsApi)) return $log;
        if (!is_array($headingsPoint)) return $log;

        $log_data = preg_split($patternSystemLog, $file);

        if (!empty(array_filter($headingsApi))) {
            self::$apiLog = true;
        }
        if (!empty(array_filter($headingsPoint))) {
            self::$pointLog = true;
        }

        foreach ($headings as $h) {
            for ($i = 0, $j = count($h); $i < $j; $i++) {
                foreach (self::$log_levels as $level) {
                    if (strpos(strtolower($h[$i]), '.' . $level) || strpos(strtolower($h[$i]), $level . ':')) {

                        preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}([\+-]\d{4})?)\](?:.*?(\w+)\.|.*?)' . $level . ': (.*?)( in .*?:[0-9]+)?$/i', $h[$i], $current);
                        if (!isset($current[4])) continue;

                        $log[] = [
                            'context' => $current[3],
                            'level' => $level,
                            'level_class' => self::$levels_classes[$level],
                            'level_img' => self::$levels_imgs[$level],
                            'date' => $current[1],
                            'text' => $current[4],
                            'in_file' => isset($current[5]) ? $current[5] : null,
                            'stack' => preg_replace("/^\n*/", '', $log_data[$i])
                        ];
                    }
                }
            }
        }
        foreach ($headingsApi as $hApi) {
            for ($i = 0, $j = count($hApi); $i < $j; $i++) {
                $tmpLog = explode('","', trim($hApi[$i], "\""));
                if (!is_array($tmpLog) || count($tmpLog) != 6) continue;
                $log[] = array_combine([
                    'datetime',
                    'ip',
                    'method_action',
                    'params',
                    'content',
                    'user_agent',
                ], $tmpLog);
            }
        }
        foreach ($headingsPoint as $hPoint) {
            for ($i = 0, $j = count($hPoint); $i < $j; $i++) {
                $tmpLog = explode("\t", $hPoint[$i]);
                if (!is_array($tmpLog) || count($tmpLog) != 13) continue;
                $log[] = array_combine([
                    'uid',
                    'time_run',
                    'title',
                    'other',
                    'get_point',
                    'before_point',
                    'after_point',
                    'remote_ip',
                    'schedule',
                    'date',
                    'time',
                    'user_agent',
                    'last'
                ], $tmpLog);
            }
        }

        return array_reverse($log);
    }

    /**
     * @param bool $basename
     * @return array
     */
    public static function getFiles($basename = false)
    {
        $files = self::getCurrentStorageLog()->allFilesWithArea();
        $files = array_reverse($files);
        if ($basename && is_array($files)) {
            foreach ($files as $k => $file) {
                $files[$k] = basename($file);
            }
        }
        return array_values($files);
    }

    /**
     * @param string $file
     * @return string
     * @throws \Exception
     */
    public static function pathToLogFile($file)
    {
        $logsPath = self::getCurrentStorageLog()->getPathPrefixWithArea();

        if (self::getCurrentStorageLog()->existsWithArea($logsPath . '/' . $file)) {
            return $logsPath . '/' . $file;
        }

        if (self::getCurrentStorageLog()->existsWithArea($file)) {
            return $file;
        }

        if (preg_match("/\d{4}-\d{2}-\d{2}/", $file, $match)) {
            $logsPath = $logsPath . '/' . $match['0'];
        }

        $file = $logsPath . '/' . $file;

        // check if requested file is really in the logs directory
        if (dirname($file) !== $logsPath) {
            throw new \Exception('No such log file');
        }

        return $file;
    }

    /**
     * @return array
     */
    public static function getLogLevels()
    {
        return self::$log_levels;
    }
}
