<?php

namespace Database\Seeders;

use App\Models\Group;
use App\Models\TopicCategory;
use Illuminate\Database\Seeder;

class TopicCategorySeeder extends Seeder
{
    /**
     * The 4 default categories and their keyword hints for ML classification.
     * Each group gets all 4 categories.
     */
    private const DEFAULT_CATEGORIES = [
        [
            'category_name' => 'Mathematics',
            'keyword_hints' => 'equation, algebra, calculus, integral, derivative, theorem, proof, matrix, vector, geometry, trigonometry, statistic, probability',
        ],
        [
            'category_name' => 'Programming',
            'keyword_hints' => 'code, function, loop, array, variable, class, algorithm, syntax, debug, error, exception, framework, API, database, query, SQL',
        ],
        [
            'category_name' => 'Science',
            'keyword_hints' => 'chemistry, physics, biology, experiment, hypothesis, theory, lab, formula, element, reaction, cell, organism, energy, force',
        ],
        [
            'category_name' => 'General',
            'keyword_hints' => 'help, question, problem, issue, discuss, opinion, suggestion, feedback, idea, resource, reference, tutorial, guide',
        ],
    ];

    /**
     * Run the database seeds.
     *
     * Seeds 4 default categories (Mathematics, Programming, Science, General)
     * for EVERY group in the system — not just group_id=1.
     */
    public function run(): void
    {
        // Get all groups — seed categories for every group
        $groups = Group::all();

        if ($groups->isEmpty()) {
            $this->command->warn('No groups found. Run GroupSeeder first.');

            return;
        }

        foreach ($groups as $group) {
            foreach (self::DEFAULT_CATEGORIES as $category) {
                TopicCategory::updateOrCreate(
                    [
                        'group_id' => $group->id,
                        'category_name' => $category['category_name'],
                    ],
                    [
                        'keyword_hints' => $category['keyword_hints'],
                    ]
                );
            }
        }

        $groupCount = $groups->count();
        $categoryCount = count(self::DEFAULT_CATEGORIES);
        $this->command->info("✅ Seeded {$categoryCount} categories for {$groupCount} group(s).");
    }
}
