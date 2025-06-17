# Analitik dan Pelaporan

**Skenario**: Membangun laporan dan analitik komprehensif menggunakan data taksonomi.

## 1. Analitik penggunaan taksonomi

```php
class TaxonomyAnalyticsService
{
    public function getUsageStatistics(string $type, ?int $tenantId = null): array
    {
        $query = Taxonomy::where('type', $type);
        
        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        return $query->withCount('models')
            ->orderBy('models_count', 'desc')
            ->get()
            ->map(function ($taxonomy) {
                return [
                    'id' => $taxonomy->id,
                    'name' => $taxonomy->name,
                    'usage_count' => $taxonomy->models_count,
                    'last_used' => $taxonomy->models()->latest('created_at')->first()?->created_at,
                    'meta' => $taxonomy->meta,
                ];
            })
            ->toArray();
    }

    public function getHierarchyDepthAnalysis(string $type): array
    {
        $taxonomies = Taxonomy::where('type', $type)
            ->orderBy('lft')
            ->get();

        $depthStats = $taxonomies->groupBy('depth')
            ->map(function ($group, $depth) {
                return [
                    'depth' => $depth,
                    'count' => $group->count(),
                    'taxonomies' => $group->pluck('name')->toArray(),
                ];
            })
            ->values();

        return [
            'max_depth' => $taxonomies->max('depth'),
            'avg_depth' => $taxonomies->avg('depth'),
            'depth_distribution' => $depthStats,
        ];
    }

    public function getPopularityTrends(string $type, int $days = 30): array
    {
        $startDate = now()->subDays($days);
        
        return DB::table('taxonomables')
            ->join('taxonomies', 'taxonomables.taxonomy_id', '=', 'taxonomies.id')
            ->where('taxonomies.type', $type)
            ->where('taxonomables.created_at', '>=', $startDate)
            ->select(
                'taxonomies.name',
                DB::raw('DATE(taxonomables.created_at) as date'),
                DB::raw('COUNT(*) as usage_count')
            )
            ->groupBy('taxonomies.name', 'date')
            ->orderBy('date')
            ->get()
            ->groupBy('name')
            ->map(function ($group) {
                return $group->pluck('usage_count', 'date')->toArray();
            })
            ->toArray();
    }
}
```

## 2. Dashboard pelaporan lanjutan

```php
class ReportingController extends Controller
{
    protected $analyticsService;

    public function __construct(TaxonomyAnalyticsService $analyticsService)
    {
        $this->analyticsService = $analyticsService;
    }

    public function dashboard()
    {
        $reports = [
            'category_usage' => $this->analyticsService->getUsageStatistics('category'),
            'tag_trends' => $this->analyticsService->getPopularityTrends('tag', 30),
            'hierarchy_analysis' => $this->analyticsService->getHierarchyDepthAnalysis('category'),
            'performance_metrics' => $this->getPerformanceMetrics(),
        ];

        return view('reports.dashboard', compact('reports'));
    }

    public function exportReport(Request $request)
    {
        $type = $request->input('type', 'category');
        $format = $request->input('format', 'csv');
        $dateRange = $request->input('date_range', 30);

        $data = $this->analyticsService->getUsageStatistics($type);

        if ($format === 'csv') {
            return $this->exportToCsv($data, "taxonomy-{$type}-report.csv");
        }

        if ($format === 'json') {
            return response()->json($data);
        }

        return $this->exportToPdf($data, "taxonomy-{$type}-report.pdf");
    }

    private function getPerformanceMetrics(): array
    {
        return [
            'total_taxonomies' => Taxonomy::count(),
            'total_associations' => DB::table('taxonomables')->count(),
            'avg_associations_per_taxonomy' => DB::table('taxonomables')
                ->selectRaw('AVG(association_count) as avg')
                ->fromSub(function ($query) {
                    $query->from('taxonomables')
                        ->selectRaw('taxonomy_id, COUNT(*) as association_count')
                        ->groupBy('taxonomy_id');
                }, 'subquery')
                ->value('avg'),
            'most_used_type' => Taxonomy::selectRaw('type, COUNT(*) as count')
                ->groupBy('type')
                ->orderBy('count', 'desc')
                ->first()?->type,
        ];
    }

    private function exportToCsv(array $data, string $filename)
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename={$filename}",
        ];

        $callback = function () use ($data) {
            $file = fopen('php://output', 'w');
            
            // Tambahkan header CSV
            fputcsv($file, ['ID', 'Name', 'Usage Count', 'Last Used', 'Meta']);
            
            foreach ($data as $row) {
                fputcsv($file, [
                    $row['id'],
                    $row['name'],
                    $row['usage_count'],
                    $row['last_used'],
                    json_encode($row['meta']),
                ]);
            }
            
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function exportToPdf(array $data, string $filename)
    {
        $pdf = PDF::loadView('reports.pdf-template', compact('data'));
        return $pdf->download($filename);
    }
}
```

