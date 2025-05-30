# Katalog Produk E-commerce

**Skenario**: Membangun platform e-commerce multi-kategori dengan produk, merek, dan atribut.

## 1. Menyiapkan struktur taksonomi

```php
use Aliziodev\LaravelTaxonomy\Facades\Taxonomy;
use Aliziodev\LaravelTaxonomy\Enums\TaxonomyType;

// Buat kategori utama
$electronics = Taxonomy::create([
    'name' => 'Electronics',
    'type' => TaxonomyType::Category->value,
    'metadata' => [
        'icon' => 'laptop',
        'banner_image' => 'electronics-banner.jpg',
        'seo_title' => 'Electronics - Latest Gadgets & Devices',
    ],
]);

$clothing = Taxonomy::create([
    'name' => 'Clothing',
    'type' => TaxonomyType::Category->value,
    'metadata' => [
        'icon' => 'shirt',
        'seasonal' => true,
    ],
]);

// Buat subkategori
$smartphones = Taxonomy::create([
    'name' => 'Smartphones',
    'type' => TaxonomyType::Category->value,
    'parent_id' => $electronics->id,
    'metadata' => [
        'filters' => ['brand', 'price_range', 'storage', 'color'],
        'popular' => true,
    ],
]);

$laptops = Taxonomy::create([
    'name' => 'Laptops',
    'type' => TaxonomyType::Category->value,
    'parent_id' => $electronics->id,
]);

// Buat merek
$apple = Taxonomy::create([
    'name' => 'Apple',
    'type' => 'brand',
    'metadata' => [
        'logo' => 'apple-logo.png',
        'premium' => true,
        'warranty_years' => 1,
    ],
]);

$samsung = Taxonomy::create([
    'name' => 'Samsung',
    'type' => 'brand',
    'metadata' => [
        'logo' => 'samsung-logo.png',
        'country' => 'South Korea',
    ],
]);

// Buat atribut produk
$colors = [
    ['name' => 'Space Gray', 'hex' => '#8E8E93'],
    ['name' => 'Silver', 'hex' => '#C7C7CC'],
    ['name' => 'Gold', 'hex' => '#FFD700'],
];

foreach ($colors as $color) {
    Taxonomy::create([
        'name' => $color['name'],
        'type' => TaxonomyType::Color->value,
        'metadata' => ['hex_code' => $color['hex']],
    ]);
}
```

## 2. Mengasosiasikan produk dengan taksonomi

```php
$product = Product::create([
    'name' => 'iPhone 15 Pro',
    'description' => 'Latest iPhone with advanced features',
    'price' => 999.99,
    'sku' => 'IPH15PRO-128-SG',
]);

// Lampirkan beberapa tipe taksonomi
$product->attachTaxonomies([
    $electronics->id,
    $smartphones->id,
    $apple->id,
    Taxonomy::findBySlug('space-gray')->id,
]);
```

## 3. Membangun navigasi kategori dinamis

```php
class CategoryController extends Controller
{
    public function index()
    {
        $categories = Taxonomy::tree(TaxonomyType::Category)
            ->map(function ($category) {
                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'icon' => $category->metadata['icon'] ?? null,
                    'product_count' => $category->models()->count(),
                    'children' => $category->children->map(function ($child) {
                        return [
                            'id' => $child->id,
                            'name' => $child->name,
                            'slug' => $child->slug,
                            'product_count' => $child->models()->count(),
                        ];
                    }),
                ];
            });

        return view('categories.index', compact('categories'));
    }

    public function show($slug)
    {
        $category = Taxonomy::findBySlug($slug, TaxonomyType::Category);
        
        if (!$category) {
            abort(404);
        }

        // Dapatkan produk dalam kategori ini dan subkategorinya
        $categoryIds = collect([$category->id])
            ->merge($category->getDescendants()->pluck('id'));

        $products = Product::withAnyTaxonomies($categoryIds)
            ->with(['taxonomies' => function ($query) {
                $query->whereIn('type', ['brand', TaxonomyType::Color->value]);
            }])
            ->paginate(20);

        // Dapatkan filter yang tersedia
        $brands = Taxonomy::whereIn('id', function ($query) use ($categoryIds) {
            $query->select('taxonomy_id')
                ->from('taxonomables')
                ->whereIn('taxonomable_id', function ($subQuery) use ($categoryIds) {
                    $subQuery->select('taxonomable_id')
                        ->from('taxonomables')
                        ->whereIn('taxonomy_id', $categoryIds);
                })
                ->where('taxonomable_type', Product::class);
        })->where('type', 'brand')->get();

        return view('categories.show', compact('category', 'products', 'brands'));
    }
}
```

