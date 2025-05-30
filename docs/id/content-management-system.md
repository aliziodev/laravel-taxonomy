# Sistem Manajemen Konten

**Skenario**: Membangun CMS dengan artikel, kategori, tag, dan organisasi konten.

## 1. Menyiapkan taksonomi konten

```php
// Buat kategori konten utama
$newsCategory = Taxonomy::create([
    'name' => 'News',
    'type' => TaxonomyType::Category->value,
    'metadata' => [
        'template' => 'news-layout',
        'show_date' => true,
        'allow_comments' => true,
    ],
]);

$techNews = Taxonomy::create([
    'name' => 'Technology',
    'type' => TaxonomyType::Category->value,
    'parent_id' => $newsCategory->id,
    'metadata' => [
        'featured_color' => '#007bff',
        'rss_enabled' => true,
    ],
]);

// Buat tag konten
$tags = ['Laravel', 'PHP', 'JavaScript', 'AI', 'Machine Learning'];
foreach ($tags as $tagName) {
    Taxonomy::create([
        'name' => $tagName,
        'type' => TaxonomyType::Tag->value,
        'metadata' => [
            'trending' => in_array($tagName, ['AI', 'Machine Learning']),
            'skill_level' => $tagName === 'Laravel' ? 'intermediate' : 'beginner',
        ],
    ]);
}
```

## 2. Model artikel dengan taksonomi

```php
class Article extends Model
{
    use HasTaxonomy;

    protected $fillable = ['title', 'content', 'excerpt', 'published_at'];

    public function scopePublished($query)
    {
        return $query->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function getRouteKeyName()
    {
        return 'slug';
    }
}
```

## 3. Membuat dan mengkategorikan artikel

```php
$article = Article::create([
    'title' => 'Getting Started with Laravel 11',
    'content' => 'Laravel 11 introduces many exciting features...',
    'excerpt' => 'Learn about the new features in Laravel 11',
    'published_at' => now(),
]);

$article->attachTaxonomies([
    $techNews->id,
    Taxonomy::findBySlug('laravel')->id,
    Taxonomy::findBySlug('php')->id,
]);
```

## 4. Membangun filtering konten dan navigasi

```php
class ArticleController extends Controller
{
    public function index(Request $request)
    {
        $query = Article::published()->with('taxonomies');

        // Filter berdasarkan kategori
        if ($request->category) {
            $category = Taxonomy::findBySlug($request->category, TaxonomyType::Category);
            if ($category) {
                $categoryIds = collect([$category->id])
                    ->merge($category->getDescendants()->pluck('id'));
                $query->withAnyTaxonomies($categoryIds);
            }
        }

        // Filter berdasarkan tag
        if ($request->tags) {
            $tagSlugs = explode(',', $request->tags);
            $tags = Taxonomy::whereIn('slug', $tagSlugs)
                ->where('type', TaxonomyType::Tag->value)
                ->get();
            $query->withAllTaxonomies($tags);
        }

        $articles = $query->orderBy('published_at', 'desc')->paginate(10);

        // Dapatkan tag populer
        $popularTags = Taxonomy::where('type', TaxonomyType::Tag->value)
            ->withCount('models')
            ->orderBy('models_count', 'desc')
            ->limit(10)
            ->get();

        return view('articles.index', compact('articles', 'popularTags'));
    }

    public function show(Article $article)
    {
        $categories = $article->taxonomiesOfType(TaxonomyType::Category);
        $tags = $article->taxonomiesOfType(TaxonomyType::Tag);

        // Dapatkan artikel terkait
        $relatedArticles = Article::published()
            ->withAnyTaxonomies($tags->pluck('id'))
            ->where('id', '!=', $article->id)
            ->limit(5)
            ->get();

        return view('articles.show', compact('article', 'categories', 'tags', 'relatedArticles'));
    }
}
```

## 5. Organisasi konten lanjutan

