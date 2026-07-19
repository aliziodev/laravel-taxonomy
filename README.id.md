<p align="center"><img src="https://raw.githubusercontent.com/aliziodev/laravel-taxonomy/refs/heads/master/art/new-header.svg" width="400" alt="Laravel Taxonomy"></p>

<p align="center">
  <a href="https://codecov.io/gh/aliziodev/laravel-taxonomy"><img src="https://codecov.io/gh/aliziodev/laravel-taxonomy/branch/master/graph/badge.svg" alt="codecov"></a>
  <a href="https://github.com/aliziodev/laravel-taxonomy/actions"><img src="https://github.com/aliziodev/laravel-taxonomy/workflows/Tests/badge.svg" alt="Tests"></a>
  <a href="https://github.com/aliziodev/laravel-taxonomy/actions"><img src="https://github.com/aliziodev/laravel-taxonomy/workflows/Code%20Quality/badge.svg" alt="Code Quality"></a>
</br>
  <a href="https://packagist.org/packages/aliziodev/laravel-taxonomy"><img src="https://img.shields.io/packagist/v/aliziodev/laravel-taxonomy.svg" alt="Latest Version on Packagist"></a>
  <a href="https://packagist.org/packages/aliziodev/laravel-taxonomy"><img src="https://img.shields.io/packagist/dt/aliziodev/laravel-taxonomy.svg" alt="Total Downloads"></a>
  <a href="https://packagist.org/packages/aliziodev/laravel-taxonomy"><img src="https://img.shields.io/packagist/php-v/aliziodev/laravel-taxonomy.svg" alt="PHP Version"></a>
  <a href="https://laravel.com/"><img src="https://img.shields.io/badge/Laravel-11%20%7C%2012%20%7C%2013-orange.svg" alt="Laravel Version"></a>
  <a href="https://deepwiki.com/aliziodev/laravel-taxonomy"><img src="https://deepwiki.com/badge.svg" alt="Ask DeepWiki"></a>
</p>

Kelola kategori, tag, dan struktur hierarkis apa pun di Laravel. Semua term disimpan dalam satu tabel, dilampirkan ke model mana pun lewat pivot polimorfik, dan hierarkinya dipelihara sebagai nested set sehingga pencarian ancestor dan descendant tetap satu query.

[🇺🇸 English documentation](README.md)

## Daftar Isi

