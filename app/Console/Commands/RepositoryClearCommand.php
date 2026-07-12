<?php

namespace App\Console\Commands;

use App\Providers\AppServiceProvider;
use Illuminate\Console\Command;

class RepositoryClearCommand extends Command
{
    protected $signature = 'repository:clear';

    protected $description = 'Xoá cache ánh xạ repository (về auto-discovery).';

    public function handle(): int
    {
        $path = AppServiceProvider::repositoryCachePath();
        if (is_file($path)) {
            @unlink($path);
        }

        $this->components->info('Repository binding cache cleared.');

        return self::SUCCESS;
    }
}
