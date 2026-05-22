<?php

namespace App\Helpers;

use Illuminate\Log\Logger;
use Monolog\Formatter\FormatterInterface;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;
use Monolog\LogRecord;

/**
 * Use channels to log into separate files
 *
 * @author Peter Feher
 */
class ChannelStreamHandler extends StreamHandler
{
    /**
     * Channel name
     *
     * @var String
     */
    protected $channel;

    /**
     * @param String $channel Channel name to write
     * @see parent __construct for params
     */
    public function __construct($channel, $stream, $level = Logger::DEBUG, $bubble = true, $filePermission = null, $useLocking = false)
    {
        $this->channel = $channel;

        parent::__construct($stream, $level, $bubble);
    }

    /**
     * @return LineFormatter
     */
    public function getDefaultFormatter(): FormatterInterface
    {
        return new LineFormatter(null, null, true, true);
    }

    /**
     * When to handle the log record.
     *
     * @param array $record
     * @return type
     */
    public function isHandling(LogRecord $record): bool
    {
        //Handle if Level high enough to be handled (default mechanism)
        //AND CHANNELS MATCHING!
        if (isset($record['channel'])) {
            return $record['level'] >= $this->level && $record['channel'] == $this->channel;
        }
        return $record['level'] >= $this->level;
    }
}