## 4. Filter dan pencarian lanjutan

```php
class ProductSearchController extends Controller
{
    public function search(Request $request)
    {
        $query = Product::query();

        // Filter berdasarkan kategori
        if ($request->filled('categories')) {
            $query->withAnyTaxonomies($request->categories);
        }

        // Filter berdasarkan merek
        if ($request->filled('brands')) {
            $query->withAnyTaxonomies($request->brands);
        }

        // Filter berdasarkan warna
        if ($request->filled('colors')) {
            $query->withAnyTaxonomies($request->colors);
        }

        // Rentang harga
        if ($request->filled('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }

        if ($request->filled('max_price')) {
            $query->where('price', '<=', $request->max_price);
        }

        // Pencarian teks
        if ($request->filled('q')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->q . '%')
                  ->orWhere('description', 'like', '%' . $request->q . '%');
            });
        }

        $products = $query->with('taxonomies')->paginate(20);

        return view('products.search', compact('products'));
    }
}
```

## 5. Sistem rekomendasi produk

```php
class ProductRecommendationService
{
    public function getRelatedProducts(Product $product, int $limit = 6): Collection
    {
        $productTaxonomies = $product->taxonomies->pluck('id');
        
        return Product::withAnyTaxonomies($productTaxonomies)
            ->where('id', '!=', $product->id)
            ->withCount(['taxonomies' => function ($query) use ($productTaxonomies) {
                $query->whereIn('taxonomy_id', $productTaxonomies);
            }])
            ->orderByDesc('taxonomies_count')
            ->limit($limit)
            ->get();
    }

    public function getCrossSellProducts(Product $product): Collection
    {
        // Dapatkan produk yang sering dibeli bersamaan
        $categoryIds = $product->taxonomies
            ->where('type', TaxonomyType::Category->value)
            ->pluck('id');

        return Product::withAnyTaxonomies($categoryIds)
            ->where('id', '!=', $product->id)
            ->where('price', '<', $product->price * 0.5) // Aksesori yang lebih murah
            ->limit(4)
            ->get();
    }
}
```

## 6. URL ramah SEO dan breadcrumb

```php
class BreadcrumbService
{
    public function generateProductBreadcrumbs(Product $product): array
    {
        $breadcrumbs = [['name' => 'Home', 'url' => route('home')]];
        
        $category = $product->taxonomies
            ->where('type', TaxonomyType::Category->value)
            ->first();

        if ($category) {
            $ancestors = $category->getAncestors();
            
            foreach ($ancestors as $ancestor) {
                $breadcrumbs[] = [
                    'name' => $ancestor->name,
                    'url' => route('categories.show', $ancestor->slug),
                ];
            }
            
            $breadcrumbs[] = [
                'name' => $category->name,
                'url' => route('categories.show', $category->slug),
            ];
        }
        
        $breadcrumbs[] = ['name' => $product->name, 'url' => null];
        
        return $breadcrumbs;
    }
}
```

Contoh e-commerce ini mendemonstrasikan bagaimana Laravel Taxonomy dapat menangani kategorisasi produk yang kompleks, filtering, dan kebutuhan navigasi dalam aplikasi dunia nyata.