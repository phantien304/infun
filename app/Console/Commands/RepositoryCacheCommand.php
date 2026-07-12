<?php

namespace App\Console\Commands;

use App\Providers\AppServiceProvider;
use Illuminate\Console\Command;

class RepositoryCacheCommand extends Command
{
    protected $signature = 'repository:cache';

    protected $description = 'Compile ánh xạ repository interface->implementation ra bootstrap/cache/repositories.php.';

    public function handle(): int
    {
        $bindings = AppServiceProvider::discoverRepositoryBindings();

        $content = "<?php\n\n"
            . 'return ' . var_export($bindings, true) . ";\n";

        file_put_contents(AppServiceProvider::repositoryCachePath(), $content);

        $this->components->info(count($bindings) . ' repository bindings cached.');

        return self::SUCCESS;
    }
}
