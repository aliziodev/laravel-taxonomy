[![en](https://img.shields.io/badge/lang-en-red.svg)](README.md)
[![id](https://img.shields.io/badge/lang-id-blue.svg)](README.id.md)

# Laravel Taxonomy

[![Latest Version on Packagist](https://img.shields.io/packagist/v/aliziodev/laravel-taxonomy.svg?style=flat-square)](https://packagist.org/packages/aliziodev/laravel-taxonomy)
[![Total Downloads](https://img.shields.io/packagist/dt/aliziodev/laravel-taxonomy.svg?style=flat-square)](https://packagist.org/packages/aliziodev/laravel-taxonomy)
[![License](https://img.shields.io/packagist/l/aliziodev/laravel-taxonomy.svg?style=flat-square)](https://packagist.org/packages/aliziodev/laravel-taxonomy)
[![PHP Version](https://img.shields.io/packagist/php-v/aliziodev/laravel-taxonomy.svg?style=flat-square)](https://packagist.org/packages/aliziodev/laravel-taxonomy)
[![Laravel Version](https://img.shields.io/badge/Laravel-11.0%2B-orange.svg?style=flat-square)](https://laravel.com/)

Laravel Taxonomy adalah paket yang kuat dan fleksibel untuk mengelola taksonomi, kategori, tag, dan istilah hierarkis dalam aplikasi Laravel. Paket ini menyediakan solusi yang kokoh untuk mengorganisir konten dengan fitur seperti dukungan metadata, kemampuan pengurutan, dan mekanisme caching yang efisien.

## Ikhtisar

Paket ini ideal untuk:

- Manajemen kategori e-commerce
- Taksonomi blog
- Organisasi konten
- Atribut produk
- Navigasi dinamis
- Struktur data hierarkis lainnya

## Fitur Utama

- **Istilah Hierarkis**: Buat hubungan induk-anak antar istilah
- **Dukungan Metadata**: Simpan data tambahan sebagai JSON dengan setiap taksonomi
- **Pengurutan Istilah**: Kontrol urutan istilah dengan sort_order
- **Sistem Caching**: Tingkatkan performa dengan caching bawaan
- **Hubungan Polimorfik**: Kaitkan taksonomi dengan model apapun
- **Beberapa Jenis Istilah**: Gunakan jenis yang telah ditentukan (Kategori, Tag, dll.) atau buat jenis kustom
- **Operasi Massal**: Lampirkan, lepaskan, sinkronkan, atau toggle beberapa taksonomi sekaligus
- **Kueri Lanjutan**: Filter model berdasarkan taksonomi dengan query scope
- **Struktur Pohon**: Dapatkan representasi pohon hierarkis atau datar
- **Dukungan Paginasi**: Paginasi hasil untuk performa yang lebih baik

## Persyaratan

- PHP 8.1+
- Laravel 11.0+

## Instalasi

### Melalui Composer

```bash
composer require aliziodev/laravel-taxonomy
```

### Publikasikan Konfigurasi dan Migrasi

Anda dapat mempublikasikan konfigurasi dan migrasi menggunakan perintah install yang disediakan:

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

## Konfigurasi

Setelah mempublikasikan file konfigurasi, Anda dapat menyesuaikannya di `config/taxonomy.php`:

```php
return [
    // Nama tabel untuk taksonomi dan taxonomables
    'table_names' => [
        'taxonomies' => 'taxonomies',
        'taxonomables' => 'taxonomables',
    ],

    // Konfigurasi morph type (numeric, uuid, ulid)
    'morph_type' => 'uuid',

    // Jenis taksonomi default
    'types' => collect(TaxonomyType::cases())->pluck('value')->toArray(),

    // Binding model untuk model Taxonomy
    'model' => \Aliziodev\LaravelTaxonomy\Models\Taxonomy::class,

    // Konfigurasi slug
    'slugs' => [
        'generate' => true,        // Jika false, slug harus disediakan secara manual
        'regenerate_on_update' => false,  // Jika true, slug akan dibuat ulang ketika nama berubah
    ],
];
```

## Jenis Taksonomi Kustom

Meskipun paket ini dilengkapi dengan jenis taksonomi yang telah ditentukan dalam enum `TaxonomyType` (Category, Tag, Color, Size, dll.), Anda dapat dengan mudah mendefinisikan dan menggunakan jenis kustom Anda sendiri.

### Mendefinisikan Jenis Kustom

Ada dua cara untuk menggunakan jenis taksonomi kustom:

#### 1. Mengganti konfigurasi jenis

Anda dapat mengganti jenis default dengan memodifikasi array `types` di file `config/taxonomy.php` Anda:

```php
'types' => [
    'category',
    'tag',
    // Jenis default yang ingin Anda pertahankan

    // Jenis kustom Anda
    'genre',
    'lokasi',
    'musim',
    'tingkat_kesulitan',
],
```

#### 2. Menggunakan jenis kustom secara langsung

Anda juga dapat menggunakan jenis kustom secara langsung dalam kode Anda tanpa memodifikasi konfigurasi:

```php
use Aliziodev\LaravelTaxonomy\Facades\Taxonomy;

// Membuat taksonomi dengan jenis kustom
$genre = Taxonomy::create([
    'name' => 'Fiksi Ilmiah',
    'type' => 'genre', // Jenis kustom yang tidak didefinisikan dalam enum TaxonomyType
    'description' => 'Genre fiksi ilmiah',
]);

// Mencari taksonomi berdasarkan jenis kustom
$genres = Taxonomy::findByType('genre');

// Memeriksa apakah model memiliki taksonomi dari jenis kustom
$product->hasTaxonomyType('genre');

// Mendapatkan taksonomi dari jenis kustom
$productGenres = $product->taxonomiesOfType('genre');

// Memfilter model berdasarkan jenis taksonomi kustom
$products = Product::withTaxonomyType('genre')->get();
```

### Membuat Enum Jenis Kustom

Untuk keamanan tipe yang lebih baik dan organisasi, Anda dapat membuat enum Anda sendiri untuk jenis kustom:

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

// Membuat taksonomi dengan jenis kustom dari enum
$genre = Taxonomy::create([
    'name' => 'Fiksi Ilmiah',
    'type' => GenreType::SciFi->value,
    'description' => 'Genre fiksi ilmiah',
]);

// Mencari taksonomi berdasarkan jenis kustom dari enum
$sciFiBooks = Taxonomy::findByType(GenreType::SciFi);
```

## Slug Kustom dan Penanganan Error

Paket ini menyediakan penanganan error yang kuat untuk pembuatan dan keunikan slug:

### Pengelolaan Slug Manual

Ketika `slugs.generate` diatur ke `false` dalam konfigurasi, Anda harus menyediakan slug secara manual:

```php
// Ini akan melempar MissingSlugException jika slugs.generate adalah false
$taxonomy = Taxonomy::create([
    'name' => 'Kategori Uji',
    'type' => TaxonomyType::Category->value,
    // Slug yang tidak disediakan akan menyebabkan exception
]);

// Cara yang benar ketika slugs.generate adalah false
$taxonomy = Taxonomy::create([
    'name' => 'Kategori Uji',
    'type' => TaxonomyType::Category->value,
    'slug' => 'kategori-uji', // Slug yang disediakan secara manual
]);
```

### Keunikan Slug

Paket ini memastikan bahwa semua slug unik di seluruh jenis taksonomi:

```php
// Ini akan melempar DuplicateSlugException jika slug sudah ada
$taxonomy1 = Taxonomy::create([
    'name' => 'Kategori Pertama',
    'slug' => 'slug-unik',
    'type' => TaxonomyType::Category->value,
]);

// Ini akan melempar DuplicateSlugException karena slug sudah ada
$taxonomy2 = Taxonomy::create([
    'name' => 'Kategori Kedua',
    'slug' => 'slug-unik', // Slug duplikat
    'type' => TaxonomyType::Tag->value, // Bahkan dengan jenis yang berbeda
]);
```

### Penanganan Exception

Paket ini menyediakan exception berikut:

- `MissingSlugException`: Dilempar ketika slug diperlukan tetapi tidak disediakan
- `DuplicateSlugException`: Dilempar ketika slug sudah ada dan slug unik diperlukan

Anda dapat menangkap exception ini untuk menyediakan penanganan error kustom:

```php
use Aliziodev\LaravelTaxonomy\Exceptions\MissingSlugException;
use Aliziodev\LaravelTaxonomy\Exceptions\DuplicateSlugException;

try {
    $taxonomy = Taxonomy::create([
        'name' => 'Kategori Uji',
        'type' => TaxonomyType::Category->value,
    ]);
} catch (MissingSlugException $e) {
    // Menangani error slug yang tidak disediakan
    return back()->withErrors(['slug' => 'Slug diperlukan.']);
} catch (DuplicateSlugException $e) {
    // Menangani error slug duplikat
    return back()->withErrors(['slug' => 'Slug ini sudah ada. Silakan pilih yang lain.']);
}
```

## Penggunaan

### Menggunakan Facade Taxonomy

Facade Taxonomy menyediakan cara yang nyaman untuk bekerja dengan taksonomi:

```php
use Aliziodev\LaravelTaxonomy\Facades\Taxonomy;
use Aliziodev\LaravelTaxonomy\Enums\TaxonomyType;

// Membuat taksonomi
$category = Taxonomy::create([
    'name' => 'Elektronik',
    'type' => TaxonomyType::Category->value,
    'description' => 'Produk elektronik',
]);

// Membuat taksonomi dengan induk
$smartphone = Taxonomy::create([
    'name' => 'Smartphone',
    'type' => TaxonomyType::Category->value,
    'parent_id' => $category->id,
]);

// Mencari taksonomi berdasarkan slug
$found = Taxonomy::findBySlug('elektronik');

// Memeriksa apakah taksonomi ada
$exists = Taxonomy::exists('elektronik');

// Mencari taksonomi
$results = Taxonomy::search('elektronik');

// Mendapatkan jenis taksonomi
$types = Taxonomy::getTypes();

// Mendapatkan pohon hierarkis
$tree = Taxonomy::tree(TaxonomyType::Category);

// Mendapatkan pohon datar dengan informasi kedalaman
$flatTree = Taxonomy::flatTree(TaxonomyType::Category);
```

### Menggunakan Trait HasTaxonomy

Tambahkan trait `HasTaxonomy` ke model Anda untuk mengaitkannya dengan taksonomi:

```php
use Aliziodev\LaravelTaxonomy\Traits\HasTaxonomy;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasTaxonomy;

    // ...
}
```

Kemudian Anda dapat menggunakan metode berikut:

```php
// Mendapatkan produk
$product = Product::find(1);

// Melampirkan taksonomi
$product->attachTaxonomies($category);
$product->attachTaxonomies([$category, $tag]);

// Melepaskan taksonomi
$product->detachTaxonomies($category);
$product->detachTaxonomies(); // Lepaskan semua

// Sinkronisasi taksonomi (hapus yang ada dan tambahkan yang baru)
$product->syncTaxonomies([$category, $tag]);

// Toggle taksonomi (tambahkan jika tidak ada, hapus jika ada)
$product->toggleTaxonomies([$category, $tag]);

// Memeriksa apakah produk memiliki taksonomi
$product->hasTaxonomies($category);
$product->hasAllTaxonomies([$category, $tag]);
$product->hasTaxonomyType(TaxonomyType::Category);

// Mendapatkan taksonomi dari jenis tertentu
$categories = $product->taxonomiesOfType(TaxonomyType::Category);
```

### Query Scope

Trait `HasTaxonomy` juga menyediakan query scope:

```php
// Mencari produk dengan salah satu taksonomi yang diberikan
$products = Product::withAnyTaxonomies([$category, $tag])->get();

// Mencari produk dengan semua taksonomi yang diberikan
$products = Product::withAllTaxonomies([$category, $tag])->get();

// Mencari produk dengan jenis taksonomi tertentu
$products = Product::withTaxonomyType(TaxonomyType::Category)->get();
```

### Paginasi

Paket ini mendukung paginasi untuk metode pencarian dan pencarian:

```php
// Paginasi hasil pencarian (5 item per halaman, halaman 1)
$results = Taxonomy::search('elektronik', null, 5, 1);

// Paginasi taksonomi berdasarkan jenis
$categories = Taxonomy::findByType(TaxonomyType::Category, 10, 1);

// Paginasi taksonomi berdasarkan induk
$children = Taxonomy::findByParent($parent->id, 10, 1);
```

### Contoh Controller

Berikut adalah contoh lengkap penggunaan Laravel Taxonomy dalam controller:

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
        // Mendapatkan produk yang difilter berdasarkan kategori
        $categorySlug = $request->input('category');

        $query = Product::query();

        if ($categorySlug) {
            $category = Taxonomy::findBySlug($categorySlug, TaxonomyType::Category);

            if ($category) {
                $query->withAnyTaxonomies($category);
            }
        }

        $products = $query->paginate(12);

        // Mendapatkan semua kategori untuk sidebar filter
        $categories = Taxonomy::findByType(TaxonomyType::Category);

        return view('products.index', compact('products', 'categories'));
    }

    public function show(Product $product)
    {
        // Mendapatkan kategori produk
        $categories = $product->taxonomiesOfType(TaxonomyType::Category);

        // Mendapatkan produk terkait yang berbagi kategori yang sama
        $relatedProducts = Product::withAnyTaxonomies($categories)
            ->where('id', '!=', $product->id)
            ->limit(4)
            ->get();

        return view('products.show', compact('product', 'categories', 'relatedProducts'));
    }

    public function create()
    {
        // Mendapatkan semua kategori untuk form produk
        $categories = Taxonomy::tree(TaxonomyType::Category);
        $tags = Taxonomy::findByType(TaxonomyType::Tag);

        return view('products.create', compact('categories', 'tags'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'price' => 'required|numeric',
            'categories' => 'required|array',
            'tags' => 'nullable|array',
        ]);

        $product = Product::create([
            'name' => $validated['name'],
            'description' => $validated['description'],
            'price' => $validated['price'],
        ]);

        // Melampirkan kategori dan tag
        $product->attachTaxonomies($validated['categories']);

        if (isset($validated['tags'])) {
            $product->attachTaxonomies($validated['tags']);
        }

        return redirect()->route('products.show', $product)
            ->with('success', 'Produk berhasil dibuat.');
    }

    public function edit(Product $product)
    {
        $categories = Taxonomy::tree(TaxonomyType::Category);
        $tags = Taxonomy::findByType(TaxonomyType::Tag);

        $productCategoryIds = $product->taxonomiesOfType(TaxonomyType::Category)->pluck('id')->toArray();
        $productTagIds = $product->taxonomiesOfType(TaxonomyType::Tag)->pluck('id')->toArray();

        return view('products.edit', compact('product', 'categories', 'tags', 'productCategoryIds', 'productTagIds'));
    }

    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'price' => 'required|numeric',
            'categories' => 'required|array',
            'tags' => 'nullable|array',
        ]);

        $product->update([
            'name' => $validated['name'],
            'description' => $validated['description'],
            'price' => $validated['price'],
        ]);

        // Sinkronisasi kategori dan tag
        $product->syncTaxonomies($validated['categories'], 'taxonomable');

        if (isset($validated['tags'])) {
            $product->syncTaxonomies($validated['tags'], 'taxonomable');
        } else {
            // Hapus semua tag jika tidak ada yang dipilih
            $product->detachTaxonomies($product->taxonomiesOfType(TaxonomyType::Tag), 'taxonomable');
        }

        return redirect()->route('products.show', $product)
            ->with('success', 'Produk berhasil diperbarui.');
    }
}
```

## Pemecahan Masalah

### Masalah Umum

#### Taksonomi Tidak Ditemukan

Jika Anda mengalami kesulitan menemukan taksonomi berdasarkan slug, pastikan slug sudah benar dan pertimbangkan untuk menggunakan metode `exists` untuk memeriksa apakah taksonomi tersebut ada:

```php
if (Taxonomy::exists('elektronik')) {
    $taxonomy = Taxonomy::findBySlug('elektronik');
}
```

#### Masalah Hubungan

Jika Anda mengalami kesulitan dengan hubungan, pastikan Anda menggunakan morph type yang benar dalam konfigurasi Anda. Jika Anda menggunakan UUID atau ULID untuk model Anda, pastikan untuk mengatur konfigurasi `morph_type` dengan benar.

#### Masalah Cache

Jika Anda tidak melihat data yang diperbarui setelah melakukan perubahan, Anda mungkin perlu membersihkan cache:

```php
\Illuminate\Support\Facades\Cache::flush();
```

## Keamanan

Paket Laravel Taxonomy mengikuti praktik keamanan yang baik:

- Menggunakan prepared statements untuk semua kueri database untuk mencegah SQL injection
- Memvalidasi data input sebelum diproses
- Menggunakan mekanisme perlindungan bawaan Laravel

Jika Anda menemukan masalah keamanan, silakan kirim email ke penulis di aliziodev@gmail.com daripada menggunakan issue tracker.

## Pengujian

Paket ini menyertakan pengujian yang komprehensif. Anda dapat menjalankannya dengan:

```bash
composer test
```

## Lisensi

Paket Laravel Taxonomy adalah perangkat lunak open-source yang dilisensikan di bawah [lisensi MIT](https://opensource.org/licenses/MIT).
