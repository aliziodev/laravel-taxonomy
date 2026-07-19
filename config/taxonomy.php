<?php

use Aliziodev\LaravelTaxonomy\Enums\TaxonomyType;
use Aliziodev\LaravelTaxonomy\Models\Taxonomy;

return [

    /*
    |--------------------------------------------------------------------------
    | Table Names
    |--------------------------------------------------------------------------
    |
    | These are the tables used by the package to store taxonomy information.
    | You can change the table names if they conflict with existing tables
    | in your application or if you prefer different naming conventions.
    |
    | 'taxonomies' - Stores the taxonomy terms (categories, tags, etc.)
    | 'taxonomables' - Pivot table for polymorphic relationships between
    |                  taxonomies and other models in your application
    |
    */
    'table_names' => [
        'taxonomies' => 'taxonomies',
        'taxonomables' => 'taxonomables',
    ],

    /*
    |--------------------------------------------------------------------------
    | Table Configurations
    |--------------------------------------------------------------------------
    |
    | This section controls how the polymorphic relationships are stored in the
    | database. The morph_type setting determines the column type used for
    | model identifiers in the taxonomables table.
    |
    | Supported options:
    | - 'numeric': Uses regular incremental IDs (bigInteger columns)
    | - 'uuid': Uses UUID identifiers (uuid columns)
    | - 'ulid': Uses ULID identifiers (ulid columns)
    |
    | Choose the option that matches how your application's models are identified.
    | If your models use UUID or ULID as primary keys, you should set this
    | accordingly to ensure proper relationship handling.
    |
    */
    'morph_type' => 'uuid', // Option: 'numeric', 'uuid', 'ulid'

    /*
    |--------------------------------------------------------------------------
    | Default Taxonomy Types
    |--------------------------------------------------------------------------
    |
    | This setting defines the taxonomy types available in your application.
    | By default, it uses all the types defined in the TaxonomyType enum
    | (Category, Tag, Color, Size, etc.).
    |
    | You can customize this list to:
    | - Use only a subset of the predefined types
    | - Add your own custom types not defined in the enum
    |
    | Example of custom configuration:
    | 'types' => ['category', 'tag', 'genre', 'location', 'season'],
    |
    | Types are used to categorize taxonomies and allow you to filter and
    | organize them based on their purpose in your application.
    |
    */
    'types' => collect(TaxonomyType::cases())->pluck('value')->toArray(),

    /*
    |--------------------------------------------------------------------------
    | Model Bindings
    |--------------------------------------------------------------------------
    |
    | This setting allows you to specify the model class used for taxonomies.
    | By default, it uses the package's built-in Taxonomy model, but you can
    | override this with your own custom model if you need to extend the
    | functionality.
    |
    | Your custom model should extend the base Taxonomy model or implement
    | the same interface to ensure compatibility with the package.
    |
    | This is useful when you need to:
    | - Add custom methods to the Taxonomy model
    | - Modify the default behavior of the model
    | - Integrate with other packages or systems
    |
    */
    'model' => Taxonomy::class,

    /*
    |--------------------------------------------------------------------------
    | Slug Configuration
    |--------------------------------------------------------------------------
    |
    | These settings control how taxonomy slugs are handled in the package.
    | Slugs are used in URLs and as unique identifiers for taxonomies.
    |
    | Available options:
    |
    | - generate (boolean):
    |   When true, slugs are automatically generated from the taxonomy name.
    |   When false, you must provide a slug manually when creating taxonomies.
    |   Setting this to false is useful when you need complete control over
    |   your taxonomy slugs.
    |
    | - regenerate_on_update (boolean):
    |   When true, slugs are automatically updated when the taxonomy name changes.
    |   When false, slugs remain unchanged even if the name is updated.
    |   Setting this to false helps maintain stable URLs even when taxonomy
    |   names are edited.
    |
    | Note: The package ensures all slugs are unique. If a generated slug
    | would conflict with an existing one, a numeric suffix is added.
    |
    */
    'slugs' => [
        'generate' => true,
        'regenerate_on_update' => true,
        // When true, slug uniqueness check also considers soft-deleted records
        // When false, trashed records are ignored (slug can be reused)
        'consider_trashed' => false,
        // When restoring a soft-deleted taxonomy and slug conflicts with an active one,
        // regenerate slug automatically to a unique variant.
        'regenerate_on_restore' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | The package caches tree lookups (tree, flatTree, getNestedTree) and
    | invalidates them automatically whenever a taxonomy is written.
    |
    | - 'ttl' (int): Cache lifetime in seconds. Defaults to 86400 (24 hours).
    |
    | - 'scope' (class-string|null): Namespaces every cache key the package
    |   writes. REQUIRED for multi-tenant applications, otherwise one tenant can
    |   be served another tenant's cached taxonomy tree.
    |
    |   Set this to the name of an invokable class returning a unique
    |   identifier for the current context (or null when there is none):
    |
    |       class TenantCacheScope
    |       {
    |           public function __invoke(): ?string
    |           {
    |               return tenant()?->getKey();
    |           }
    |       }
    |
    |       'scope' => TenantCacheScope::class,
    |
    |   A class name is used rather than a closure so this file stays
    |   compatible with `php artisan config:cache`. If you prefer a closure,
    |   register it from a service provider instead:
    |
    |       TaxonomyManager::resolveCacheScopeUsing(fn () => tenant()?->getKey());
    |
    |   When no scope is configured the cache keys are identical to previous
    |   releases, so single-tenant applications are unaffected.
    |
    */
    'cache' => [
        'ttl' => 86400,
        'scope' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Loading
    |--------------------------------------------------------------------------
    |
    | Control whether the package automatically registers its migrations.
    | In multi-tenant setups, you may want to disable autoloading and run
    | migrations explicitly per tenant connection.
    |
    | Options:
    | - 'autoload' (boolean): When true, the package registers its migration
    |   paths so they run with the standard `php artisan migrate` command.
    |   Set to false to disable autoloading.
    | - 'paths' (array<string>): Optional custom paths to load if autoload is
    |   enabled. If empty, the package's default migration path is used.
    |
    */
    'migrations' => [
        'autoload' => env('TAXONOMY_AUTOLOAD_MIGRATIONS', true),
        'paths' => [],
    ],
];
