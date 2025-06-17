# Aplikasi Bisnis Multi-Tenant

**Skenario**: Platform SaaS dimana setiap tenant dapat memiliki struktur taksonomi mereka sendiri.

## 1. Model taksonomi yang sadar tenant

```php
class TenantTaxonomy extends Model
{
    use HasTaxonomy, SoftDeletes;

    protected $table = 'taxonomies';
    protected $fillable = ['name', 'slug', 'type', 'description', 'parent_id', 'tenant_id', 'meta'];

    protected $casts = [
        'meta' => 'array',
    ];

    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
```

## 2. Pengaturan taksonomi khusus tenant

```php
class TenantTaxonomyService
{
    public function setupDefaultTaxonomies(Tenant $tenant): void
    {
        $defaultStructure = [
            'departments' => [
                'Sales' => ['meta' => ['color' => '#007bff', 'manager_required' => true]],
                'Marketing' => ['meta' => ['color' => '#28a745', 'budget_tracking' => true]],
                'Engineering' => [
                    'meta' => ['color' => '#6f42c1', 'technical' => true],
                    'children' => [
                        'Frontend' => ['meta' => ['skills' => ['React', 'Vue', 'Angular']]],
                        'Backend' => ['meta' => ['skills' => ['Laravel', 'Node.js', 'Python']]],
                        'DevOps' => ['meta' => ['skills' => ['Docker', 'Kubernetes', 'AWS']]],
                    ],
                ],
            ],
            'project_types' => [
                'Internal' => ['meta' => ['billable' => false]],
                'Client Work' => ['meta' => ['billable' => true, 'requires_contract' => true]],
                'R&D' => ['meta' => ['billable' => false, 'innovation' => true]],
            ],
            'priorities' => [
                'Low' => ['meta' => ['color' => '#6c757d', 'sla_days' => 30]],
                'Medium' => ['meta' => ['color' => '#ffc107', 'sla_days' => 14]],
                'High' => ['meta' => ['color' => '#fd7e14', 'sla_days' => 7]],
                'Critical' => ['meta' => ['color' => '#dc3545', 'sla_days' => 1]],
            ],
        ];

        foreach ($defaultStructure as $type => $items) {
            $this->createTaxonomyStructure($tenant, $type, $items);
        }
    }

    private function createTaxonomyStructure(Tenant $tenant, string $type, array $items, ?int $parentId = null): void
    {
        foreach ($items as $name => $config) {
            $taxonomy = TenantTaxonomy::create([
                'name' => $name,
                'type' => $type,
                'tenant_id' => $tenant->id,
                'parent_id' => $parentId,
                'meta' => $config['meta'] ?? [],
            ]);

            if (isset($config['children'])) {
                $this->createTaxonomyStructure($tenant, $type, $config['children'], $taxonomy->id);
            }
        }
    }
}
```

## 3. Manajemen proyek yang sadar tenant

```php
class Project extends Model
{
    use HasTaxonomy;

    protected $fillable = ['name', 'description', 'tenant_id', 'status'];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function getDepartmentAttribute()
    {
        return $this->taxonomiesOfType('departments')->first();
    }

    public function getPriorityAttribute()
    {
        return $this->taxonomiesOfType('priorities')->first();
    }
}
```

## 4. Dashboard multi-tenant

