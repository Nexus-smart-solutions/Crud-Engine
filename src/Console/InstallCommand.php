<?php

declare(strict_types=1);

namespace Nexus\CrudEngine\Console;

use Illuminate\Console\Command;

/**
 * `php artisan crud-engine:install` — publishes the config and
 * translation files in one step and prints a short quick-start.
 */
final class InstallCommand extends Command
{
    protected $signature = 'crud-engine:install';

    protected $description = 'Publish the Nexus CRUD Engine config and translation files.';

    public function handle(): int
    {
        $this->call('vendor:publish', ['--tag' => 'crud-engine-config']);
        $this->call('vendor:publish', ['--tag' => 'crud-engine-lang']);

        $this->newLine();
        $this->info('Nexus CRUD Engine is installed.');
        $this->line('Next steps:');
        $this->line('  1. Implement Nexus\CrudEngine\Contracts\Capabilities\* on your Eloquent models as needed.');
        $this->line('  2. Extend Nexus\CrudEngine\Services\Crud\Abstract*Service for each resource.');
        $this->line('  3. Review the published config/crud-engine.php for disk, caching, and validation settings.');

        return self::SUCCESS;
    }
}
