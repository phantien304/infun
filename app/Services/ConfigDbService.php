<?php

namespace App\Services;

use App\Repositories\Interfaces\SettingRepositoryInterface;

class ConfigDbService
{
    private ?array $configsMemo = null;

    public function __construct(protected SettingRepositoryInterface $settingRepo)
    {
    }

    public function getConfigs(): array
    {
        return $this->configsMemo ??= $this->settingRepo->listAllCached();
    }

    public function clearCache(): void
    {
        $this->configsMemo = null;
        $this->settingRepo->flushCache();
    }
}
