<?php

namespace App\Helpers\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Class ChannelLog
 * @package App\Helpers\Facades
 */
class ChannelLog extends Facade
{
    /**
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return "channellog";
    }
}