## 3. Analitik real-time dengan caching

```php
class CachedAnalyticsService
{
    protected $cache;
    protected $analyticsService;

    public function __construct(Cache $cache, TaxonomyAnalyticsService $analyticsService)
    {
        $this->cache = $cache;
        $this->analyticsService = $analyticsService;
    }

    public function getCachedUsageStats(string $type, int $ttl = 3600): array
    {
        $cacheKey = "taxonomy_usage_stats_{$type}";
        
        return $this->cache->remember($cacheKey, $ttl, function () use ($type) {
            return $this->analyticsService->getUsageStatistics($type);
        });
    }

    public function getCachedTrends(string $type, int $days = 30, int $ttl = 1800): array
    {
        $cacheKey = "taxonomy_trends_{$type}_{$days}";
        
        return $this->cache->remember($cacheKey, $ttl, function () use ($type, $days) {
            return $this->analyticsService->getPopularityTrends($type, $days);
        });
    }

    public function invalidateCache(string $type = null): void
    {
        if ($type) {
            $this->cache->forget("taxonomy_usage_stats_{$type}");
            $this->cache->forget("taxonomy_trends_{$type}_30");
        } else {
            // Hapus semua cache analitik taksonomi
            $this->cache->flush();
        }
    }

    public function getRealtimeMetrics(): array
    {
        return [
            'active_users' => $this->getActiveUsersCount(),
            'recent_associations' => $this->getRecentAssociations(),
            'trending_taxonomies' => $this->getTrendingTaxonomies(),
            'system_health' => $this->getSystemHealth(),
        ];
    }

    private function getActiveUsersCount(): int
    {
        return DB::table('taxonomables')
            ->where('created_at', '>=', now()->subHour())
            ->distinct('created_by')
            ->count('created_by');
    }

    private function getRecentAssociations(): int
    {
        return DB::table('taxonomables')
            ->where('created_at', '>=', now()->subMinutes(5))
            ->count();
    }

    private function getTrendingTaxonomies(): array
    {
        return DB::table('taxonomables')
            ->join('taxonomies', 'taxonomables.taxonomy_id', '=', 'taxonomies.id')
            ->where('taxonomables.created_at', '>=', now()->subHour())
            ->select('taxonomies.name', DB::raw('COUNT(*) as count'))
            ->groupBy('taxonomies.id', 'taxonomies.name')
            ->orderBy('count', 'desc')
            ->limit(5)
            ->get()
            ->toArray();
    }

    private function getSystemHealth(): array
    {
        $totalTaxonomies = Taxonomy::count();
        $totalAssociations = DB::table('taxonomables')->count();
        $avgResponseTime = $this->calculateAverageResponseTime();
        
        return [
            'status' => $avgResponseTime < 100 ? 'healthy' : 'warning',
            'total_taxonomies' => $totalTaxonomies,
            'total_associations' => $totalAssociations,
            'avg_response_time' => $avgResponseTime,
            'cache_hit_rate' => $this->calculateCacheHitRate(),
        ];
    }

    private function calculateAverageResponseTime(): float
    {
        // Implementasi akan mengukur waktu respons aktual
        return rand(50, 150); // Placeholder
    }

    private function calculateCacheHitRate(): float
    {
        // Implementasi akan menghitung tingkat hit cache aktual
        return rand(80, 95); // Placeholder
    }
}
```

