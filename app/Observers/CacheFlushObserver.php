<?php

namespace App\Observers;

use App\Helpers\CacheGate;

class CacheFlushObserver
{
    public function __construct(protected array $repoInterfaces)
    {
    }

    public function saved($model): void
    {
        $this->flush();
    }

    public function deleted($model): void
    {
        $this->flush();
    }

    public function restored($model): void
    {
        $this->flush();
    }

    public function forceDeleted($model): void
    {
        $this->flush();
    }

    protected function flush(): void
    {
        foreach ($this->repoInterfaces as $iface) {
            try {
                app($iface)->flushCache();
            } catch (\Throwable $e) {
                logError('CacheFlushObserver flush ' . $iface . ': ' . $e->getMessage());
            }
        }
        CacheGate::flushPages();
    }
}