```php
class DashboardController extends Controller
{
    public function index()
    {
        $tenant = auth()->user()->tenant;
        
        // Dapatkan taksonomi khusus tenant
        $departments = TenantTaxonomy::forTenant($tenant->id)
            ->where('type', 'departments')
            ->withCount(['models as project_count' => function ($query) use ($tenant) {
                $query->where('taxonomable_type', Project::class)
                    ->whereHas('taxonomable', function ($q) use ($tenant) {
                        $q->where('tenant_id', $tenant->id);
                    });
            }])
            ->get();

        // Statistik proyek berdasarkan prioritas
        $priorityStats = TenantTaxonomy::forTenant($tenant->id)
            ->where('type', 'priorities')
            ->with(['models' => function ($query) use ($tenant) {
                $query->where('taxonomable_type', Project::class)
                    ->whereHas('taxonomable', function ($q) use ($tenant) {
                        $q->where('tenant_id', $tenant->id)
                            ->where('status', 'active');
                    });
            }])
            ->get()
            ->map(function ($priority) {
                return [
                    'name' => $priority->name,
                    'color' => $priority->meta['color'] ?? '#6c757d',
                    'count' => $priority->models->count(),
                    'sla_days' => $priority->meta['sla_days'] ?? null,
                ];
            });

        return view('dashboard.index', compact('departments', 'priorityStats'));
    }
}
```

## 5. Kustomisasi dan branding tenant

```php
class TenantCustomizationService
{
    public function customizeTaxonomyStructure(Tenant $tenant, array $customizations): void
    {
        foreach ($customizations as $type => $config) {
            // Perbarui taksonomi yang ada
            if (isset($config['update'])) {
                foreach ($config['update'] as $taxonomyId => $updates) {
                    $taxonomy = TenantTaxonomy::forTenant($tenant->id)
                        ->where('id', $taxonomyId)
                        ->first();
                    
                    if ($taxonomy) {
                        $taxonomy->update($updates);
                    }
                }
            }

            // Tambahkan taksonomi baru
            if (isset($config['add'])) {
                foreach ($config['add'] as $newTaxonomy) {
                    TenantTaxonomy::create(array_merge($newTaxonomy, [
                        'tenant_id' => $tenant->id,
                        'type' => $type,
                    ]));
                }
            }

            // Hapus taksonomi
            if (isset($config['remove'])) {
                TenantTaxonomy::forTenant($tenant->id)
                    ->whereIn('id', $config['remove'])
                    ->delete();
            }
        }
    }

    public function applyTenantBranding(Tenant $tenant, array $brandingConfig): void
    {
        $tenant->update([
            'branding_config' => array_merge($tenant->branding_config ?? [], $brandingConfig)
        ]);

        // Perbarui warna taksonomi berdasarkan warna brand
        if (isset($brandingConfig['primary_color'])) {
            $this->updateTaxonomyColors($tenant, $brandingConfig['primary_color']);
        }
    }

    private function updateTaxonomyColors(Tenant $tenant, string $primaryColor): void
    {
        $colorVariations = $this->generateColorVariations($primaryColor);
        
        $taxonomies = TenantTaxonomy::forTenant($tenant->id)
            ->whereJsonContains('meta->color', null, 'or')
            ->get();

        foreach ($taxonomies as $index => $taxonomy) {
            $colorIndex = $index % count($colorVariations);
            $meta = $taxonomy->meta;
            $meta['color'] = $colorVariations[$colorIndex];
            $taxonomy->update(['meta' => $meta]);
        }
    }

    private function generateColorVariations(string $baseColor): array
    {
        // Buat variasi warna berdasarkan warna dasar
        return [
            $baseColor,
            $this->adjustBrightness($baseColor, 20),
            $this->adjustBrightness($baseColor, -20),
            $this->adjustBrightness($baseColor, 40),
            $this->adjustBrightness($baseColor, -40),
        ];
    }

    private function adjustBrightness(string $hex, int $percent): string
    {
        // Implementasi untuk menyesuaikan kecerahan warna
        $hex = ltrim($hex, '#');
        $rgb = array_map('hexdec', str_split($hex, 2));
        
        foreach ($rgb as &$color) {
            $color = max(0, min(255, $color + ($color * $percent / 100)));
        }
        
        return '#' . implode('', array_map(function($c) {
            return str_pad(dechex($c), 2, '0', STR_PAD_LEFT);
        }, $rgb));
    }
}
```

## 6. Isolasi data tenant dan keamanan