## 4. Pembuat laporan kustom

```php
class ReportBuilder
{
    protected $query;
    protected $filters = [];
    protected $groupBy = [];
    protected $orderBy = [];

    public function __construct()
    {
        $this->query = DB::table('taxonomies')
            ->leftJoin('taxonomables', 'taxonomies.id', '=', 'taxonomables.taxonomy_id');
    }

    public function filterByType(string $type): self
    {
        $this->filters[] = ['taxonomies.type', '=', $type];
        return $this;
    }

    public function filterByDateRange(Carbon $startDate, Carbon $endDate): self
    {
        $this->filters[] = ['taxonomables.created_at', '>=', $startDate];
        $this->filters[] = ['taxonomables.created_at', '<=', $endDate];
        return $this;
    }

    public function filterByUsageCount(int $minCount, ?int $maxCount = null): self
    {
        $this->query->havingRaw('COUNT(taxonomables.id) >= ?', [$minCount]);
        
        if ($maxCount) {
            $this->query->havingRaw('COUNT(taxonomables.id) <= ?', [$maxCount]);
        }
        
        return $this;
    }

    public function groupByType(): self
    {
        $this->groupBy[] = 'taxonomies.type';
        return $this;
    }

    public function groupByDate(string $format = 'Y-m-d'): self
    {
        $this->groupBy[] = DB::raw("DATE_FORMAT(taxonomables.created_at, '{$format}') as date_group");
        return $this;
    }

    public function orderByUsage(string $direction = 'desc'): self
    {
        $this->orderBy[] = ['usage_count', $direction];
        return $this;
    }

    public function orderByName(string $direction = 'asc'): self
    {
        $this->orderBy[] = ['taxonomies.name', $direction];
        return $this;
    }

    public function build(): Collection
    {
        // Terapkan filter
        foreach ($this->filters as $filter) {
            $this->query->where(...$filter);
        }

        // Terapkan pengelompokan
        if (!empty($this->groupBy)) {
            $this->query->groupBy($this->groupBy);
        } else {
            $this->query->groupBy('taxonomies.id');
        }

        // Pilih field
        $this->query->select([
            'taxonomies.id',
            'taxonomies.name',
            'taxonomies.type',
            'taxonomies.meta',
            DB::raw('COUNT(taxonomables.id) as usage_count'),
            DB::raw('MAX(taxonomables.created_at) as last_used'),
        ]);

        // Terapkan pengurutan
        foreach ($this->orderBy as $order) {
            $this->query->orderBy(...$order);
        }

        return $this->query->get();
    }

    public function toChart(string $chartType = 'bar'): array
    {
        $data = $this->build();
        
        return [
            'type' => $chartType,
            'labels' => $data->pluck('name')->toArray(),
            'datasets' => [[
                'label' => 'Usage Count',
                'data' => $data->pluck('usage_count')->toArray(),
                'backgroundColor' => $this->generateColors($data->count()),
            ]],
        ];
    }

    private function generateColors(int $count): array
    {
        $colors = [
            '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0',
            '#9966FF', '#FF9F40', '#FF6384', '#C9CBCF'
        ];
        
        return array_slice(array_merge($colors, $colors), 0, $count);
    }
}
```

## 5. Sistem pelaporan otomatis

