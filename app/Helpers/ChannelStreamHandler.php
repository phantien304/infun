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
        $recordLevel  = $record->level->value;
        $handlerLevel = $this->level instanceof \Monolog\Level
            ? $this->level->value
            : $this->level;
        return $recordLevel >= $handlerLevel
            && ($record->channel === '' || $record->channel === $this->channel);
    }
}