```php
class TenantSecurityMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $user = auth()->user();
        
        if (!$user || !$user->tenant_id) {
            abort(403, 'Access denied: No tenant association');
        }

        // Atur konteks tenant untuk request
        app()->instance('current_tenant', $user->tenant);
        
        // Terapkan global scope untuk isolasi tenant
        TenantTaxonomy::addGlobalScope('tenant', function ($query) use ($user) {
            $query->where('tenant_id', $user->tenant_id);
        });

        Project::addGlobalScope('tenant', function ($query) use ($user) {
            $query->where('tenant_id', $user->tenant_id);
        });

        return $next($request);
    }
}
```

## 7. Analitik dan pelaporan tenant

```php
class TenantAnalyticsService
{
    public function getTenantUsageStats(Tenant $tenant): array
    {
        return [
            'total_taxonomies' => TenantTaxonomy::forTenant($tenant->id)->count(),
            'total_projects' => Project::forTenant($tenant->id)->count(),
            'active_projects' => Project::forTenant($tenant->id)->where('status', 'active')->count(),
            'taxonomy_usage' => $this->getTaxonomyUsageStats($tenant),
            'department_productivity' => $this->getDepartmentProductivity($tenant),
            'priority_distribution' => $this->getPriorityDistribution($tenant),
        ];
    }

    private function getTaxonomyUsageStats(Tenant $tenant): array
    {
        return TenantTaxonomy::forTenant($tenant->id)
            ->withCount('models')
            ->get()
            ->groupBy('type')
            ->map(function ($taxonomies, $type) {
                return [
                    'type' => $type,
                    'total_taxonomies' => $taxonomies->count(),
                    'total_usage' => $taxonomies->sum('models_count'),
                    'average_usage' => $taxonomies->avg('models_count'),
                ];
            })
            ->values()
            ->toArray();
    }

    private function getDepartmentProductivity(Tenant $tenant): array
    {
        $departments = TenantTaxonomy::forTenant($tenant->id)
            ->where('type', 'departments')
            ->with(['models' => function ($query) {
                $query->where('taxonomable_type', Project::class)
                    ->where('status', 'completed')
                    ->where('created_at', '>=', now()->subMonth());
            }])
            ->get();

        return $departments->map(function ($department) {
            $completedProjects = $department->models->count();
            $avgCompletionTime = $this->calculateAverageCompletionTime($department);
            
            return [
                'department' => $department->name,
                'completed_projects' => $completedProjects,
                'avg_completion_days' => $avgCompletionTime,
                'productivity_score' => $this->calculateProductivityScore($completedProjects, $avgCompletionTime),
            ];
        })->toArray();
    }

    private function getPriorityDistribution(Tenant $tenant): array
    {
        return TenantTaxonomy::forTenant($tenant->id)
            ->where('type', 'priorities')
            ->withCount(['models' => function ($query) {
                $query->where('taxonomable_type', Project::class)
                    ->where('status', 'active');
            }])
            ->get()
            ->map(function ($priority) {
                return [
                    'priority' => $priority->name,
                    'count' => $priority->models_count,
                    'color' => $priority->meta['color'] ?? '#6c757d',
                    'sla_days' => $priority->meta['sla_days'] ?? null,
                ];
            })
            ->toArray();
    }

    private function calculateAverageCompletionTime(TenantTaxonomy $department): float
    {
        // Implementasi akan menghitung rata-rata waktu penyelesaian proyek
        return 15.5; // Placeholder (hari)
    }

    private function calculateProductivityScore(int $completedProjects, float $avgCompletionTime): float
    {
        // Algoritma penilaian produktivitas kustom
        $baseScore = $completedProjects * 10;
        $timeBonus = max(0, (30 - $avgCompletionTime) * 2);
        
        return min(100, $baseScore + $timeBonus);
    }
}
```

Contoh multi-tenant ini menunjukkan bagaimana Laravel Taxonomy dapat mendukung aplikasi SaaS yang kompleks dengan isolasi tenant, struktur yang dapat disesuaikan, dan analitik komprehensif sambil mempertahankan keamanan data dan performa.