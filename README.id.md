<picture>
    <source media="(prefers-color-scheme: dark)" srcset="art/header-dark.png">
    <img alt="Logo for essentials" src="art/header-light.png">
</picture>

# Laravel Taxonomy

[![Tests](https://github.com/aliziodev/laravel-taxonomy/workflows/Tests/badge.svg)](https://github.com/aliziodev/laravel-taxonomy/actions)
[![Code Quality](https://github.com/aliziodev/laravel-taxonomy/workflows/Code%20Quality/badge.svg)](https://github.com/aliziodev/laravel-taxonomy/actions)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/aliziodev/laravel-taxonomy.svg)](https://packagist.org/packages/aliziodev/laravel-taxonomy)
[![Total Downloads](https://img.shields.io/packagist/dt/aliziodev/laravel-taxonomy.svg)](https://packagist.org/packages/aliziodev/laravel-taxonomy)
[![License](https://img.shields.io/packagist/l/aliziodev/laravel-taxonomy.svg)](https://packagist.org/packages/aliziodev/laravel-taxonomy)
[![PHP Version](https://img.shields.io/packagist/php-v/aliziodev/laravel-taxonomy.svg)](https://packagist.org/packages/aliziodev/laravel-taxonomy)
[![Laravel Version](https://img.shields.io/badge/Laravel-11.0%2B-orange.svg)](https://laravel.com/)

Laravel Taxonomy adalah paket yang fleksibel dan powerful untuk mengelola taksonomi, kategori, tag, dan struktur hierarkis dalam aplikasi Laravel. Dilengkapi dengan dukungan nested-set untuk performa query optimal pada struktur data hierarkis.

## 📖 Dokumentasi

-   [🇺🇸 English Documentation](README.md)
-   [🇮🇩 Dokumentasi Bahasa Indonesia](README.id.md)

## 📋 Daftar Isi

-   [Gambaran Umum](#gambaran-umum)
-   [Fitur Utama](#fitur-utama)
-   [Persyaratan](#persyaratan)
-   [Instalasi](#instalasi)
-   [Konfigurasi](#️-konfigurasi)
-   [Memulai Cepat](#-memulai-cepat)
-   [Penggunaan Dasar](#-penggunaan-dasar)
-   [Data Hierarkis & Nested Sets](#-data-hierarkis--nested-sets)
-   [Dukungan Metadata](#-dukungan-metadata)
-   [Operasi Bulk](#-operasi-bulk)
-   [Caching & Performa](#-caching--performa)
-   [Tipe Taksonomi Kustom](#️-tipe-taksonomi-kustom)
-   [Skenario Penggunaan Dunia Nyata](#-skenario-penggunaan-dunia-nyata)
-   [Fitur Lanjutan](#-fitur-lanjutan)
-   [Best Practices](#-best-practices)
-   [Slug Kustom dan Error Handling](#slug-kustom-dan-error-handling)
-   [Troubleshooting](#troubleshooting)
-   [Keamanan](#keamanan)
-   [Testing](#testing)
-   [📝 Changelog Otomatis](#-changelog-otomatis)
-   [Contributing](#contributing)
-   [Lisensi](#lisensi)

## Gambaran Umum

Paket ini ideal untuk:

-   Manajemen kategori e-commerce
-   Taksonomi blog
-   Organisasi konten
-   Atribut produk
-   Navigasi dinamis
-   Struktur data hierarkis apapun

## Fitur Utama

### Fungsionalitas Inti

-   **Term Hierarkis**: Membuat hubungan parent-child antar term
-   **Dukungan Metadata**: Menyimpan data tambahan sebagai JSON dengan setiap taksonomi
-   **Pengurutan Term**: Mengontrol urutan term dengan sort_order
-   **Relasi Polimorfik**: Mengasosiasikan taksonomi dengan model apapun
-   **Multiple Tipe Term**: Menggunakan tipe yang sudah didefinisikan (Category, Tag, dll.) atau membuat tipe kustom
-   **Slug Unik Komposit**: Slug unik dalam tipe mereka, memungkinkan slug yang sama di tipe berbeda
-   **Operasi Bulk**: Attach, detach, sync, atau toggle multiple taksonomi sekaligus
-   **Query Lanjutan**: Filter model berdasarkan taksonomi dengan query scopes

### Fitur Nested Set

-   **Navigasi Tree**: Query ancestor dan descendant yang efisien
-   **Manipulasi Tree**: Memindahkan, menyisipkan, dan mengatur ulang node tree
-   **Manajemen Depth**: Melacak dan query berdasarkan kedalaman hierarki
-   **Validasi Tree**: Mempertahankan integritas tree secara otomatis
-   **Query Efisien**: Query database yang dioptimalkan untuk data hierarkis

### Performa & Skalabilitas

-   **Sistem Caching**: Meningkatkan performa dengan caching built-in
-   **Indexing Database**: Index yang dioptimalkan untuk query cepat
-   **Lazy Loading**: Loading relasi yang efisien
-   **Struktur Tree**: Mendapatkan representasi tree hierarkis atau flat
-   **Dukungan Pagination**: Pagination hasil untuk performa yang lebih baik

### Developer Experience

-   **API Intuitif**: Sintaks yang bersih dan ekspresif
-   **Dokumentasi Komprehensif**: Panduan dan contoh yang detail
-   **Type Safety**: Dukungan penuh untuk sistem type Laravel
-   **Dukungan Testing**: Utilitas testing built-in

## Persyaratan

-   PHP 8.2+
-   Laravel 11.0+ or 12.0+
-   Composer 2.0+

## Instalasi

### Via Composer

```bash
composer require aliziodev/laravel-taxonomy
```

### Publish Konfigurasi dan Migrasi

Anda dapat mempublish konfigurasi dan migrasi menggunakan perintah install yang disediakan:

```bash
php artisan taxonomy:install
```

Atau secara manual:

```bash
php artisan vendor:publish --provider="Aliziodev\LaravelTaxonomy\TaxonomyProvider" --tag="taxonomy-config"
php artisan vendor:publish --provider="Aliziodev\LaravelTaxonomy\TaxonomyProvider" --tag="taxonomy-migrations"
```

### Jalankan Migrasi

```bash
php artisan migrate
```

## ⚙️ Konfigurasi

Setelah mempublish file konfigurasi, Anda dapat menyesuaikannya di `config/taxonomy.php`. Berikut penjelasan detail dari setiap opsi:

```php
return [
    // Nama tabel database
    'table_names' => [
        'taxonomies' => 'taxonomies',      // Tabel taksonomi utama
        'taxonomables' => 'taxonomables',  // Tabel pivot polimorfik
    ],

    // Tipe primary key untuk relasi polimorfik
    // Opsi: 'numeric' (default), 'uuid', 'ulid'
    'morph_type' => 'uuid',

    // Tipe taksonomi yang tersedia (dapat diperluas)
    'types' => collect(TaxonomyType::cases())->pluck('value')->toArray(),

    // Binding model kustom (untuk memperluas model Taxonomy dasar)
    'model' => \Aliziodev\LaravelTaxonomy\Models\Taxonomy::class,

    // Pengaturan generasi slug
    'slugs' => [
        'generate' => true,                // Auto-generate slug dari nama
        'regenerate_on_update' => false,  // Regenerate slug ketika nama berubah
    ],
];
```

### Penjelasan Opsi Konfigurasi

#### Nama Tabel

Sesuaikan nama tabel database jika Anda perlu menghindari konflik atau mengikuti konvensi penamaan tertentu:

```php
'table_names' => [
    'taxonomies' => 'custom_taxonomies',
    'taxonomables' => 'custom_taxonomables',
],
```

#### Tipe Morph

Pilih tipe morph yang sesuai berdasarkan primary key model Anda:

```php
// Untuk ID integer auto-incrementing
'morph_type' => 'numeric',

// Untuk primary key UUID
'morph_type' => 'uuid',

// Untuk primary key ULID
'morph_type' => 'ulid',
```

#### Tipe Kustom

Perluas atau ganti tipe taksonomi default:

```php
'types' => [
    'category',
    'tag',
    'brand',
    'collection',
    'custom_type',
],
```

#### Konfigurasi Slug

Kontrol perilaku generasi slug:

```php
'slugs' => [
    'generate' => false,               // Memerlukan input slug manual
    'regenerate_on_update' => true,   // Auto-update slug ketika nama berubah
],
```

#### Penting: Batasan Unik Komposit

Mulai dari versi 2.3.0, slug unik dalam tipe taksonomi mereka, bukan secara global. Ini berarti:

-   ✅ Anda dapat memiliki `slug: 'featured'` untuk tipe `Category` dan `Tag`
-   ✅ Fleksibilitas yang lebih baik untuk mengorganisir tipe taksonomi yang berbeda
-   ⚠️ **Breaking Change**: Jika upgrade dari v2.2.x, lihat [UPGRADE.md](UPGRADE.md) untuk instruksi migrasi

```php
// Ini sekarang dimungkinkan:
Taxonomy::create(['name' => 'Featured', 'slug' => 'featured', 'type' => 'category']);
Taxonomy::create(['name' => 'Featured', 'slug' => 'featured', 'type' => 'tag']);

// Tetapi ini akan gagal (slug duplikat dalam tipe yang sama):
// Taxonomy::create(['name' => 'Another Featured', 'slug' => 'featured', 'type' => 'category']); // Error!
```

#### Error Handling dan Validasi

Ketika bekerja dengan slug unik komposit, penting untuk memahami bahwa keunikan diberlakukan dalam tipe taksonomi yang sama:

```php
try {
    // Ini akan berhasil - slug yang sama, tipe berbeda
    $category = Taxonomy::create([
        'name' => 'Electronics',
        'slug' => 'electronics',
        'type' => TaxonomyType::Category
    ]);

    $tag = Taxonomy::create([
        'name' => 'Electronics',
        'slug' => 'electronics',
        'type' => TaxonomyType::Tag
    ]);

    // Ini akan gagal - slug duplikat dalam tipe yang sama
    $duplicateCategory = Taxonomy::create([
        'name' => 'Consumer Electronics',
        'slug' => 'electronics',
        'type' => TaxonomyType::Category // Error: Duplicate slug dalam category
    ]);

} catch (\Illuminate\Database\QueryException $e) {
    // Handle constraint violation
    if ($e->getCode() === '23000') {
        throw new \Exception('Slug sudah digunakan dalam tipe taksonomi ini.');
    }
}
```

## 🚀 Memulai Cepat

Mulai dan jalankan Laravel Taxonomy dalam hitungan menit:

### 1. Buat Taksonomi Pertama Anda

```php
use Aliziodev\LaravelTaxonomy\Facades\Taxonomy;
use Aliziodev\LaravelTaxonomy\Enums\TaxonomyType;

// Buat kategori
$electronics = Taxonomy::create([
    'name' => 'Electronics',
    'type' => TaxonomyType::Category->value,
    'description' => 'Produk elektronik dan gadget',
]);

// Buat subkategori
$smartphones = Taxonomy::create([
    'name' => 'Smartphones',
    'type' => TaxonomyType::Category->value,
    'parent_id' => $electronics->id,
]);

// Buat tag
$featured = Taxonomy::create([
    'name' => 'Featured',
    'type' => TaxonomyType::Tag->value,
]);
```

### 2. Asosiasikan dengan Model

```php
// Asumsikan Anda memiliki model Product dengan trait HasTaxonomy
$product = Product::create([
    'name' => 'iPhone 15 Pro',
    'price' => 999.99,
]);

// Attach taksonomi
$product->attachTaxonomies([$electronics->id, $smartphones->id, $featured->id]);

// Atau attach berdasarkan slug
$product->attachTaxonomies(['electronics', 'smartphones', 'featured']);
```

### 3. Query dan Filter

```php
// Temukan produk dalam kategori electronics
$products = Product::withTaxonomyType(TaxonomyType::Category)
    ->withTaxonomySlug('electronics')
    ->get();

// Dapatkan semua taksonomi dari tipe tertentu
$categories = Taxonomy::findByType(TaxonomyType::Category);

// Dapatkan tree hierarkis
$categoryTree = Taxonomy::tree(TaxonomyType::Category);
```

## 📖 Penggunaan Dasar

### Bekerja dengan Facade Taxonomy

Facade `Taxonomy` menyediakan API yang bersih dan ekspresif untuk semua operasi taksonomi:

```php
use Aliziodev\LaravelTaxonomy\Facades\Taxonomy;
use Aliziodev\LaravelTaxonomy\Enums\TaxonomyType;

// Buat taksonomi
$category = Taxonomy::create([
    'name' => 'Books',
    'type' => TaxonomyType::Category->value,
    'description' => 'Semua jenis buku',
    'meta' => [
        'icon' => 'book',
        'color' => '#3498db',
        'featured' => true,
    ],
]);

// Temukan taksonomi
$taxonomy = Taxonomy::findBySlug('books');
$exists = Taxonomy::exists('books');
$categories = Taxonomy::findByType(TaxonomyType::Category);

// Cari taksonomi
$results = Taxonomy::search('science', TaxonomyType::Category);

// Dapatkan data hierarkis
$tree = Taxonomy::tree(TaxonomyType::Category);
$flatTree = Taxonomy::flatTree(TaxonomyType::Category);
$nestedTree = Taxonomy::getNestedTree(TaxonomyType::Category);
```

### Bekerja dengan Relasi Model

Setelah Anda menambahkan trait `HasTaxonomy` ke model Anda, Anda mendapatkan akses ke metode relasi yang powerful:

```php
// Operasi dasar
$product->attachTaxonomies($taxonomyIds);
$product->detachTaxonomies($taxonomyIds);
$product->syncTaxonomies($taxonomyIds);
$product->toggleTaxonomies($taxonomyIds);

// Periksa relasi
$hasCategory = $product->hasTaxonomies($categoryIds);
$hasAllTags = $product->hasAllTaxonomies($tagIds);
$hasType = $product->hasTaxonomyType(TaxonomyType::Category);

// Dapatkan taksonomi terkait
$allTaxonomies = $product->taxonomies;
$categories = $product->taxonomiesOfType(TaxonomyType::Category);
$hierarchical = $product->getHierarchicalTaxonomies(TaxonomyType::Category);
```

### Query Scopes untuk Filtering

Filter model Anda menggunakan query scopes yang powerful:

```php
// Filter berdasarkan tipe taksonomi
$products = Product::withTaxonomyType(TaxonomyType::Category)->get();

// Filter berdasarkan taksonomi tertentu
$products = Product::withAnyTaxonomies([$category1, $category2])->get();
$products = Product::withAllTaxonomies([$tag1, $tag2])->get();

// Filter berdasarkan slug taksonomi (tipe apapun)
$products = Product::withTaxonomySlug('electronics')->get();

// Filter berdasarkan slug taksonomi dengan tipe tertentu (direkomendasikan)
$products = Product::withTaxonomySlug('electronics', TaxonomyType::Category)->get();

// Filter berdasarkan hierarki (termasuk turunan)
$products = Product::withTaxonomyHierarchy($parentCategoryId)->get();

// Filter berdasarkan level kedalaman
$products = Product::withTaxonomyAtDepth(2, TaxonomyType::Category)->get();
```

#### Chaining Scope vs Single Scope dengan Type

Dengan batasan unik komposit, Anda memiliki dua pendekatan untuk filtering:

```php
// Pendekatan 1: Single scope dengan parameter type (Direkomendasikan)
// Mencari produk dengan taxonomy slug='electronics' DAN type='category'
$products = Product::withTaxonomySlug('electronics', TaxonomyType::Category)->get();

// Pendekatan 2: Chaining scopes (Lebih fleksibel untuk query kompleks)
// Mencari produk yang memiliki SEMBARANG taxonomy dengan type='category' DAN SEMBARANG taxonomy dengan slug='electronics'
$products = Product::withTaxonomyType(TaxonomyType::Category)
    ->withTaxonomySlug('electronics')
    ->get();

// Catatan: Ini mungkin mengembalikan hasil yang berbeda jika produk memiliki multiple taxonomies
```

### Keunikan Slug (Unik Komposit)

Mulai dari versi 3.0, package ini menggunakan batasan unik komposit untuk slug, yang berarti slug hanya perlu unik dalam tipe taksonomi yang sama:

```php
// Sekarang dimungkinkan - slug yang sama untuk tipe berbeda
$category = Taxonomy::create([
    'name' => 'Featured Products',
    'slug' => 'featured',
    'type' => TaxonomyType::Category
]);

$tag = Taxonomy::create([
    'name' => 'Featured Items',
    'slug' => 'featured',
    'type' => TaxonomyType::Tag
]);

// Tetapi ini akan gagal - slug duplikat dalam tipe yang sama
try {
    $duplicateCategory = Taxonomy::create([
        'name' => 'Another Featured',
        'slug' => 'featured',
        'type' => TaxonomyType::Category // Error: Duplicate!
    ]);
} catch (\Illuminate\Database\QueryException $e) {
    // Handle constraint violation
    echo 'Slug sudah digunakan dalam tipe taksonomi ini.';
}
```

#### Manfaat Batasan Unik Komposit

-   **Fleksibilitas Lebih Tinggi**: Slug yang sama dapat digunakan di tipe taksonomi berbeda
-   **Organisasi yang Lebih Baik**: Setiap tipe taksonomi memiliki namespace slug sendiri
-   **Skalabilitas**: Mengurangi konflik slug saat aplikasi berkembang
-   **Konsistensi**: Memungkinkan penamaan yang konsisten di berbagai tipe

#### Query dengan Slug Komposit

Ketika melakukan query berdasarkan slug, disarankan untuk menyertakan tipe:

```php
// Direkomendasikan - spesifik tipe
$electronics = Taxonomy::where('slug', 'electronics')
    ->where('type', TaxonomyType::Category)
    ->first();

// Atau menggunakan scope
$products = Product::withTaxonomySlug('electronics', TaxonomyType::Category)->get();

// Hati-hati - tanpa tipe bisa mengembalikan hasil yang tidak diinginkan
$ambiguous = Taxonomy::where('slug', 'electronics')->get(); // Bisa mengembalikan multiple results
```

### Dukungan Pagination

Package ini mendukung pagination untuk metode search dan find:

```php
// Paginate hasil pencarian (5 item per halaman, halaman 1)
$results = Taxonomy::search('electronic', null, 5, 1);

// Paginate taksonomi berdasarkan tipe
$categories = Taxonomy::findByType(TaxonomyType::Category, 10, 1);

// Paginate taksonomi berdasarkan parent
$children = Taxonomy::findByParent($parent->id, 10, 1);
```

### Contoh Controller Lengkap

Berikut adalah contoh komprehensif penggunaan Laravel Taxonomy dalam controller:

```php
namespace App\Http\Controllers;

use App\Models\Product;
use Aliziodev\LaravelTaxonomy\Enums\TaxonomyType;
use Aliziodev\LaravelTaxonomy\Facades\Taxonomy;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        // Dapatkan produk yang difilter berdasarkan kategori
        $categorySlug = $request->input('category');
        $query = Product::query();

        if ($categorySlug) {
            $category = Taxonomy::findBySlug($categorySlug, TaxonomyType::Category);
            if ($category) {
                $query->withAnyTaxonomies($category);
            }
        }

        $products = $query->paginate(12);
        $categories = Taxonomy::findByType(TaxonomyType::Category);

        return view('products.index', compact('products', 'categories'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'categories' => 'required|array',
            'tags' => 'nullable|array',
        ]);

        $product = Product::create($validated);

        // Lampirkan kategori dan tag
        $product->attachTaxonomies($validated['categories']);
        if (isset($validated['tags'])) {
            $product->attachTaxonomies($validated['tags']);
        }

        return redirect()->route('products.show', $product)
            ->with('success', 'Produk berhasil dibuat.');
    }
}
```

## 🌳 Data Hierarkis & Nested Sets

Laravel Taxonomy menggunakan Nested Set Model untuk manajemen data hierarkis yang efisien:

### Memahami Nested Sets

Model nested set menyimpan data hierarkis menggunakan nilai `lft` (kiri) dan `rgt` (kanan), bersama dengan `depth` untuk setiap node. Ini memungkinkan query relasi hierarkis yang efisien.

```php
// Dapatkan semua turunan dari taksonomi (anak, cucu, dll.)
$descendants = $taxonomy->getDescendants();

// Dapatkan semua leluhur dari taksonomi (parent, grandparent, dll.)
$ancestors = $taxonomy->getAncestors();

// Periksa relasi hierarkis
$isParent = $parent->isAncestorOf($child);
$isChild = $child->isDescendantOf($parent);

// Dapatkan level kedalaman
$level = $taxonomy->getLevel();

// Dapatkan hanya taksonomi root
$roots = Taxonomy::roots()->get();

// Dapatkan taksonomi pada kedalaman tertentu
$level2 = Taxonomy::atDepth(2)->get();
```

### Operasi Tree

```php
// Pindahkan taksonomi ke parent baru
Taxonomy::moveToParent($taxonomyId, $newParentId);

// Rebuild nilai nested set (berguna setelah operasi bulk)
Taxonomy::rebuildNestedSet(TaxonomyType::Category);

// Dapatkan representasi tree yang berbeda
$hierarchicalTree = Taxonomy::tree(TaxonomyType::Category);           // Relasi parent-child
$flatTree = Taxonomy::flatTree(TaxonomyType::Category);               // List flat dengan info depth
$nestedTree = Taxonomy::getNestedTree(TaxonomyType::Category);        // Urutan nested set
```

## 📊 Dukungan Metadata

Simpan data tambahan dengan setiap taksonomi menggunakan meta JSON:

```php
// Buat taksonomi dengan meta
$category = Taxonomy::create([
    'name' => 'Premium Products',
    'type' => TaxonomyType::Category->value,
    'meta' => [
        'icon' => 'star',
        'color' => '#gold',
        'display_order' => 1,
        'seo' => [
            'title' => 'Premium Products - Best Quality',
            'description' => 'Temukan koleksi produk premium kami',
            'keywords' => ['premium', 'quality', 'luxury'],
        ],
        'settings' => [
            'show_in_menu' => true,
            'featured' => true,
            'requires_auth' => false,
        ],
    ],
]);

// Akses metadata
$icon = $category->meta['icon'] ?? 'default';
$seoTitle = $category->meta['seo']['title'] ?? $category->name;

// Update meta
$category->update([
    'meta' => array_merge($category->meta ?? [], [
        'updated_at' => now()->toISOString(),
        'view_count' => ($category->meta['view_count'] ?? 0) + 1,
    ]),
]);
```

## 🔄 Operasi Bulk

Kelola relasi taksonomi multiple secara efisien:

### Operasi Bulk Dasar

```php
// Lampirkan multiple taksonomi (tidak akan duplikasi yang sudah ada)
$product->attachTaxonomies([1, 2, 3, 'electronics', 'featured']);

// Lepaskan taksonomi tertentu
$product->detachTaxonomies([1, 2]);

// Lepaskan semua taksonomi
$product->detachTaxonomies();

// Sync taksonomi (hapus yang lama, tambah yang baru)
$product->syncTaxonomies([1, 2, 3]);

// Toggle taksonomi (lampirkan jika tidak ada, lepaskan jika ada)
$product->toggleTaxonomies([1, 2, 3]);

// Bekerja dengan nama relasi yang berbeda
$product->attachTaxonomies($categoryIds, 'categories');
$product->attachTaxonomies($tagIds, 'tags');
```

### Manajemen Bulk Lanjutan

```php
class BulkTaxonomyService
{
    public function bulkAttach(Collection $models, array $taxonomyIds): void
    {
        $data = [];
        $timestamp = now();

        foreach ($models as $model) {
            foreach ($taxonomyIds as $taxonomyId) {
                $data[] = [
                    'taxonomy_id' => $taxonomyId,
                    'taxonomable_id' => $model->id,
                    'taxonomable_type' => get_class($model),
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ];
            }
        }

        DB::table('taxonomables')->insert($data);
    }

    public function bulkDetach(Collection $models, array $taxonomyIds): void
    {
        $modelIds = $models->pluck('id');
        $modelType = get_class($models->first());

        DB::table('taxonomables')
            ->whereIn('taxonomy_id', $taxonomyIds)
            ->whereIn('taxonomable_id', $modelIds)
            ->where('taxonomable_type', $modelType)
            ->delete();
    }

    public function bulkSync(Collection $models, array $taxonomyIds): void
    {
        $modelIds = $models->pluck('id');
        $modelType = get_class($models->first());

        // Hapus asosiasi yang ada
        DB::table('taxonomables')
            ->whereIn('taxonomable_id', $modelIds)
            ->where('taxonomable_type', $modelType)
            ->delete();

        // Tambah asosiasi baru
        $this->bulkAttach($models, $taxonomyIds);
    }

    public function migrateType(string $oldType, string $newType): int
    {
        return Taxonomy::where('type', $oldType)
            ->update(['type' => $newType]);
    }

    public function mergeTaxonomies(Taxonomy $source, Taxonomy $target): void
    {
        DB::transaction(function () use ($source, $target) {
            // Pindahkan semua asosiasi ke target
            DB::table('taxonomables')
                ->where('taxonomy_id', $source->id)
                ->update(['taxonomy_id' => $target->id]);

            // Pindahkan children ke target
            Taxonomy::where('parent_id', $source->id)
                ->update(['parent_id' => $target->id]);

            // Hapus taksonomi sumber
            $source->delete();

            // Rebuild nested set untuk tree target
            $target->rebuildNestedSet();
        });
    }
}
```

## ⚡ Caching & Performance

Laravel Taxonomy menyertakan caching cerdas untuk performa optimal:

### Automatic Caching

```php
// Operasi ini secara otomatis di-cache
$tree = Taxonomy::tree(TaxonomyType::Category);           // Di-cache selama 24 jam
$flatTree = Taxonomy::flatTree(TaxonomyType::Category);   // Di-cache selama 24 jam
$nestedTree = Taxonomy::getNestedTree(TaxonomyType::Category); // Di-cache selama 24 jam
```

### Manajemen Cache Manual

```php
// Bersihkan cache untuk tipe tertentu
Taxonomy::clearCacheForType(TaxonomyType::Category);

// Cache otomatis dibersihkan ketika:
// - Taksonomi dibuat, diupdate, atau dihapus
// - Nested set di-rebuild
// - Taksonomi dipindahkan dalam hierarki
```

### Tips Performance

```php
// Gunakan eager loading untuk menghindari N+1 queries
$products = Product::with(['taxonomies' => function ($query) {
    $query->where('type', TaxonomyType::Category->value);
}])->get();

// Gunakan pagination untuk dataset besar
$taxonomies = Taxonomy::findByType(TaxonomyType::Category, 20); // 20 per halaman

// Gunakan query spesifik daripada memuat semua relasi
$categories = $product->taxonomiesOfType(TaxonomyType::Category);
```

## 🏷️ Tipe Taksonomi Kustom

Meskipun package ini dilengkapi dengan tipe taksonomi yang telah didefinisikan dalam enum `TaxonomyType` (Category, Tag, Color, Size, dll.), Anda dapat dengan mudah mendefinisikan dan menggunakan tipe kustom Anda sendiri.

### Mendefinisikan Tipe Kustom

Ada dua cara untuk menggunakan tipe taksonomi kustom:

#### 1. Override konfigurasi types

Anda dapat override tipe default dengan memodifikasi array `types` dalam file `config/taxonomy.php` Anda:

```php
'types' => [
    'category',
    'tag',
    // Tipe default yang ingin Anda pertahankan

    // Tipe kustom Anda
    'genre',
    'location',
    'season',
    'difficulty',
],
```

#### 2. Gunakan tipe kustom secara langsung

Anda juga dapat menggunakan tipe kustom secara langsung dalam kode Anda tanpa memodifikasi konfigurasi:

```php
use Aliziodev\LaravelTaxonomy\Facades\Taxonomy;

// Buat taksonomi dengan tipe kustom
$genre = Taxonomy::create([
    'name' => 'Science Fiction',
    'type' => 'genre', // Tipe kustom tidak didefinisikan dalam enum TaxonomyType
    'description' => 'Genre science fiction',
]);

// Temukan taksonomi berdasarkan tipe kustom
$genres = Taxonomy::findByType('genre');

// Periksa apakah model memiliki taksonomi dari tipe kustom
$product->hasTaxonomyType('genre');

// Dapatkan taksonomi dari tipe kustom
$productGenres = $product->taxonomiesOfType('genre');

// Filter model berdasarkan tipe taksonomi kustom
$products = Product::withTaxonomyType('genre')->get();
```

### Membuat Enum Tipe Kustom

Untuk type safety dan organisasi yang lebih baik, Anda dapat membuat enum sendiri untuk tipe kustom:

```php
namespace App\Enums;

enum GenreType: string
{
    case SciFi = 'sci-fi';
    case Fantasy = 'fantasy';
    case Horror = 'horror';
    case Romance = 'romance';
    case Mystery = 'mystery';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
```

Kemudian gunakan dalam kode Anda:

```php
use App\Enums\GenreType;
use Aliziodev\LaravelTaxonomy\Facades\Taxonomy;

// Buat taksonomi dengan tipe kustom dari enum
$genre = Taxonomy::create([
    'name' => 'Science Fiction',
    'type' => GenreType::SciFi->value,
    'description' => 'Genre science fiction',
]);

// Temukan taksonomi berdasarkan tipe kustom dari enum
$sciFiBooks = Taxonomy::findByType(GenreType::SciFi);
```

## 🎯 Skenario Penggunaan Real-World

Untuk contoh komprehensif cara menggunakan Laravel Taxonomy dalam aplikasi real-world, silakan merujuk ke dokumentasi skenario detail kami:

### Skenario yang Tersedia

1. **[Katalog Produk E-commerce](docs/id/ecommerce-product-catalog.md)** - Membangun platform e-commerce komprehensif dengan kategori hierarkis, tag produk, navigasi dinamis, dan filtering lanjutan.

2. **[Sistem Manajemen Konten](docs/id/content-management-system.md)** - Membuat CMS fleksibel dengan kategorisasi konten, tagging, filtering, dan optimasi SEO.

3. **[Sistem Manajemen Pembelajaran](docs/id/learning-management-system.md)** - Mengembangkan platform edukasi dengan kursus, skill, level kesulitan, dan jalur pembelajaran personal.

4. **[Aplikasi Bisnis Multi-tenant](docs/id/multi-tenant-business-application.md)** - Membangun platform SaaS dengan taksonomi spesifik tenant, manajemen proyek, dan workflow yang dapat dikustomisasi.

5. **[Analytics dan Reporting](docs/id/analytics-and-reporting.md)** - Mengimplementasikan analytics komprehensif, dashboard reporting, dan insight otomatis menggunakan data taksonomi.

Setiap skenario mencakup:

-   Contoh kode lengkap
-   Setup database dan migrasi
-   Implementasi controller
-   Pola service layer

## 🚀 Fitur Lanjutan

### 🔄 Nested Set Model

Laravel Taxonomy menggunakan Nested Set Model untuk manajemen data hierarkis yang efisien:

```php
// Dapatkan semua turunan dari taksonomi
$category = Taxonomy::find(1);
$descendants = $category->getDescendants();

// Dapatkan semua leluhur dari taksonomi
$ancestors = $category->getAncestors();

// Dapatkan siblings
$siblings = $category->getSiblings();

// Periksa apakah taksonomi adalah turunan dari yang lain
$isDescendant = $category->isDescendantOf($parentCategory);
```

### Optimasi Performance

**Strategi Caching**:

```php
// Cache taxonomy trees untuk performa yang lebih baik
class CachedTaxonomyService
{
    public function getCachedTree(string $type, int $ttl = 3600): Collection
    {
        return Cache::remember("taxonomy_tree_{$type}", $ttl, function () use ($type) {
            return Taxonomy::tree($type);
        });
    }

    public function invalidateCache(string $type): void
    {
        Cache::forget("taxonomy_tree_{$type}");
    }

    public function warmCache(): void
    {
        $types = Taxonomy::distinct('type')->pluck('type');

        foreach ($types as $type) {
            $this->getCachedTree($type);
        }
    }
}

// Gunakan dalam model Anda
class Product extends Model
{
    use HasTaxonomy;

    protected static function booted()
    {
        static::saved(function ($product) {
            // Invalidate cache terkait ketika taksonomi produk berubah
            $types = $product->taxonomies->pluck('type')->unique();
            foreach ($types as $type) {
                Cache::forget("taxonomy_tree_{$type}");
            }
        });
    }
}
```

**Eager Loading untuk Performance**:

```php
// Loading taksonomi dengan model secara efisien
$products = Product::with([
    'taxonomies' => function ($query) {
        $query->select('id', 'name', 'slug', 'type', 'meta')
              ->orderBy('type')
              ->orderBy('name');
    }
])->get();

// Load hanya tipe taksonomi tertentu
$products = Product::with([
    'taxonomies' => function ($query) {
        $query->whereIn('type', ['category', 'brand']);
    }
])->get();

// Preload jumlah taksonomi
$categories = Taxonomy::where('type', 'category')
    ->withCount(['models as product_count' => function ($query) {
        $query->where('taxonomable_type', Product::class);
    }])
    ->get();
```

### Query Lanjutan

**Filter Taksonomi Kompleks**:

```php
class ProductFilterService
{
    public function filterByTaxonomies(array $filters): Builder
    {
        $query = Product::query();

        // Filter berdasarkan multiple kategori (kondisi OR)
        if (!empty($filters['categories'])) {
            $query->withAnyTaxonomies($filters['categories']);
        }

        // Filter berdasarkan tag yang diperlukan (kondisi AND)
        if (!empty($filters['required_tags'])) {
            $query->withAllTaxonomies($filters['required_tags']);
        }

        // Filter berdasarkan brand (exact match)
        if (!empty($filters['brand'])) {
            $query->withTaxonomy($filters['brand']);
        }

        // Filter berdasarkan taksonomi rentang harga
        if (!empty($filters['price_range'])) {
            $priceRange = Taxonomy::findBySlug($filters['price_range'], 'price_range');
            if ($priceRange) {
                $min = $priceRange->meta['min_price'] ?? 0;
                $max = $priceRange->meta['max_price'] ?? PHP_INT_MAX;
                $query->whereBetween('price', [$min, $max]);
            }
        }

        // Kecualikan taksonomi tertentu
        if (!empty($filters['exclude'])) {
            $query->withoutTaxonomies($filters['exclude']);
        }

        return $query;
    }

    public function getFilterOptions(array $currentFilters = []): array
    {
        $baseQuery = $this->filterByTaxonomies($currentFilters);

        return [
            'categories' => $this->getAvailableOptions($baseQuery, 'category'),
            'brands' => $this->getAvailableOptions($baseQuery, 'brand'),
            'tags' => $this->getAvailableOptions($baseQuery, 'tag'),
            'price_ranges' => $this->getAvailableOptions($baseQuery, 'price_range'),
        ];
    }

    private function getAvailableOptions(Builder $query, string $type): Collection
    {
        return Taxonomy::where('type', $type)
            ->whereHas('models', function ($q) use ($query) {
                $q->whereIn('taxonomable_id', $query->pluck('id'));
            })
            ->withCount('models')
            ->orderBy('models_count', 'desc')
            ->get();
    }
}
```

### Import/Export Data

**Fungsionalitas Import/Export**:

```php
class TaxonomyImportExportService
{
    public function exportToJson(string $type = null): string
    {
        $query = Taxonomy::with('children');

        if ($type) {
            $query->where('type', $type);
        }

        $taxonomies = $query->whereNull('parent_id')
            ->orderBy('lft')
            ->get();

        return json_encode($this->buildExportTree($taxonomies), JSON_PRETTY_PRINT);
    }

    public function importFromJson(string $json, bool $replaceExisting = false): array
    {
        $data = json_decode($json, true);
        $imported = [];
        $errors = [];

        DB::transaction(function () use ($data, $replaceExisting, &$imported, &$errors) {
            foreach ($data as $item) {
                try {
                    $taxonomy = $this->importTaxonomyItem($item, null, $replaceExisting);
                    $imported[] = $taxonomy->id;
                } catch (Exception $e) {
                    $errors[] = [
                        'item' => $item['name'] ?? 'Unknown',
                        'error' => $e->getMessage(),
                    ];
                }
            }
        });

        return [
            'imported' => count($imported),
            'errors' => $errors,
            'taxonomy_ids' => $imported,
        ];
    }

    private function buildExportTree(Collection $taxonomies): array
    {
        return $taxonomies->map(function ($taxonomy) {
            $item = [
                'name' => $taxonomy->name,
                'slug' => $taxonomy->slug,
                'type' => $taxonomy->type,
                'description' => $taxonomy->description,
                'meta' => $taxonomy->meta,
                'sort_order' => $taxonomy->sort_order,
            ];

            if ($taxonomy->children->isNotEmpty()) {
                $item['children'] = $this->buildExportTree($taxonomy->children);
            }

            return $item;
        })->toArray();
    }

    private function importTaxonomyItem(array $item, ?int $parentId, bool $replaceExisting): Taxonomy
    {
        $existing = null;

        if ($replaceExisting) {
            $existing = Taxonomy::where('slug', $item['slug'])
                ->where('type', $item['type'])
                ->first();
        }

        $taxonomy = $existing ?: new Taxonomy();

        $taxonomy->fill([
            'name' => $item['name'],
            'slug' => $item['slug'],
            'type' => $item['type'],
            'description' => $item['description'] ?? null,
            'parent_id' => $parentId,
            'meta' => $item['meta'] ?? [],
            'sort_order' => $item['sort_order'] ?? 0,
        ]);

        $taxonomy->save();

        // Import children
        if (!empty($item['children'])) {
            foreach ($item['children'] as $child) {
                $this->importTaxonomyItem($child, $taxonomy->id, $replaceExisting);
            }
        }

        return $taxonomy;
    }

    public function exportToCsv(string $type): string
    {
        $taxonomies = Taxonomy::where('type', $type)
            ->with('parent')
            ->orderBy('lft')
            ->get();

        $csv = "Name,Slug,Type,Parent,Description,Meta\n";

        foreach ($taxonomies as $taxonomy) {
            $csv .= sprintf(
                "\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\"\n",
                $taxonomy->name,
                $taxonomy->slug,
                $taxonomy->type,
                $taxonomy->parent?->name ?? '',
                $taxonomy->description ?? '',
                json_encode($taxonomy->meta)
            );
        }

        return $csv;
    }
}
```

## 📋 Praktik Terbaik

### 1. **Prinsip Desain Taksonomi**

```php
// ✅ Baik: Tipe taksonomi yang jelas dan spesifik
class TaxonomyTypes
{
    const PRODUCT_CATEGORY = 'product_category';
    const PRODUCT_TAG = 'product_tag';
    const CONTENT_CATEGORY = 'content_category';
    const USER_SKILL = 'user_skill';
}

// ❌ Hindari: Tipe yang generik dan ambigu
// 'category', 'tag', 'type' - terlalu generik
```

### 2. **Praktik Terbaik Metadata**

```php
// ✅ Baik: meta terstruktur dengan validasi
class CategoryMetadata
{
    public static function validate(array $metadata): array
    {
        return Validator::make($metadata, [
            'icon' => 'nullable|string|max:50',
            'color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'featured' => 'boolean',
            'seo_title' => 'nullable|string|max:60',
            'seo_description' => 'nullable|string|max:160',
        ])->validated();
    }
}

// Penggunaan
$category = Taxonomy::create([
    'name' => 'Electronics',
    'type' => TaxonomyTypes::PRODUCT_CATEGORY,
    'meta' => CategoryMetadata::validate([
        'icon' => 'laptop',
        'color' => '#007bff',
        'featured' => true,
    ]),
]);
```

### 3. **Optimasi Performa**

```php
// ✅ Baik: Query efisien dengan indexing yang tepat
class OptimizedTaxonomyQueries
{
    public function getProductsByCategory(string $categorySlug): Collection
    {
        return Product::select(['id', 'name', 'price', 'slug'])
            ->withTaxonomy(
                Taxonomy::where('slug', $categorySlug)
                    ->where('type', TaxonomyTypes::PRODUCT_CATEGORY)
                    ->first()
            )
            ->with(['taxonomies' => function ($query) {
                $query->select(['id', 'name', 'slug', 'type'])
                      ->whereIn('type', [TaxonomyTypes::PRODUCT_TAG, 'brand']);
            }])
            ->limit(20)
            ->get();
    }

    // ✅ Baik: Operasi batch untuk performa yang lebih baik
    public function attachCategoriesInBatch(Collection $products, array $categoryIds): void
    {
        $products->chunk(100)->each(function ($chunk) use ($categoryIds) {
            foreach ($chunk as $product) {
                $product->attachTaxonomies($categoryIds);
            }
        });
    }
}
```

### 4. **Penanganan Error dan Validasi**

```php
class TaxonomyService
{
    public function createWithValidation(array $data): Taxonomy
    {
        $validator = Validator::make($data, [
            'name' => 'required|string|max:255',
            'type' => 'required|string|max:50',
            'parent_id' => 'nullable|exists:taxonomies,id',
            'meta' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        // Periksa referensi melingkar
        if (isset($data['parent_id'])) {
            $this->validateNoCircularReference($data['parent_id'], $data);
        }

        return Taxonomy::create($validator->validated());
    }

    private function validateNoCircularReference(int $parentId, array $data): void
    {
        $parent = Taxonomy::find($parentId);

        if (!$parent) {
            throw new InvalidArgumentException('Parent taxonomy not found');
        }

        // Periksa apakah tipe parent cocok (aturan bisnis opsional)
        if ($parent->type !== $data['type']) {
            throw new InvalidArgumentException('Parent must be of the same type');
        }

        // Cegah nesting yang terlalu dalam (aturan bisnis opsional)
        if ($parent->depth >= 5) {
            throw new InvalidArgumentException('Maximum nesting depth exceeded');
        }
    }
}
```

### 5. **Strategi Testing**

```php
class TaxonomyTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTestTaxonomies();
    }

    private function createTestTaxonomies(): void
    {
        $this->electronics = Taxonomy::create([
            'name' => 'Electronics',
            'type' => 'category',
        ]);

        $this->smartphones = Taxonomy::create([
            'name' => 'Smartphones',
            'type' => 'category',
            'parent_id' => $this->electronics->id,
        ]);
    }

    /** @test */
    public function it_can_attach_taxonomies_to_models(): void
    {
        $product = Product::factory()->create();

        $product->attachTaxonomy($this->electronics);

        $this->assertTrue($product->hasTaxonomy($this->electronics));
        $this->assertCount(1, $product->taxonomies);
    }

    /** @test */
    public function it_maintains_nested_set_integrity(): void
    {
        $this->electronics->rebuildNestedSet();

        $this->electronics->refresh();
        $this->smartphones->refresh();

        $this->assertEquals(1, $this->electronics->lft);
        $this->assertEquals(4, $this->electronics->rgt);
        $this->assertEquals(2, $this->smartphones->lft);
        $this->assertEquals(3, $this->smartphones->rgt);
    }
}
```

## Slug Kustom dan Penanganan Error

Paket ini menyediakan penanganan error yang robust untuk generasi slug dan keunikan:

### Manajemen Slug Manual

Ketika `slugs.generate` diatur ke `false` dalam konfigurasi, Anda harus menyediakan slug secara manual:

```php
// Ini akan melempar MissingSlugException jika slugs.generate adalah false
$taxonomy = Taxonomy::create([
    'name' => 'Test Category',
    'type' => TaxonomyType::Category->value,
    // Slug yang hilang akan menyebabkan exception
]);

// Cara yang benar ketika slugs.generate adalah false
$taxonomy = Taxonomy::create([
    'name' => 'Test Category',
    'type' => TaxonomyType::Category->value,
    'slug' => 'test-category', // Slug yang disediakan secara manual
]);
```

### Keunikan Slug

Paket ini memastikan bahwa semua slug unik di seluruh tipe taksonomi:

```php
// Ini akan melempar DuplicateSlugException jika slug sudah ada
$taxonomy1 = Taxonomy::create([
    'name' => 'First Category',
    'slug' => 'unique-slug',
    'type' => TaxonomyType::Category->value,
]);

// Ini akan melempar DuplicateSlugException karena slug sudah ada
$taxonomy2 = Taxonomy::create([
    'name' => 'Second Category',
    'slug' => 'unique-slug', // Slug duplikat
    'type' => TaxonomyType::Tag->value, // Bahkan dengan tipe yang berbeda
]);
```

### Penanganan Exception

Paket ini menyediakan exception berikut:

-   `MissingSlugException`: Dilempar ketika slug diperlukan tetapi tidak disediakan
-   `DuplicateSlugException`: Dilempar ketika slug sudah ada dan slug unik diperlukan

Anda dapat menangkap exception ini untuk menyediakan penanganan error kustom:

```php
use Aliziodev\LaravelTaxonomy\Exceptions\MissingSlugException;
use Aliziodev\LaravelTaxonomy\Exceptions\DuplicateSlugException;

try {
    $taxonomy = Taxonomy::create([
        'name' => 'Test Category',
        'type' => TaxonomyType::Category->value,
    ]);
} catch (MissingSlugException $e) {
    // Tangani error slug yang hilang
    return back()->withErrors(['slug' => 'A slug is required.']);
} catch (DuplicateSlugException $e) {
    // Tangani error slug duplikat
    return back()->withErrors(['slug' => 'This slug already exists. Please choose another.']);
}
```

## Troubleshooting

### Masalah Umum

#### Taksonomi Tidak Ditemukan

Jika Anda mengalami kesulitan menemukan taksonomi berdasarkan slug, pastikan slug sudah benar dan pertimbangkan menggunakan method `exists` untuk memeriksa apakah ada:

```php
if (Taxonomy::exists('electronics')) {
    $taxonomy = Taxonomy::findBySlug('electronics');
}
```

#### Masalah Relasi

Jika Anda mengalami masalah dengan relasi, pastikan Anda menggunakan morph type yang benar dalam konfigurasi. Jika Anda menggunakan UUID atau ULID untuk model Anda, pastikan untuk mengatur konfigurasi `morph_type` sesuai.

#### Masalah Cache

Jika Anda tidak melihat data yang diperbarui setelah melakukan perubahan, Anda mungkin perlu membersihkan cache:

```php
\Illuminate\Support\Facades\Cache::flush();
```

## Keamanan

Paket Laravel Taxonomy mengikuti praktik keamanan yang baik:

-   Menggunakan prepared statements untuk semua query database untuk mencegah SQL injection
-   Memvalidasi data input sebelum diproses
-   Menggunakan mekanisme perlindungan bawaan Laravel

Jika Anda menemukan masalah keamanan, silakan email penulis di aliziodev@gmail.com daripada menggunakan issue tracker.

## Testing

Paket ini menyertakan tes yang komprehensif. Anda dapat menjalankannya dengan:

```bash
composer test

// atau

vendor/bin/pest
```

## 📝 Changelog Otomatis

Paket ini menggunakan **generasi changelog otomatis** berdasarkan [Conventional Commits](https://www.conventionalcommits.org/) dan [Semantic Versioning](https://semver.org/).

### Cara Kerjanya

-   **Analisis Commit**: Setiap pesan commit dianalisis untuk menentukan jenis perubahan
-   **Versioning Otomatis**: Nomor versi ditentukan secara otomatis berdasarkan jenis commit
-   **Generasi Changelog**: `CHANGELOG.md` diperbarui secara otomatis dengan catatan rilis
-   **GitHub Releases**: Rilis dibuat secara otomatis dengan catatan rilis yang detail

### Format Pesan Commit

```
<type>[optional scope]: <description>

[optional body]

[optional footer(s)]
```

**Contoh:**

```bash
feat: add moveToParent method with performance optimization
fix: resolve nested set corruption on concurrent operations
feat!: change taxonomy structure for multi-tenancy support
```

### Jenis Rilis

| Jenis Commit                         | Jenis Rilis   | Contoh                  |
| ------------------------------------ | ------------- | ----------------------- |
| `fix:`                               | Patch (1.0.1) | Perbaikan bug           |
| `feat:`                              | Minor (1.1.0) | Fitur baru              |
| `feat!:` atau `BREAKING CHANGE:`     | Major (2.0.0) | Perubahan yang merusak  |
| `docs:`, `style:`, `test:`, `chore:` | Tidak Rilis   | Dokumentasi, formatting |

### Workflow Otomatis

-   **Auto Changelog**: Dipicu pada setiap push ke branch main
-   **Commitlint**: Memvalidasi pesan commit pada PR dan push
-   **Pembuatan Rilis**: Secara otomatis membuat GitHub releases dengan changelog

## Contributing

Silakan lihat [CONTRIBUTING](CONTRIBUTING.md) untuk detail tentang sistem changelog otomatis dan workflow pengembangan kami.

## Lisensi

Paket Laravel Taxonomy adalah software open-source yang dilisensikan di bawah [lisensi MIT](https://opensource.org/licenses/MIT).