- [Kebutuhan](#kebutuhan) · [Instalasi](#instalasi) · [Konfigurasi](#konfigurasi)
- [Mulai cepat](#mulai-cepat) · [Mengelola taksonomi](#mengelola-taksonomi) · [Impor massal](#impor-massal) · [Melampirkan ke model](#melampirkan-ke-model)
- [Query scope](#query-scope) · [Hierarki](#hierarki) · [Type](#type) · [Metadata](#metadata)
- [Cache](#cache) · [Multitenancy](#multitenancy) · [Slug dan exception](#slug-dan-exception)
- [Perintah artisan](#perintah-artisan) · [Contoh](#contoh) · [Pemecahan masalah](#pemecahan-masalah)

## Kebutuhan

| Kebutuhan | Versi |
|---|---|
| PHP | 8.2 atau lebih baru |
| Laravel | 11, 12, atau 13 |

## Instalasi

```bash
composer require aliziodev/laravel-taxonomy
php artisan taxonomy:install
php artisan migrate
```

`taxonomy:install` mempublikasikan config dan migration. Gunakan `--force` untuk menimpa berkas yang sudah ada — tanpa itu berkas lama dibiarkan dan perintahnya memberi tahu Anda.

Untuk mempublikasikan satu per satu:

```bash
php artisan vendor:publish --tag=taxonomy-config
php artisan vendor:publish --tag=taxonomy-migrations
```

## Konfigurasi

`config/taxonomy.php`, dengan nilai bawaan:

```php
return [
    'table_names' => [
        'taxonomies'   => 'taxonomies',
        'taxonomables' => 'taxonomables',
    ],

    // Tipe kolom morph pada pivot: 'numeric', 'uuid', atau 'ulid'.
    // Harus sesuai cara model ANDA dikunci. Tetapkan sebelum migrate pertama.
    'morph_type' => 'uuid',

    'types' => collect(TaxonomyType::cases())->pluck('value')->toArray(),

    // Ganti dengan model Anda sendiri; harus meng-extend Taxonomy paket ini.
    'model' => Taxonomy::class,

    'slugs' => [
        'generate'               => true,  // buat otomatis dari name bila tidak diisi
        'regenerate_on_update'   => true,  // tulis ulang slug saat name berubah
        'consider_trashed'       => false, // hitung baris soft-deleted saat cek keunikan
        'regenerate_on_restore'  => true,  // selesaikan konflik saat restore, bukan melempar
    ],

    'cache' => [
        'ttl'   => 86400, // detik
        'scope' => null,  // lihat Multitenancy
    ],

    'migrations' => [
        'autoload' => env('TAXONOMY_AUTOLOAD_MIGRATIONS', true),
        'paths'    => [],
    ],
];
```

**`morph_type` adalah satu setelan yang harus benar sejak awal.** Ia menentukan apakah pivot menyimpan `taxonomable_id` sebagai integer, UUID, atau ULID, dan tidak bisa diubah setelah migrate tanpa menulis ulang tabel. Gunakan `numeric` untuk primary key auto-increment biasa.

**`migrations.autoload`** menentukan apakah paket mendaftarkan path migration-nya ke `php artisan migrate`. Matikan bila Anda menjalankan migration per koneksi tenant:

```php
'migrations' => ['autoload' => false],
```

```bash
php artisan migrate --path=database/migrations/tenants --database=tenant
```

## Mulai cepat

```php
use Aliziodev\LaravelTaxonomy\Facades\Taxonomy;
use Aliziodev\LaravelTaxonomy\Enums\TaxonomyType;

$electronics = Taxonomy::create([
    'name' => 'Electronics',
    'type' => TaxonomyType::Category,
    'meta' => ['icon' => 'devices'],
]);

$phones = Taxonomy::create([
    'name'      => 'Smartphones',
    'type'      => TaxonomyType::Category,
    'parent_id' => $electronics->id,
]);
```

Tambahkan trait ke model mana pun yang perlu membawa taksonomi:

```php
use Aliziodev\LaravelTaxonomy\Traits\HasTaxonomy;

class Product extends Model
{
    use HasTaxonomy;
}
```

```php
$product->attachTaxonomies([$phones->id]);
$product->taxonomies;                                  // semua term terlampir
Product::withTaxonomySlug('smartphones')->get();       // filter berdasarkan slug
```

## Mengelola taksonomi

Facade `Taxonomy` adalah proxy untuk `TaxonomyManager` dan menyediakan tepat method berikut:

```php
Taxonomy::create($attributes);                   // buat
Taxonomy::createOrUpdate($attributes);           // buat, atau perbarui bila slug+type cocok
Taxonomy::bulkCreate($rows, $chunkSize = 1000);   // insert banyak sekaligus, tanpa model event
Taxonomy::find($id);
Taxonomy::findMany($ids, $perPage = null, $page = 1);
Taxonomy::findBySlug('smartphones', TaxonomyType::Category);
Taxonomy::findByType(TaxonomyType::Category, $perPage = null, $page = 1);
Taxonomy::findByParent($parentId, $perPage = null, $page = 1);
Taxonomy::search('phone', TaxonomyType::Category, $perPage = null, $page = 1);
Taxonomy::exists('smartphones', TaxonomyType::Category);
Taxonomy::getTypes();                            // Support\Collection<string>

Taxonomy::tree($type = null, $parentId = null);        // bersarang, satu tingkat anak
Taxonomy::flatTree($type = null, $parentId = null);    // datar, tiap node membawa `depth`
Taxonomy::getNestedTree($type = null);                 // bersarang penuh, via nested set

Taxonomy::getDescendants($taxonomyId);
Taxonomy::getAncestors($taxonomyId);
Taxonomy::moveToParent($taxonomyId, $parentId);
Taxonomy::rebuildNestedSet($type);
Taxonomy::clearCacheForType($type);
```

> Facade ini **bukan** Eloquent builder. `Taxonomy::where(...)` dan sejenisnya tidak ada padanya. Untuk membangun query, impor model-nya:
>
> ```php
> use Aliziodev\LaravelTaxonomy\Models\Taxonomy as TaxonomyModel;
>
> TaxonomyModel::type(TaxonomyType::Category)->ordered()->get();
> ```

Scope pada model: `type()`, `root()`, `ordered()`, `roots()`, `atDepth()`, `nestedSetOrder()`.

## Impor massal

`create()` memakan empat sampai tujuh query per baris — cek slug, pencarian
induk atau `max(rgt)`, UPDATE rentang yang melebarkan nested set, insert-nya
sendiri, lalu pembaruan cache. Wajar untuk beberapa baris, menyiksa untuk
seeder.

`bulkCreate()` menyelesaikan slug di memori, insert secara berkelompok, dan
menomori ulang nested set sekali di akhir:

```php
Taxonomy::bulkCreate([
    ['name' => 'Electronics', 'type' => TaxonomyType::Category],
    ['name' => 'Books',       'type' => TaxonomyType::Category, 'sort_order' => 2],
    ['name' => 'Fiction',     'type' => TaxonomyType::Category, 'parent_id' => $booksId],
]);
```

Terukur pada 10.000 baris:

| | Waktu | Query |
|---|---|---|
| `create()` dalam loop, datar | 14,8s | 40.000 |
| **`bulkCreate()`, datar** | **0,95s** | **32** |
| `create()` dalam loop, bersarang | 22,0s | 59.980 |
| **`bulkCreate()`, bersarang** | **1,0s** | **37** |

Kedua jalur menghasilkan pohon yang sama; yang berbeda hanya jumlah perjalanan
ke database.

Ia menerima iterable apa pun, jadi generator menjaga memori tetap rendah pada
impor besar:

```php
Taxonomy::bulkCreate((function () {
    foreach (LazyCollection::make($csvRows) as $row) {
        yield ['name' => $row['name'], 'type' => 'category'];
    }
})(), chunkSize: 500);
```

Setiap baris membutuhkan `name` dan `type`, ditambah opsional `slug`,
`description`, `parent_id`, `sort_order`, `meta`, `created_at`, dan
`updated_at`. Slug dibuat dan di-deduplikasi terhadap batch maupun baris yang
sudah ada di tabel; slug eksplisit yang sudah terpakai melempar
`DuplicateSlugException`, persis seperti `create()`.

> **Model event tidak dipicu.** Itu harga dari kecepatannya. Bila Anda
> bergantung pada observer, atau apa pun yang terkait `creating`/`created`,
> tetap gunakan `create()`. Semua yang dikerjakan paket ini di hook tersebut —
> pembuatan slug, nilai nested set, pembatalan cache — sudah dilakukan
> `bulkCreate()` untuk Anda.

## Melampirkan ke model

```php
$product->attachTaxonomies([$a->id, $b->id]);   // tambah, yang lama tetap
$product->syncTaxonomies([$a->id, $b->id]);     // ganti semua
$product->detachTaxonomies([$a->id]);           // lepas sebagian
$product->detachTaxonomies();                   // lepas semua
$product->toggleTaxonomies([$a->id]);           // balik
```

Semuanya menerima id, objek `Taxonomy`, array, atau collection apa pun:

```php
$product->attachTaxonomies($taxonomy);
$product->attachTaxonomies(collect([$a, $b]));
$product->attachTaxonomies(TaxonomyModel::type('category')->pluck('id'));
```

> Method ini menerima **id atau model**, bukan slug. Mengirim string slug tidak melampirkan apa pun. Selesaikan dulu: `Taxonomy::findBySlug('featured', TaxonomyType::Tag)`.

Membaca dan memeriksa:

```php
$product->taxonomies;                                    // relasi
$product->taxonomiesOfType(TaxonomyType::Category);      // Collection
$product->getFirstTaxonomyOfType(TaxonomyType::Category);
$product->getTaxonomyCountByType(TaxonomyType::Tag);

$product->hasTaxonomies([$a->id]);                       // salah satu
$product->hasAllTaxonomies([$a->id, $b->id]);            // semuanya
$product->hasTaxonomyType(TaxonomyType::Category);       // punya type ini
```

### Varian khusus type

Setiap attach/detach/sync/toggle punya pasangan `*OfType` yang hanya menyentuh term satu type, membiarkan taksonomi lain pada model itu utuh:

```php
$product->syncTaxonomiesOfType(TaxonomyType::Category, [$catA->id]);   // tag tidak tersentuh
$product->attachTaxonomiesOfType(TaxonomyType::Tag, [$tagA->id]);
$product->detachTaxonomiesOfType(TaxonomyType::Tag);                   // semua tag
$product->toggleTaxonomiesOfType(TaxonomyType::Tag, [$tagA->id]);

$product->hasTaxonomiesOfType(TaxonomyType::Tag, [$tagA->id]);
$product->hasAllTaxonomiesOfType(TaxonomyType::Tag, [$tagA->id]);
```

Id yang bukan dari type tersebut dilewati — penyaringan itulah inti method-method ini.

## Query scope

```php
Product::withTaxonomy($ids)->get();               // punya salah satu
Product::withAnyTaxonomies($ids)->get();          // punya salah satu
Product::withAllTaxonomies($ids)->get();          // punya semuanya
Product::withoutTaxonomies($ids)->get();          // tidak punya satu pun
Product::withTaxonomyType(TaxonomyType::Category)->get();
Product::withTaxonomySlug('smartphones', TaxonomyType::Category)->get();

Product::withAnyTaxonomiesOfType(TaxonomyType::Tag, $ids)->get();
Product::withAllTaxonomiesOfType(TaxonomyType::Tag, $ids)->get();
Product::withoutTaxonomiesOfType(TaxonomyType::Tag, $ids)->get();

Product::withTaxonomyHierarchy($categoryId)->get();          // term + seluruh keturunannya
Product::withTaxonomyAtDepth(1, TaxonomyType::Category)->get();
Product::orderByTaxonomyType(TaxonomyType::Category, 'asc', 'name')->get();
```

Scope bisa dirantai, dan rantai berarti AND:

```php
Product::withTaxonomySlug('smartphones', TaxonomyType::Category)
    ->withAnyTaxonomiesOfType(TaxonomyType::Tag, $featuredIds)
    ->get();
```

`filterByTaxonomies()` menerima array berkunci, praktis untuk filter dari request:

```php
Product::filterByTaxonomies([
    'category' => 'smartphones',        // type => slug
    'color'    => ['red', 'blue'],      // OR di dalam satu type
    'exclude'  => $discontinuedIds,
])->get();
```

## Hierarki

Hierarki disimpan dua kali: sebagai `parent_id`, dan sebagai kolom nested set `lft`/`rgt`/`depth` yang otomatis dijaga saat create, update, delete, dan restore.

```php
$node->parent;              // relasi
$node->children;            // relasi, terurut sort_order
$node->ancestors();         // menelusuri parent_id — tetap benar meski lft/rgt melenceng
$node->descendants();       // depth-first, satu query per tingkat
$node->getAncestors();      // nested set, satu query
$node->getDescendants();    // nested set, satu query
$node->getSiblings();
$node->getChildren();

$node->isAncestorOf($other);
$node->isDescendantOf($other);
$node->getLevel();          // depth, 0 untuk root

$node->path;                // "Electronics > Smartphones"
$node->full_slug;           // "electronics/smartphones"

$node->moveToParent($newParentId);   // melempar bila memindahkan jadi melingkar
```

`getAncestors()`/`getDescendants()` membaca `lft`/`rgt` dan paling cepat. `ancestors()`/`descendants()` mengikuti `parent_id` dan tetap benar meski nested set melenceng — pakai keduanya bila Anda menulis ke tabel di luar model.

> **Muat ulang sebelum memakai varian nested set pada objek yang sudah Anda pegang.** Menambah anak akan melebarkan `rgt` induk di database, sedangkan objek yang dimuat sebelum itu masih menyimpan batas lama — `getDescendants()` lalu mengembalikan collection kosong tanpa error:
>
> ```php
> $parent = Taxonomy::create(['name' => 'Electronics', 'type' => 'category']);
> Taxonomy::create(['name' => 'Phones', 'type' => 'category', 'parent_id' => $parent->id]);
>
> $parent->getDescendants();            // kosong — $parent->rgt sudah usang
> $parent->refresh()->getDescendants(); // 1
> ```
>
> `descendants()` berbasis id, jadi kebal terhadap masalah ini.

Pohon:

```php
Taxonomy::tree(TaxonomyType::Category);          // root dengan satu tingkat anak
Taxonomy::flatTree(TaxonomyType::Category);      // daftar datar, `depth` terisi
Taxonomy::getNestedTree(TaxonomyType::Category); // kedalaman penuh, `children_nested` + `tree_depth`
```

## Type

`TaxonomyType` menyediakan `Category`, `Tag`, `Color`, `Size`, `Unit`, `Type`, `Brand`, `Model`, `Variant`. Di mana pun sebuah type diterima, Anda boleh mengirim enum atau string biasa — jadi type kustom tidak perlu didaftarkan:

```php
Taxonomy::create(['name' => 'Winter', 'type' => 'season']);
Product::withTaxonomyType('season')->get();
```

Daftarkan di config agar tooling dan perintah rebuild mengenalinya:

```php
'types' => ['category', 'tag', 'season', 'department'],
```

Helper enum:

```php
TaxonomyType::values();               // ['category', 'tag', ...]
TaxonomyType::options();              // [['value' => ..., 'label' => ...], ...]
TaxonomyType::Category->label();      // 'Category'
TaxonomyType::Category->getLabel();   // sama, dinamai mengikuti kontrak HasLabel milik Filament
```

## Metadata

`meta` adalah kolom JSON yang di-cast ke array:

```php
Taxonomy::create([
    'name' => 'Electronics',
    'type' => TaxonomyType::Category,
    'meta' => ['icon' => 'devices', 'color' => '#3498db', 'featured' => true],
]);

$taxonomy->meta['icon'];

TaxonomyModel::where('meta->featured', true)->get();
TaxonomyModel::whereJsonContains('meta->tags', 'sale')->get();
```

Paket ini tidak punya lapisan terjemahan; `meta` tempat yang wajar untuk itu:

```php
'meta' => ['translations' => ['en' => ['name' => 'Electronics']]],
```

## Cache

`tree()`, `flatTree()`, dan `getNestedTree()` di-cache selama `cache.ttl` (24 jam secara bawaan) dan otomatis dibatalkan setiap kali taksonomi dibuat, diperbarui, dihapus, dipulihkan, dipindahkan, atau di-rebuild.

```php
Taxonomy::clearCacheForType(TaxonomyType::Category);   // manual, jarang diperlukan
```

Pembatalan bekerja dengan menaikkan version key, jadi entri kedaluwarsa secara logis alih-alih dienumerasi lalu dihapus — sehingga tetap benar pada cache store yang tidak mendukung tag.

> Jangan bungkus pemanggilan ini dengan `Cache::remember()` lagi. Lapisan kedua yang tanpa versi tidak akan melihat pembatalan dari paket dan akan menyajikan pohon usang.

## Multitenancy

Ada dua hal yang perlu diperhatikan.

**1. Isolasi cache.** Kunci cache bersifat global kecuali Anda menentukan lain, sehingga tanpa scope satu tenant bisa menerima pohon milik tenant lain. Daftarkan resolver:

```php
use Aliziodev\LaravelTaxonomy\TaxonomyManager;

// AppServiceProvider::boot()
TaxonomyManager::resolveCacheScopeUsing(fn () => tenant()?->getKey());
```

Atau arahkan `taxonomy.cache.scope` ke sebuah class invokable — nama class, bukan closure, agar config tetap aman dari `php artisan config:cache`:

```php
class TenantCacheScope
{
    public function __invoke(): ?string
    {
        return tenant()?->getKey();
    }
}
```

Bila tidak ada scope terdaftar, kunci cache sama persis dengan rilis sebelumnya, jadi aplikasi single-tenant tidak perlu melakukan apa pun.

**2. Scope datanya sendiri.** Paket ini tidak menyediakan kolom `tenant_id`. Tambahkan sendiri, dan ganti unique index — `unique(['slug', 'type', 'deleted_at'])` bawaan akan mencegah dua tenant memakai slug yang sama dalam satu type:

```php
Schema::table('taxonomies', function (Blueprint $table) {
    $table->dropUnique(['slug', 'type', 'deleted_at']);
    $table->foreignId('tenant_id')->nullable()->index();
    $table->unique(['tenant_id', 'slug', 'type', 'deleted_at']);
});
```

Lalu arahkan paket ke model yang membawa scope Anda:

```php
namespace App\Models;

use Aliziodev\LaravelTaxonomy\Models\Taxonomy as BaseTaxonomy;

class Taxonomy extends BaseTaxonomy
{
    protected static function boot(): void
    {
        parent::boot();

        static::addGlobalScope('tenant', function ($query) {
            if ($tenantId = tenant()?->getKey()) {
                $query->where('tenant_id', $tenantId);
            }
        });
    }
}
```

```php
'model' => \App\Models\Taxonomy::class,
```

Model itu **harus** meng-extend `Taxonomy` milik paket — di sanalah pembuatan slug dan pemeliharaan nested set berada.

> Method `*OfType` memvalidasi id yang Anda kirim **tanpa** menerapkan global scope, sehingga taksonomi bersama antar tenant tidak dibuang diam-diam. Konsekuensinya: id milik tenant lain akan terlampir bila aplikasi Anda meneruskannya. Validasi input pengguna, misalnya dengan `Rule::exists()` yang di-scope per tenant.

## Slug dan exception

Slug dibuat dari name dan unik **di dalam satu type**, sehingga kategori `featured` dan tag `featured` bisa hidup berdampingan.

```php
Taxonomy::create(['name' => 'Electronics', 'type' => TaxonomyType::Category]);           // 'electronics'
Taxonomy::create(['name' => 'Electronics', 'type' => TaxonomyType::Category]);           // 'electronics-1'
Taxonomy::create(['name' => 'Electronics', 'slug' => 'custom', 'type' => 'category']);   // 'custom'
```

Dua exception, keduanya turunan `TaxonomyException`:

```php
use Aliziodev\LaravelTaxonomy\Exceptions\DuplicateSlugException;
use Aliziodev\LaravelTaxonomy\Exceptions\MissingSlugException;

try {
    Taxonomy::create(['name' => 'Electronics', 'slug' => 'taken', 'type' => 'category']);
} catch (DuplicateSlugException $e) {
    $e->getSlug();   // 'taken'
    $e->getType();   // 'category'
}
```

`MissingSlugException` dilempar bila `slugs.generate` bernilai `false` dan slug tidak diisi.

Soft delete berinteraksi dengan keunikan lewat dua setelan: `consider_trashed` menentukan apakah baris terhapus ikut memblokir slug, dan `regenerate_on_restore` menentukan apakah memulihkan baris dengan slug yang kini terpakai akan menamainya ulang atau melempar exception.

## Perintah artisan

```bash
php artisan taxonomy:install [--force]
php artisan taxonomy:rebuild-nested-set [type] [--force]
```

`taxonomy:rebuild-nested-set` menghitung ulang `lft`, `rgt`, dan `depth`. Anda hanya membutuhkannya bila ada baris yang ditulis di luar model — SQL langsung, seeder mentah, atau impor massal. Tanpa argumen ia me-rebuild semua type, memakai satu transaksi per type, dan membersihkan cache setelahnya. `--force` wajib bila dijalankan non-interaktif.

## Contoh

- [Katalog produk e-commerce](docs/en/ecommerce-product-catalog.md)
- [Content management system](docs/en/content-management-system.md)

## Pemecahan masalah

**Attach tidak melakukan apa-apa, tanpa error.** Kemungkinan besar Anda mengirim slug. Method ini menerima id atau model; selesaikan slug dengan `Taxonomy::findBySlug()` lebih dulu.

**`Call to undefined method ... where()` pada facade.** Facade adalah proxy untuk `TaxonomyManager`, bukan Eloquent. Impor `Models\Taxonomy` untuk membangun query.

**Tipe kolom pivot salah.** `morph_type` harus cocok dengan kunci model Anda dan ditetapkan saat migration. Periksa sebelum `migrate` pertama.

**Pohon terlihat usang.** Seharusnya batal otomatis; bila Anda menulis baris dengan SQL mentah, panggil `Taxonomy::clearCacheForType()` dan, bila `lft`/`rgt` terlibat, `php artisan taxonomy:rebuild-nested-set`.

**Ancestor atau descendant salah.** Nested set melenceng, biasanya karena penulisan SQL langsung. Jalankan perintah rebuild, atau pakai `ancestors()`/`descendants()` yang mengikuti `parent_id`.

## Upgrade

Lihat [UPGRADE.md](UPGRADE.md). Yang penting di 2.11: isolasi cache untuk aplikasi multitenant, dan nama relasi kustom yang kini deprecated dan akan dihapus di 3.0.

## Kontribusi

Lihat [CONTRIBUTING.md](CONTRIBUTING.md). Commit mengikuti Conventional Commits; rilis dan changelog dihasilkan darinya.

```bash
composer test      # Pest
composer analyse   # PHPStan
composer format    # Pint
```

## Keamanan

Laporkan celah keamanan ke <aliziodev@gmail.com>, bukan ke issue tracker publik.

## Lisensi

MIT. Lihat [LICENSE](LICENSE).
