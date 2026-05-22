<?php

namespace App\Events;

/**
 * Trait BaseEvent
 * @package App\Events
 */
trait BaseEvent
{
    protected $eventSuffix = '';

    protected $eventPrefix = '';

    /**
     * @return string
     */
    public function getEventPrefix(): string
    {
        return $this->eventPrefix;
    }

    /**
     * @param string $eventPrefix
     */
    public function setEventPrefix(string $eventPrefix)
    {
        $this->eventPrefix = $eventPrefix;
    }

    /**
     * @return string
     */
    public function getEventSuffix(): string
    {
        return $this->eventSuffix;
    }

    /**
     * @param string $eventSuffix
     */
    public function setEventSuffix(string $eventSuffix)
    {
        $this->eventSuffix = $eventSuffix;
    }

    /**
     * @param $name
     * @param $data
     */
    public function fireEvent($name, &$data)
    {
        $nameC = \Illuminate\Support\Str::camel($name);
        if (method_exists($this, $nameC)) {
            $this->{$nameC}($data);
        }
        $eventName = $this->getEventName($name);
        if ($this->eventPrefix) {
            $eventName = $this->eventPrefix . '.' . $eventName;
        }
        if ($this->eventSuffix) {
            $eventName .= '.' . $this->eventSuffix;
        }
        $responses = event($eventName, $data);
        if (!empty($responses)) {
            foreach ($responses as $response) {
                if (is_array($response) && isset($response['url'])) {
                    $data = $response;
                }
            }
        }
    }

    public function getEventName($name)
    {
        return getEventName($name);
    }
}
