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
    protected $signature = 'taxonomy:install';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install the Laravel Taxonomy package';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Installing Laravel Taxonomy...');

        // Publish config file
        $this->call('vendor:publish', [
            '--provider' => 'Aliziodev\\LaravelTaxonomy\\TaxonomyProvider',
            '--tag' => 'taxonomy-config',
        ]);

        // Publish migration files
        $this->call('vendor:publish', [
            '--provider' => 'Aliziodev\\LaravelTaxonomy\\TaxonomyProvider',
            '--tag' => 'taxonomy-migrations',
        ]);

        $this->info('Laravel Taxonomy has been installed successfully!');
        $this->info('You can now run your migrations with: php artisan migrate');

        return Command::SUCCESS;
    }
}
