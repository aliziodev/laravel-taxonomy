# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).


## [2.6.1](https://github.com/aliziodev/laravel-taxonomy/compare/v2.6.0...v2.6.1) (2025-10-10)

### Bug Fixes

* **database:** use dynamic table names for taxonomy foreign keys [#10](https://github.com/aliziodev/laravel-taxonomy/issues/10) ([2908957](https://github.com/aliziodev/laravel-taxonomy/commit/2908957764e93c6a3be83b96c8f58825153fabfe))

## [2.6.0](https://github.com/aliziodev/laravel-taxonomy/compare/v2.5.0...v2.6.0) (2025-09-02)

### Features

* **taxonomy:** add type-specific operations and query scopes ([20cf611](https://github.com/aliziodev/laravel-taxonomy/commit/20cf611584206bf33747266448e7acb432e10d5a))
* **taxonomy:** add type-specific taxonomy relationship methods ([ee31d44](https://github.com/aliziodev/laravel-taxonomy/commit/ee31d4471f1febbccf7248fde35d7c24a24a4177))

## [2.5.0](https://github.com/aliziodev/laravel-taxonomy/compare/v2.4.8...v2.5.0) (2025-07-22)

### Features

* refactor to configurable model and merged PR [#8](https://github.com/aliziodev/laravel-taxonomy/issues/8) ([#9](https://github.com/aliziodev/laravel-taxonomy/issues/9)) ([409cbe7](https://github.com/aliziodev/laravel-taxonomy/commit/409cbe74d104dc4d3078f4c70fe41b0d27e43447))

## [2.4.8](https://github.com/aliziodev/laravel-taxonomy/compare/v2.4.7...v2.4.8) (2025-07-15)

### Bug Fixes

* **taxonomies:** relation return model from config ([#7](https://github.com/aliziodev/laravel-taxonomy/issues/7)) ([5c9b85e](https://github.com/aliziodev/laravel-taxonomy/commit/5c9b85e01f486276d14b0664b76dfa1e34911aa9))

## [2.4.7](https://github.com/aliziodev/laravel-taxonomy/compare/v2.4.6...v2.4.7) (2025-07-03)

### Bug Fixes

* **ci:** resolve composer dependency management issues in workflows ([3e46e8d](https://github.com/aliziodev/laravel-taxonomy/commit/3e46e8db9f29ec5869d7bcf37c482189d7d9d3c7))

## [2.4.6](https://github.com/aliziodev/laravel-taxonomy/compare/v2.4.5...v2.4.6) (2025-07-03)

### Bug Fixes

* **workflows:** remove invalid --prefer-stable flag from composer install ([4b7a17d](https://github.com/aliziodev/laravel-taxonomy/commit/4b7a17daf99943424d954b5ee68299e5d20f8093))

## [2.4.5](https://github.com/aliziodev/laravel-taxonomy/compare/v2.4.4...v2.4.5) (2025-07-03)

### Bug Fixes

* **workflows:** prevent orchestra/testbench dependency placement issues ([8b5e7e7](https://github.com/aliziodev/laravel-taxonomy/commit/8b5e7e726fe2d8b954b5ec13337eb5586c8fdb7f))

## [2.4.4](https://github.com/aliziodev/laravel-taxonomy/compare/v2.4.3...v2.4.4) (2025-07-03)

### Bug Fixes

* **composer:** move again  orchestra/testbench to require-dev ([2fc16b0](https://github.com/aliziodev/laravel-taxonomy/commit/2fc16b0ed82c186c4cc40acea65d4433bc1afaa0))
* **release:** remove composer.json from semantic-release assets ([8f3b2db](https://github.com/aliziodev/laravel-taxonomy/commit/8f3b2dbb4d53dd304fc79c295eb0d2c62fc98c93))

## [2.4.3](https://github.com/aliziodev/laravel-taxonomy/compare/v2.4.2...v2.4.3) (2025-07-03)

### Bug Fixes

* **ci:** install orchestra/testbench as dev dependency in all workflows ([4c52711](https://github.com/aliziodev/laravel-taxonomy/commit/4c527117aa702401aa9f455e173593d01f3d246e))

## [2.4.2](https://github.com/aliziodev/laravel-taxonomy/compare/v2.4.1...v2.4.2) (2025-07-03)

### Bug Fixes

* **composer:** move orchestra/testbench to require-dev section ([dc5a1a7](https://github.com/aliziodev/laravel-taxonomy/commit/dc5a1a7dde1ca89aca1428687fb708c90db4c6c0))

## [2.4.1](https://github.com/aliziodev/laravel-taxonomy/compare/v2.4.0...v2.4.1) (2025-07-03)

### Bug Fixes

* **taxonomy:** include type in DuplicateSlugException for better error context ([67cdd43](https://github.com/aliziodev/laravel-taxonomy/commit/67cdd4339b2522529e091b27792ee1fbf0c4f830))

## [2.4.0](https://github.com/aliziodev/laravel-taxonomy/compare/v2.3.0...v2.4.0) (2025-07-03)

### Bug Fixes

* **Taxonomy:** include type in DuplicateSlugException for better error context ([6665b4e](https://github.com/aliziodev/laravel-taxonomy/commit/6665b4ed12dc20a8bcb5cbfd645fa8ffdec52a23))

### Features

* **exceptions:** add type context to DuplicateSlugException for better debugging ([defd29b](https://github.com/aliziodev/laravel-taxonomy/commit/defd29b7394a10637bf02167d405b0d61bbb458f))

## [2.3.0](https://github.com/aliziodev/laravel-taxonomy/compare/v2.2.1...v2.3.0) (2025-07-03)

### ⚠ BREAKING CHANGES

* implement composite unique slugs for taxonomies

### Features

* implement composite unique slugs for taxonomies ([c7c6d58](https://github.com/aliziodev/laravel-taxonomy/commit/c7c6d58a47b359ff8c3f502d2882f5f428c65e60))

## [2.2.1](https://github.com/aliziodev/laravel-taxonomy/compare/v2.2.0...v2.2.1) (2025-07-02)

### Bug Fixes

-   move testbench to require-dev (follow-up to [#5](https://github.com/aliziodev/laravel-taxonomy/issues/5) by [@howdu](https://github.com/howdu)) ([17b377e](https://github.com/aliziodev/laravel-taxonomy/commit/17b377e7c697c98438ea28112fadbcc1c4ece267))

## [2.2.0](https://github.com/aliziodev/laravel-taxonomy/compare/v2.1.0...v2.2.0) (2025-06-18)

### Features

-   **HasTaxonomy:** add taxonomy scopes for filtering models ([5cc5a78](https://github.com/aliziodev/laravel-taxonomy/commit/5cc5a783710d8453b34f3434d1324d5c04d5f5b9))
-   **Taxonomy:** add getSiblings method to retrieve same-level taxonomies ([4756c1a](https://github.com/aliziodev/laravel-taxonomy/commit/4756c1ac2a3b4dfa65ecbe82118dada7f6fa27a0))

## [2.1.0](https://github.com/aliziodev/laravel-taxonomy/compare/v2.0.2...v2.1.0) (2025-06-17)

### Features

-   implement automated changelog system ([0c8c6c0](https://github.com/aliziodev/laravel-taxonomy/commit/0c8c6c00b96396cabff2488f18f8321d739e37a0))

## [2.0.0] - 2025-05-31

### 🚀 Major Features Added

#### Nested Set Model Implementation

-   **Added complete Nested Set Model support** for hierarchical data management
-   **Implemented automatic left/right boundary calculation** (`lft`, `rgt`, `depth` fields)
-   **Added efficient tree traversal methods** for ancestors, descendants, and siblings
-   **Integrated automatic nested set maintenance** on create, update, and delete operations

#### Performance Optimizations

-   **Added bulk operations support** for large-scale taxonomy management
-   **Implemented efficient tree rebuilding** with `rebuildNestedSet()` method
-   **Added performance monitoring** and benchmarking capabilities
-   **Optimized database queries** using nested set boundaries instead of recursive queries

#### Advanced Tree Operations

-   **Added `moveToParent()` method** for efficient node repositioning
-   **Implemented cascade delete** with orphan prevention
-   **Added tree validation** and integrity checking
-   **Support for concurrent operations** with race condition handling

#### Console Commands

-   **Added `taxonomy:rebuild-nested-set` command** for rebuilding nested set values
-   **Enhanced `taxonomy:install` command** with better error handling

### 🔧 Technical Improvements

#### Database Schema Enhancements

```sql
-- Added nested set fields to taxonomies table
ALTER TABLE taxonomies ADD COLUMN lft INTEGER;
ALTER TABLE taxonomies ADD COLUMN rgt INTEGER;
ALTER TABLE taxonomies ADD COLUMN depth INTEGER DEFAULT 0;

-- Added indexes for performance
CREATE INDEX idx_taxonomies_nested_set ON taxonomies(type, lft, rgt);
CREATE INDEX idx_taxonomies_parent ON taxonomies(parent_id);
```

#### Model Enhancements

-   **Enhanced Taxonomy model** with nested set methods:
    -   `getAncestors()` - Get all parent nodes
    -   `getDescendants()` - Get all child nodes
    -   `getSiblings()` - Get nodes at same level
    -   `getNestedTree()` - Get complete tree structure
    -   `isAncestorOf()` - Check parent-child relationship
    -   `isDescendantOf()` - Check child-parent relationship

#### Service Layer Improvements

-   **Added TaxonomyManager service** for complex operations
-   **Implemented transaction-safe operations** for data integrity
-   **Added batch processing capabilities** for large datasets
-   **Enhanced error handling** with custom exceptions

### 🧪 Testing Infrastructure

#### Comprehensive Test Suite

-   **Added ExtremeTaxonomyTest** for edge cases and large datasets
-   **Added TaxonomyPerformanceTest** for performance benchmarking
-   **Added TaxonomyConcurrencyTest** for race condition testing
-   **Added NestedSetTest** for nested set specific operations

#### Performance Test Coverage

-   **Move operation efficiency testing** with time assertions
-   **Descendants retrieval performance** for various tree sizes
-   **Delete operations with cascade** and orphan prevention
-   **Memory usage monitoring** for large operations
-   **Concurrent operation handling** with database transactions

### 📊 Performance Metrics

#### Before Nested Set (v1.x)

-   Tree traversal: O(n) recursive queries
-   Ancestor retrieval: Multiple database hits
-   Move operations: Expensive parent_id updates
-   Large trees: Performance degradation

#### After Nested Set (v2.0)

-   Tree traversal: O(1) single query with boundaries
-   Ancestor retrieval: Single query with lft/rgt comparison
-   Move operations: Efficient boundary recalculation
-   Large trees: Consistent performance up to 10,000+ nodes

### 🔄 Migration Guide

#### Database Migration

```bash
# Run the migration to add nested set fields
php artisan migrate

# Rebuild nested set values for existing data
php artisan taxonomy:rebuild-nested-set

# Rebuild specific taxonomy type
php artisan taxonomy:rebuild-nested-set category

# Force rebuild without confirmation
php artisan taxonomy:rebuild-nested-set --force
```

#### Code Updates

```php
// Old way (v1.x)
$children = $taxonomy->children;
$ancestors = $this->getAncestorsRecursively($taxonomy);

// New way (v2.0)
$children = $taxonomy->getDescendants();
$ancestors = $taxonomy->getAncestors();
```

### ⚠️ Breaking Changes

-   **Database schema changes** require migration
-   **Some method signatures changed** for consistency
-   **Performance test thresholds** may need adjustment for different environments
-   **Soft delete behavior** modified for nested set integrity

### 🐛 Bug Fixes

-   **Fixed race conditions** in concurrent move operations
-   **Resolved orphan node issues** in delete operations
-   **Fixed nested set boundary corruption** in edge cases
-   **Corrected performance test assertions** for realistic expectations

### 📚 Documentation

-   **Added comprehensive API documentation** for all nested set methods
-   **Created performance benchmarking guide** with optimization tips
-   **Added migration guide** for upgrading from v1.x
-   **Enhanced README** with nested set usage examples

---

## [1.0.0] - 2025-05-29

### Initial Release

-   Basic taxonomy management with parent-child relationships
-   Simple CRUD operations
-   Basic hierarchical queries using parent_id
-   Soft delete support
-   Multi-type taxonomy support (categories, tags, etc.)

---

# Changelog (Bahasa Indonesia)

Semua perubahan penting pada proyek ini akan didokumentasikan dalam file ini.

## [2.0.0] - 2025-05-31

### 🚀 Fitur Utama yang Ditambahkan

#### Implementasi Nested Set Model

-   **Menambahkan dukungan lengkap Nested Set Model** untuk manajemen data hierarkis
-   **Mengimplementasikan kalkulasi otomatis batas kiri/kanan** (field `lft`, `rgt`, `depth`)
-   **Menambahkan metode traversal tree yang efisien** untuk ancestors, descendants, dan siblings
-   **Mengintegrasikan pemeliharaan nested set otomatis** pada operasi create, update, dan delete

#### Optimisasi Performa

-   **Menambahkan dukungan operasi bulk** untuk manajemen taxonomy skala besar
-   **Mengimplementasikan rebuilding tree yang efisien** dengan metode `rebuildNestedSet()`
-   **Menambahkan monitoring performa** dan kemampuan benchmarking
-   **Mengoptimalkan query database** menggunakan batas nested set alih-alih query rekursif

#### Operasi Tree Lanjutan

-   **Menambahkan metode `moveToParent()`** untuk reposisi node yang efisien
-   **Mengimplementasikan cascade delete** dengan pencegahan orphan
-   **Menambahkan validasi tree** dan pengecekan integritas
-   **Dukungan untuk operasi concurrent** dengan penanganan race condition

#### Console Commands

-   **Menambahkan command `taxonomy:rebuild-nested-set`** untuk rebuilding nilai nested set
-   **Meningkatkan command `taxonomy:install`** dengan error handling yang lebih baik

### 🔧 Peningkatan Teknis

#### Peningkatan Skema Database

```sql
-- Menambahkan field nested set ke tabel taxonomies
ALTER TABLE taxonomies ADD COLUMN lft INTEGER;
ALTER TABLE taxonomies ADD COLUMN rgt INTEGER;
ALTER TABLE taxonomies ADD COLUMN depth INTEGER DEFAULT 0;

-- Menambahkan index untuk performa
CREATE INDEX idx_taxonomies_nested_set ON taxonomies(type, lft, rgt);
CREATE INDEX idx_taxonomies_parent ON taxonomies(parent_id);
```

#### Peningkatan Model

-   **Meningkatkan model Taxonomy** dengan metode nested set:
    -   `getAncestors()` - Mendapatkan semua node parent
    -   `getDescendants()` - Mendapatkan semua node child
    -   `getSiblings()` - Mendapatkan node di level yang sama
    -   `getNestedTree()` - Mendapatkan struktur tree lengkap
    -   `isAncestorOf()` - Mengecek hubungan parent-child
    -   `isDescendantOf()` - Mengecek hubungan child-parent

#### Peningkatan Service Layer

-   **Menambahkan service TaxonomyManager** untuk operasi kompleks
-   **Mengimplementasikan operasi transaction-safe** untuk integritas data
-   **Menambahkan kemampuan batch processing** untuk dataset besar
-   **Meningkatkan error handling** dengan custom exceptions

### 🧪 Infrastruktur Testing

#### Test Suite Komprehensif

-   **Menambahkan ExtremeTaxonomyTest** untuk edge cases dan dataset besar
-   **Menambahkan TaxonomyPerformanceTest** untuk benchmarking performa
-   **Menambahkan TaxonomyConcurrencyTest** untuk testing race condition
-   **Menambahkan NestedSetTest** untuk operasi spesifik nested set

#### Coverage Test Performa

-   **Testing efisiensi operasi move** dengan assertion waktu
-   **Performa retrieval descendants** untuk berbagai ukuran tree
-   **Operasi delete dengan cascade** dan pencegahan orphan
-   **Monitoring penggunaan memori** untuk operasi besar
-   **Penanganan operasi concurrent** dengan transaksi database

### 📊 Metrik Performa

#### Sebelum Nested Set (v1.x)

-   Traversal tree: O(n) query rekursif
-   Retrieval ancestor: Multiple database hits
-   Operasi move: Update parent_id yang mahal
-   Tree besar: Degradasi performa

#### Setelah Nested Set (v2.0)

-   Traversal tree: O(1) single query dengan boundaries
-   Retrieval ancestor: Single query dengan perbandingan lft/rgt
-   Operasi move: Rekalkulasi boundary yang efisien
-   Tree besar: Performa konsisten hingga 10,000+ nodes

### 🔄 Panduan Migrasi

#### Migrasi Database

```bash
# Jalankan migrasi untuk menambahkan field nested set
php artisan migrate

# Rebuild nilai nested set untuk data yang sudah ada
php artisan taxonomy:rebuild-nested-set

# Rebuild tipe taxonomy tertentu
php artisan taxonomy:rebuild-nested-set category

# Force rebuild tanpa konfirmasi
php artisan taxonomy:rebuild-nested-set --force
```

#### Update Kode

```php
// Cara lama (v1.x)
$children = $taxonomy->children;
$ancestors = $this->getAncestorsRecursively($taxonomy);

// Cara baru (v2.0)
$children = $taxonomy->getDescendants();
$ancestors = $taxonomy->getAncestors();
```

### ⚠️ Breaking Changes

-   **Perubahan skema database** memerlukan migrasi
-   **Beberapa signature metode berubah** untuk konsistensi
-   **Threshold test performa** mungkin perlu penyesuaian untuk environment berbeda
-   **Perilaku soft delete dimodifikasi** untuk integritas nested set

### 🐛 Perbaikan Bug

-   **Memperbaiki race conditions** dalam operasi move concurrent
-   **Mengatasi masalah orphan node** dalam operasi delete
-   **Memperbaiki korupsi boundary nested set** dalam edge cases
-   **Mengoreksi assertion test performa** untuk ekspektasi yang realistis

### 📚 Dokumentasi

-   **Menambahkan dokumentasi API komprehensif** untuk semua metode nested set
-   **Membuat panduan benchmarking performa** dengan tips optimisasi
-   **Menambahkan panduan migrasi** untuk upgrade dari v1.x
-   **Meningkatkan README** dengan contoh penggunaan nested set

---

## [1.0.0] - 2025-05-29

### Rilis Awal

-   Manajemen taxonomy dasar dengan hubungan parent-child
-   Operasi CRUD sederhana
-   Query hierarkis dasar menggunakan parent_id
-   Dukungan soft delete
-   Dukungan multi-type taxonomy (categories, tags, dll.)
