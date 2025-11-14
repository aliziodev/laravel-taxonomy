<?php

use Aliziodev\LaravelTaxonomy\Enums\TaxonomyType;

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
    'model' => \Aliziodev\LaravelTaxonomy\Models\Taxonomy::class,

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