```php
class ContentOrganizationService
{
    public function getContentByCategory(string $categorySlug): Collection
    {
        $category = Taxonomy::findBySlug($categorySlug, TaxonomyType::Category);
        
        if (!$category) {
            return collect();
        }

        // Dapatkan semua kategori turunan
        $categoryIds = collect([$category->id])
            ->merge($category->getDescendants()->pluck('id'));

        return Article::published()
            ->withAnyTaxonomies($categoryIds)
            ->orderBy('published_at', 'desc')
            ->get();
    }

    public function getTrendingContent(int $days = 7): Collection
    {
        $trendingTags = Taxonomy::where('type', TaxonomyType::Tag->value)
            ->whereJsonContains('metadata->trending', true)
            ->get();

        return Article::published()
            ->withAnyTaxonomies($trendingTags->pluck('id'))
            ->where('published_at', '>=', now()->subDays($days))
            ->orderBy('published_at', 'desc')
            ->get();
    }

    public function getContentArchive(): array
    {
        return Article::published()
            ->selectRaw('YEAR(published_at) as year, MONTH(published_at) as month, COUNT(*) as count')
            ->groupBy('year', 'month')
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->get()
            ->groupBy('year')
            ->toArray();
    }
}
```

## 6. Manajemen SEO dan metadata

```php
class SEOService
{
    public function generateMetaTags(Article $article): array
    {
        $categories = $article->taxonomiesOfType(TaxonomyType::Category);
        $tags = $article->taxonomiesOfType(TaxonomyType::Tag);

        return [
            'title' => $article->title,
            'description' => $article->excerpt,
            'keywords' => $tags->pluck('name')->implode(', '),
            'category' => $categories->first()?->name,
            'article:section' => $categories->first()?->name,
            'article:tag' => $tags->pluck('name')->toArray(),
            'article:published_time' => $article->published_at?->toISOString(),
        ];
    }

    public function generateBreadcrumbs(Article $article): array
    {
        $breadcrumbs = [['name' => 'Home', 'url' => route('home')]];
        
        $category = $article->taxonomiesOfType(TaxonomyType::Category)->first();
        
        if ($category) {
            $ancestors = $category->getAncestors();
            
            foreach ($ancestors as $ancestor) {
                $breadcrumbs[] = [
                    'name' => $ancestor->name,
                    'url' => route('articles.category', $ancestor->slug),
                ];
            }
            
            $breadcrumbs[] = [
                'name' => $category->name,
                'url' => route('articles.category', $category->slug),
            ];
        }
        
        $breadcrumbs[] = ['name' => $article->title, 'url' => null];
        
        return $breadcrumbs;
    }
}
```

## 7. Analitik konten dan pelaporan

```php
class ContentAnalyticsService
{
    public function getCategoryPerformance(): Collection
    {
        return Taxonomy::where('type', TaxonomyType::Category->value)
            ->withCount('models')
            ->with(['models' => function ($query) {
                $query->where('published_at', '>=', now()->subMonth());
            }])
            ->get()
            ->map(function ($category) {
                return [
                    'name' => $category->name,
                    'total_articles' => $category->models_count,
                    'recent_articles' => $category->models->count(),
                    'engagement_rate' => $this->calculateEngagementRate($category),
                ];
            });
    }

    public function getPopularTags(int $limit = 20): Collection
    {
        return Taxonomy::where('type', TaxonomyType::Tag->value)
            ->withCount(['models' => function ($query) {
                $query->where('published_at', '>=', now()->subMonth());
            }])
            ->orderBy('models_count', 'desc')
            ->limit($limit)
            ->get();
    }

    private function calculateEngagementRate(Taxonomy $category): float
    {
        // Implementasi tergantung pada pelacakan analitik Anda
        // Ini adalah perhitungan placeholder
        return rand(10, 95) / 100;
    }
}
```

Contoh CMS ini menunjukkan bagaimana Laravel Taxonomy dapat menggerakkan sistem manajemen konten yang canggih dengan kategori hierarkis, tagging yang fleksibel, dan fitur organisasi konten lanjutan.