<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableNames = config('taxonomy.table_names');
        $morphType = config('taxonomy.morph_type', 'uuid');

        Schema::create($tableNames['taxonomies'], function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('type')->index();
            $table->text('description')->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('taxonomies');
            $table->integer('sort_order')->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create($tableNames['taxonomables'], function (Blueprint $table) use ($morphType) {
            $table->id();
            $table->foreignId('taxonomy_id')->constrained()->cascadeOnDelete();

            $name = 'taxonomable';
            switch ($morphType) {
                case 'uuid':
                    $table->uuidMorphs($name);
                    break;
                case 'ulid':
                    $table->ulidMorphs($name);
                    break;
                default:
                    $table->morphs($name);
                    break;
            }
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $tableNames = config('taxonomy.table_names');

        Schema::dropIfExists($tableNames['taxonomables']);
        Schema::dropIfExists($tableNames['taxonomies']);
    }

};
