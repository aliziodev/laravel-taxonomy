<?php

namespace Aliziodev\LaravelTaxonomy\Console\Commands;

use Illuminate\Console\Command;

class InstallCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'taxonomy:install
                            {--force : Overwrite the config and migrations if they already exist}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install the Laravel Taxonomy package';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Installing Laravel Taxonomy...');

        $force = (bool) $this->option('force');
        $configExisted = file_exists(config_path('taxonomy.php'));

        // Without --force, vendor:publish skips files that already exist. That
        // is the safe default, but it used to happen silently and the command
        // still reported a successful install.
        $this->call('vendor:publish', array_filter([
            '--tag' => 'taxonomy-config',
            '--force' => $force,
        ]));

        $this->call('vendor:publish', array_filter([
            '--tag' => 'taxonomy-migrations',
            '--force' => $force,
        ]));

        if ($configExisted && ! $force) {
            $this->warn('config/taxonomy.php already existed and was left untouched.');
            $this->warn('Re-run with --force to overwrite it with the current defaults.');
        }

        $this->info('Laravel Taxonomy has been installed successfully!');
        $this->info('You can now run your migrations with: php artisan migrate');

        return Command::SUCCESS;
    }
}
