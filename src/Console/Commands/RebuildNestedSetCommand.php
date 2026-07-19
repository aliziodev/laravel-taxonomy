<?php

namespace Aliziodev\LaravelTaxonomy\Console\Commands;

use Aliziodev\LaravelTaxonomy\Concerns\ResolvesTaxonomyModel;
use Aliziodev\LaravelTaxonomy\Enums\TaxonomyType;
use Aliziodev\LaravelTaxonomy\TaxonomyManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RebuildNestedSetCommand extends Command
{
    use ResolvesTaxonomyModel;

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
     * Execute the console command.
     */
    public function handle(): int
    {
        /** @var string|null $type */
        $type = $this->argument('type');
        $force = (bool) $this->option('force');

        if ($type !== null && ! $this->isValidType($type)) {
            $this->error('Invalid taxonomy type: ' . $type);
            $this->info('Available types: ' . implode(', ', $this->getAvailableTypes()));

            return self::FAILURE;
        }

        $types = $type !== null ? [$type] : $this->getExistingTypes();

        if (empty($types)) {
            $this->info('No taxonomies found to rebuild.');

            return self::SUCCESS;
        }

        // Counted once here and reused, rather than once for the confirmation
        // prompt and again per type.
        $counts = $this->countByType($types);

        if (! $force) {
            // Refusing to prompt is a hard stop: a deploy script that silently
            // exited 0 without rebuilding would be worse than a loud failure.
            if (! $this->canPrompt()) {
                $this->error('Refusing to prompt on a non-interactive terminal. Re-run with --force.');

                return self::FAILURE;
            }

            if (! $this->confirmRebuild($types, array_sum($counts))) {
                // Declining a prompt is a choice, not an error.
                $this->info('Operation cancelled.');

                return self::SUCCESS;
            }
        }

        $this->info('Starting nested set rebuild...');
        $startTime = microtime(true);

        // One transaction per type rather than one spanning every type, so a
        // large run holds locks for a bounded window.
        foreach ($types as $taxonomyType) {
            DB::transaction(function () use ($taxonomyType, $counts) {
                $this->rebuildType($taxonomyType, $counts[$taxonomyType] ?? 0);
            });
        }

        $duration = round(microtime(true) - $startTime, 2);
        $this->info("Nested set rebuild completed in {$duration} seconds.");

        return self::SUCCESS;
    }

    /**
     * Rebuild nested set for a specific type.
     */
    protected function rebuildType(string $type, int $count): void
    {
        $this->line("Rebuilding type: {$type}");

        if ($count === 0) {
            $this->line("  No taxonomies found for type: {$type}");

            return;
        }

        $startTime = microtime(true);

        $modelClass = $this->getModelClass();
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
     * Get all taxonomy types the command will accept.
     *
     * Validating against the enum alone rejected types declared in
     * `taxonomy.types` and types that only exist in the database, which
     * contradicted the no-argument run: that rebuilds whatever types it finds.
     *
     * @return array<string>
     */
    protected function getAvailableTypes(): array
    {
        /** @var array<int, string> $configured */
        $configured = config('taxonomy.types', TaxonomyType::values());

        $types = array_merge(
            array_map(fn ($case) => $case->value, TaxonomyType::cases()),
            array_values($configured),
            $this->getExistingTypes(),
        );

        return array_values(array_unique(array_filter($types, 'is_string')));
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
     * Count taxonomies per type in a single query.
     *
     * @param  array<string>  $types
     * @return array<string, int>
     */
    protected function countByType(array $types): array
    {
        $modelClass = $this->getModelClass();

        /** @var array<string, int> $counts */
        $counts = $modelClass::whereIn('type', $types)
            ->selectRaw('type, count(*) as aggregate')
            ->groupBy('type')
            ->pluck('aggregate', 'type')
            ->map(fn ($value) => (int) $value)
            ->all();

        return $counts;
    }

    /**
     * Show confirmation dialog.
     *
     * @param  array<string>  $types
     */
    protected function confirmRebuild(array $types, int $count): bool
    {
        $typesList = implode(', ', $types);

        $this->warn("This will rebuild nested set values for {$count} taxonomies.");
        $this->warn("Types: {$typesList}");
        $this->warn('This operation will modify lft, rgt, and depth values.');

        return $this->confirm('Do you want to continue?');
    }

    /**
     * Determine whether a confirmation prompt can actually be answered.
     *
     * There is nobody to answer when the command runs from a deploy script,
     * cron job, CI pipeline or Artisan::call(). Prompting anyway blocks the
     * process forever.
     *
     * isInteractive() alone is not enough: Artisan::call() builds an ArrayInput
     * that reports itself as interactive, so the attached stream is checked too.
     */
    protected function canPrompt(): bool
    {
        return $this->input->isInteractive() && $this->hasInteractiveStdin();
    }

    /**
     * Determine whether STDIN is attached to a real terminal.
     */
    protected function hasInteractiveStdin(): bool
    {
        // stream_isatty() has existed since PHP 7.2 and this package requires
        // 8.2, so no function_exists() guard is needed.
        return defined('STDIN') && is_resource(STDIN) && stream_isatty(STDIN);
    }
}