```php
class AutomatedReportingService
{
    public function scheduleReports(): void
    {
        // Laporan penggunaan harian
        Schedule::call(function () {
            $this->generateDailyUsageReport();
        })->daily();

        // Analisis tren mingguan
        Schedule::call(function () {
            $this->generateWeeklyTrendReport();
        })->weekly();

        // Laporan komprehensif bulanan
        Schedule::call(function () {
            $this->generateMonthlyReport();
        })->monthly();
    }

    public function generateDailyUsageReport(): void
    {
        $report = (new ReportBuilder())
            ->filterByDateRange(now()->subDay(), now())
            ->groupByType()
            ->orderByUsage()
            ->build();

        $this->sendReportEmail('Daily Taxonomy Usage Report', $report, 'daily-usage');
    }

    public function generateWeeklyTrendReport(): void
    {
        $analyticsService = app(TaxonomyAnalyticsService::class);
        
        $trends = $analyticsService->getPopularityTrends('category', 7);
        $usage = $analyticsService->getUsageStatistics('tag');
        
        $this->sendReportEmail('Weekly Taxonomy Trends', [
            'trends' => $trends,
            'usage' => $usage,
        ], 'weekly-trends');
    }

    public function generateMonthlyReport(): void
    {
        $analyticsService = app(TaxonomyAnalyticsService::class);
        
        $report = [
            'summary' => $this->getMonthlyMetrics(),
            'category_analysis' => $analyticsService->getHierarchyDepthAnalysis('category'),
            'usage_trends' => $analyticsService->getPopularityTrends('category', 30),
            'performance_metrics' => $this->getPerformanceMetrics(),
        ];
        
        $this->sendReportEmail('Monthly Taxonomy Report', $report, 'monthly-comprehensive');
    }

    private function sendReportEmail(string $subject, array $data, string $template): void
    {
        $recipients = config('taxonomy.reporting.email_recipients', []);
        
        foreach ($recipients as $recipient) {
            Mail::to($recipient)->send(new TaxonomyReportMail($subject, $data, $template));
        }
    }

    private function getMonthlyMetrics(): array
    {
        $startOfMonth = now()->startOfMonth();
        
        return [
            'new_taxonomies' => Taxonomy::where('created_at', '>=', $startOfMonth)->count(),
            'new_associations' => DB::table('taxonomables')
                ->where('created_at', '>=', $startOfMonth)
                ->count(),
            'most_active_type' => $this->getMostActiveType($startOfMonth),
            'growth_rate' => $this->calculateGrowthRate($startOfMonth),
        ];
    }

    private function getMostActiveType(Carbon $startDate): ?string
    {
        return DB::table('taxonomables')
            ->join('taxonomies', 'taxonomables.taxonomy_id', '=', 'taxonomies.id')
            ->where('taxonomables.created_at', '>=', $startDate)
            ->select('taxonomies.type', DB::raw('COUNT(*) as count'))
            ->groupBy('taxonomies.type')
            ->orderBy('count', 'desc')
            ->first()?->type;
    }

    private function calculateGrowthRate(Carbon $startDate): float
    {
        $currentMonth = DB::table('taxonomables')
            ->where('created_at', '>=', $startDate)
            ->count();
            
        $previousMonth = DB::table('taxonomables')
            ->where('created_at', '>=', $startDate->copy()->subMonth())
            ->where('created_at', '<', $startDate)
            ->count();
            
        return $previousMonth > 0 ? (($currentMonth - $previousMonth) / $previousMonth) * 100 : 0;
    }

    private function getPerformanceMetrics(): array
    {
        return [
            'avg_query_time' => $this->measureAverageQueryTime(),
            'cache_efficiency' => $this->calculateCacheEfficiency(),
            'database_size' => $this->getDatabaseSize(),
            'optimization_suggestions' => $this->getOptimizationSuggestions(),
        ];
    }

    private function measureAverageQueryTime(): float
    {
        // Implementasi akan mengukur performa query aktual
        return 45.2; // Placeholder (milliseconds)
    }

    private function calculateCacheEfficiency(): float
    {
        // Implementasi akan menghitung rasio hit/miss cache
        return 87.5; // Placeholder (percentage)
    }

    private function getDatabaseSize(): array
    {
        return [
            'taxonomies_table_size' => '2.5 MB',
            'taxonomables_table_size' => '15.8 MB',
            'total_size' => '18.3 MB',
        ];
    }

    private function getOptimizationSuggestions(): array
    {
        return [
            'Pertimbangkan menambahkan indeks pada field meta yang sering di-query',
            'Arsipkan asosiasi taksonomi lama untuk meningkatkan performa',
            'Implementasikan caching yang lebih agresif untuk operasi read-heavy',
        ];
    }
}
```

Contoh analitik dan pelaporan ini mendemonstrasikan bagaimana Laravel Taxonomy dapat mendukung sistem business intelligence yang canggih dengan metrik real-time, pelaporan otomatis, dan kemampuan analisis data yang komprehensif.