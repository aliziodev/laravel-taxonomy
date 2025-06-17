# Learning Management System

**Scenario**: Educational platform with courses, skills, difficulty levels, and learning paths.

## 1. Set up educational taxonomies

```php
// Create skill categories
$programmingSkill = Taxonomy::create([
    'name' => 'Programming',
    'type' => 'skill',
    'meta' => [
        'icon' => 'code',
        'industry' => 'Technology',
        'demand_level' => 'high',
    ],
]);

$webDevelopment = Taxonomy::create([
    'name' => 'Web Development',
    'type' => 'skill',
    'parent_id' => $programmingSkill->id,
    'meta' => [
        'prerequisites' => ['HTML', 'CSS', 'JavaScript'],
        'career_paths' => ['Frontend Developer', 'Full Stack Developer'],
    ],
]);

// Create difficulty levels
$difficulties = [
    ['name' => 'Beginner', 'order' => 1, 'color' => '#28a745'],
    ['name' => 'Intermediate', 'order' => 2, 'color' => '#ffc107'],
    ['name' => 'Advanced', 'order' => 3, 'color' => '#dc3545'],
];

foreach ($difficulties as $difficulty) {
    Taxonomy::create([
        'name' => $difficulty['name'],
        'type' => 'difficulty',
        'sort_order' => $difficulty['order'],
        'meta' => [
            'color' => $difficulty['color'],
            'estimated_hours' => $difficulty['order'] * 20,
        ],
    ]);
}
```

## 2. Course model with learning paths

```php
class Course extends Model
{
    use HasTaxonomy;

    protected $fillable = ['title', 'description', 'duration_hours', 'price'];

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }

    public function getSkillsAttribute()
    {
        return $this->taxonomiesOfType('skill');
    }

    public function getDifficultyAttribute()
    {
        return $this->taxonomiesOfType('difficulty')->first();
    }
}
```

## 3. Create learning paths

```php
$course = Course::create([
    'title' => 'Complete Laravel Developer Course',
    'description' => 'Master Laravel from basics to advanced concepts',
    'duration_hours' => 40,
    'price' => 99.99,
]);

$course->attachTaxonomies([
    $webDevelopment->id,
    Taxonomy::findBySlug('intermediate', 'difficulty')->id,
]);
```

## 4. Build skill-based course recommendations

```php
class CourseController extends Controller
{
    public function recommendations(User $user)
    {
        // Get user's completed courses and their skills
        $completedCourses = $user->enrollments()
            ->where('completed', true)
            ->with('course.taxonomies')
            ->get()
            ->pluck('course');

        $userSkills = $completedCourses
            ->flatMap(function ($course) {
                return $course->taxonomiesOfType('skill');
            })
            ->unique('id');

        // Find courses that build on user's existing skills
        $recommendedCourses = Course::query()
            ->whereNotIn('id', $completedCourses->pluck('id'))
            ->where(function ($query) use ($userSkills) {
                // Courses with related skills
                $query->withAnyTaxonomies($userSkills->pluck('id'))
                    // Or courses with parent skills of user's skills
                    ->orWhereHas('taxonomies', function ($q) use ($userSkills) {
                        $parentSkillIds = $userSkills
                            ->flatMap(fn($skill) => $skill->getAncestors())
                            ->pluck('id');
                        $q->whereIn('taxonomies.id', $parentSkillIds);
                    });
            })
            ->with(['taxonomies'])
            ->limit(10)
            ->get();

        return view('courses.recommendations', compact('recommendedCourses', 'userSkills'));
    }

    public function skillPath($skillSlug)
    {
        $skill = Taxonomy::findBySlug($skillSlug, 'skill');
        
        if (!$skill) {
            abort(404);
        }

        // Get all courses for this skill and its descendants
        $skillIds = collect([$skill->id])
            ->merge($skill->getDescendants()->pluck('id'));

        $courses = Course::withAnyTaxonomies($skillIds)
            ->with(['taxonomies' => function ($query) {
                $query->where('type', 'difficulty');
            }])
            ->get()
            ->groupBy(function ($course) {
                return $course->difficulty->name ?? 'Unknown';
            });

        return view('courses.skill-path', compact('skill', 'courses'));
    }
}
```

## 5. Learning progress tracking

