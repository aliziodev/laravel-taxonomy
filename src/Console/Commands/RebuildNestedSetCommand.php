<?php

namespace Aliziodev\LaravelTaxonomy\Console\Commands;

use Aliziodev\LaravelTaxonomy\Enums\TaxonomyType;
use Aliziodev\LaravelTaxonomy\Models\Taxonomy;
use Aliziodev\LaravelTaxonomy\TaxonomyManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RebuildNestedSetCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'taxonomy:rebuild-nested-set 
                            {type? : The taxonomy type to rebuild (optional, rebuilds all if not specified)}
                            {--force : Force rebuild without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rebuild nested set values (lft, rgt, depth) for taxonomy hierarchies';

    /**
     * Get the taxonomy model class from config.
     *
     * @return class-string<Taxonomy>
     */
    protected function getModelClass(): string
    {
        return config('taxonomy.model', Taxonomy::class);
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $type = $this->argument('type');
        $force = $this->option('force');

        // Validate type if provided
        if ($type !== null && ! $this->isValidType(is_string($type) ? $type : '')) {
            $typeStr = is_string($type) ? $type : 'invalid';
            $this->error('Invalid taxonomy type: ' . $typeStr);
            $this->info('Available types: ' . implode(', ', $this->getAvailableTypes()));

            return self::FAILURE;
        }

        // Get types to rebuild
        $types = $type !== null && is_string($type) ? [$type] : $this->getExistingTypes();

        if (empty($types)) {
            $this->info('No taxonomies found to rebuild.');

            return self::SUCCESS;
        }

        // Show confirmation unless forced
        if (! $force && ! $this->confirmRebuild($types)) {
            $this->info('Operation cancelled.');

            return self::FAILURE;
        }

        $this->info('Starting nested set rebuild...');
        $startTime = microtime(true);

        DB::transaction(function () use ($types) {
            foreach ($types as $taxonomyType) {
                $this->rebuildType($taxonomyType);
            }
        });

        $duration = round(microtime(true) - $startTime, 2);
        $this->info("Nested set rebuild completed in {$duration} seconds.");

        return self::SUCCESS;
    }

    /**
     * Rebuild nested set for a specific type.
     */
    protected function rebuildType(string $type): void
    {
        $this->line("Rebuilding type: {$type}");

        $modelClass = $this->getModelClass();
        $count = $modelClass::where('type', $type)->count();
        if ($count === 0) {
            $this->line("  No taxonomies found for type: {$type}");

            return;
        }

        $startTime = microtime(true);

        // Use the model's rebuild method
        $modelClass::rebuildNestedSet($type);

        // rebuildNestedSet() writes without firing model events, so nothing
        // invalidates the cached trees. Without this the command reports
        // success while the application keeps serving the pre-rebuild tree
        // for the rest of the cache TTL.
        app(TaxonomyManager::class)->clearCacheForType($type);

        $duration = round(microtime(true) - $startTime, 2);
        $this->line("  Rebuilt {$count} taxonomies in {$duration} seconds");
    }

    /**
     * Check if the provided type is valid.
     */
    protected function isValidType(string $type): bool
    {
        return in_array($type, $this->getAvailableTypes());
    }

    /**
     * Get all available taxonomy types from enum.
     *
     * @return array<string>
     */
    protected function getAvailableTypes(): array
    {
        return array_map(fn ($case) => $case->value, TaxonomyType::cases());
    }

    /**
     * Get existing taxonomy types from database.
     *
     * @return array<string>
     */
    protected function getExistingTypes(): array
    {
        $modelClass = $this->getModelClass();

        return $modelClass::select('type')
            ->distinct()
            ->pluck('type')
            ->toArray();
    }

    /**
     * Show confirmation dialog.
     *
     * @param  array<string>  $types
     */
    protected function confirmRebuild(array $types): bool
    {
        $typesList = implode(', ', $types);
        $modelClass = $this->getModelClass();
        $count = $modelClass::whereIn('type', $types)->count();

        $this->warn("This will rebuild nested set values for {$count} taxonomies.");
        $this->warn("Types: {$typesList}");
        $this->warn('This operation will modify lft, rgt, and depth values.');

        // There is nobody to answer the prompt when the command runs from a
        // deploy script, cron job, CI pipeline or Artisan::call(). Prompting
        // anyway blocks the process forever, so require --force instead.
        //
        // isInteractive() alone is not enough: Artisan::call() builds an
        // ArrayInput that reports itself as interactive, so the attached
        // stream is checked as well.
        if (! $this->input->isInteractive() || ! $this->hasInteractiveStdin()) {
            $this->warn('Non-interactive terminal detected. Re-run with --force to proceed.');

            return false;
        }

        return $this->confirm('Do you want to continue?');
    }

    /**
     * Determine whether STDIN is attached to a real terminal.
     */
    protected function hasInteractiveStdin(): bool
    {
        if (! defined('STDIN') || ! is_resource(STDIN)) {
            return false;
        }

        return function_exists('stream_isatty') ? stream_isatty(STDIN) : true;
    }
}