```php
class LearningProgressService
{
    public function getUserSkillProgress(User $user): Collection
    {
        $enrollments = $user->enrollments()->with('course.taxonomies')->get();
        
        $skillProgress = collect();
        
        foreach ($enrollments as $enrollment) {
            $skills = $enrollment->course->taxonomiesOfType('skill');
            
            foreach ($skills as $skill) {
                $existing = $skillProgress->firstWhere('skill_id', $skill->id);
                
                if ($existing) {
                    $existing['total_courses']++;
                    if ($enrollment->completed) {
                        $existing['completed_courses']++;
                    }
                } else {
                    $skillProgress->push([
                        'skill_id' => $skill->id,
                        'skill_name' => $skill->name,
                        'total_courses' => 1,
                        'completed_courses' => $enrollment->completed ? 1 : 0,
                        'progress_percentage' => 0,
                    ]);
                }
            }
        }
        
        return $skillProgress->map(function ($item) {
            $item['progress_percentage'] = ($item['completed_courses'] / $item['total_courses']) * 100;
            return $item;
        });
    }

    public function getRecommendedNextCourses(User $user, int $limit = 5): Collection
    {
        $userSkills = $this->getUserSkillProgress($user)
            ->where('progress_percentage', '>', 50)
            ->pluck('skill_id');

        // Find advanced courses in user's skill areas
        $advancedDifficulty = Taxonomy::where('type', 'difficulty')
            ->where('name', 'Advanced')
            ->first();

        return Course::withAnyTaxonomies($userSkills)
            ->withAnyTaxonomies([$advancedDifficulty->id])
            ->whereNotIn('id', function ($query) use ($user) {
                $query->select('course_id')
                    ->from('enrollments')
                    ->where('user_id', $user->id);
            })
            ->limit($limit)
            ->get();
    }
}
```

## 6. Skill assessment and certification

```php
class SkillAssessmentService
{
    public function createSkillAssessment(Taxonomy $skill): Assessment
    {
        $assessment = Assessment::create([
            'title' => "Assessment: {$skill->name}",
            'description' => "Test your knowledge in {$skill->name}",
            'passing_score' => 80,
        ]);

        $assessment->attachTaxonomies([$skill->id]);

        return $assessment;
    }

    public function generateCertificate(User $user, Taxonomy $skill): Certificate
    {
        $completedCourses = $user->enrollments()
            ->where('completed', true)
            ->whereHas('course', function ($query) use ($skill) {
                $query->withAnyTaxonomies([$skill->id]);
            })
            ->count();

        $passedAssessments = $user->assessmentResults()
            ->where('passed', true)
            ->whereHas('assessment', function ($query) use ($skill) {
                $query->withAnyTaxonomies([$skill->id]);
            })
            ->count();

        if ($completedCourses >= 3 && $passedAssessments >= 1) {
            return Certificate::create([
                'user_id' => $user->id,
                'skill_name' => $skill->name,
                'issued_at' => now(),
                'certificate_number' => $this->generateCertificateNumber(),
            ]);
        }

        throw new InsufficientRequirementsException('User has not met certification requirements');
    }

    private function generateCertificateNumber(): string
    {
        return 'CERT-' . strtoupper(Str::random(8)) . '-' . date('Y');
    }
}
```

## 7. Learning analytics and insights

```php
class LearningAnalyticsService
{
    public function getSkillDemandAnalytics(): Collection
    {
        return Taxonomy::where('type', 'skill')
            ->withCount(['models as course_count'])
            ->with(['models' => function ($query) {
                $query->withCount('enrollments');
            }])
            ->get()
            ->map(function ($skill) {
                $totalEnrollments = $skill->models->sum('enrollments_count');
                
                return [
                    'skill_name' => $skill->name,
                    'course_count' => $skill->course_count,
                    'total_enrollments' => $totalEnrollments,
                    'demand_score' => $this->calculateDemandScore($skill, $totalEnrollments),
                    'growth_trend' => $this->calculateGrowthTrend($skill),
                ];
            })
            ->sortByDesc('demand_score');
    }

    public function getLearningPathEffectiveness(): array
    {
        $skills = Taxonomy::where('type', 'skill')
            ->with(['children', 'models'])
            ->get();

        $pathAnalytics = [];

        foreach ($skills as $skill) {
            if ($skill->children->isNotEmpty()) {
                $pathAnalytics[] = [
                    'skill_path' => $skill->name,
                    'total_courses' => $skill->getDescendants()->sum(function ($descendant) {
                        return $descendant->models->count();
                    }),
                    'completion_rate' => $this->calculatePathCompletionRate($skill),
                    'average_time_to_complete' => $this->calculateAverageCompletionTime($skill),
                ];
            }
        }

        return $pathAnalytics;
    }

    private function calculateDemandScore(Taxonomy $skill, int $enrollments): float
    {
        $industryDemand = $skill->meta['demand_level'] ?? 'medium';
        $baseScore = match($industryDemand) {
            'high' => 100,
            'medium' => 70,
            'low' => 40,
            default => 50
        };

        return $baseScore + ($enrollments * 0.1);
    }

    private function calculateGrowthTrend(Taxonomy $skill): string
    {
        // Implementation would analyze enrollment trends over time
        return 'increasing'; // Placeholder
    }

    private function calculatePathCompletionRate(Taxonomy $skill): float
    {
        // Implementation would calculate completion rates for learning paths
        return 75.5; // Placeholder
    }

    private function calculateAverageCompletionTime(Taxonomy $skill): int
    {
        // Implementation would calculate average time to complete skill path
        return 120; // Placeholder (days)
    }
}
```

This LMS example demonstrates how Laravel Taxonomy can create sophisticated educational platforms with skill tracking, personalized learning paths, and comprehensive analytics.