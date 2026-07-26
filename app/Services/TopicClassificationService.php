<?php

namespace App\Services;

use App\Models\SystemConfig;
use App\Models\Topic;
use App\Models\TopicCategory;

class TopicClassificationService
{
    /**
     * Built-in keywords that define each category.
     * Administrators can extend these per group by editing the
     * keyword_hints column on topic_categories (comma-separated).
     */
    private $categoryKeywords = [
        'Django' => ['django', 'python', 'framework', 'views', 'models', 'templates'],
        'APIs' => ['api', 'rest', 'endpoint', 'http', 'json', 'request'],
        'Database' => ['database', 'sql', 'query', 'table', 'column', 'join', 'relational'],
        'JavaScript' => ['javascript', 'js', 'react', 'vue', 'node', 'npm'],
        'CSS' => ['css', 'styling', 'bootstrap', 'tailwind', 'design', 'layout'],
        'General' => [],  // Fallback if no match
    ];

    /**
     * Classify a topic based on its title and description.
     *
     * Per SDD §5.2.2 / Appendix A:
     * - The category with the most matching keywords wins.
     * - Confidence = matching words for the winner / total matching words.
     * - If confidence falls below the configured threshold, the topic is
     *   flagged for administrator review.
     *
     * Returns the best matching category.
     */
    public function classifyTopic(Topic $topic)
    {
        $text = strtolower($topic->title.' '.$topic->description);
        $keywordMap = $this->keywordMapForGroup($topic->group_id);
        $scores = [];

        // Score each category based on keyword matches
        foreach ($keywordMap as $categoryName => $keywords) {
            $score = 0;

            foreach ($keywords as $keyword) {
                // Count how many times this keyword appears
                $score += substr_count($text, $keyword);
            }

            $scores[$categoryName] = $score;
        }

        // Sort scores in descending order and get the key of the highest score
        arsort($scores);
        $bestCategory = array_key_first($scores);
        $totalMatches = array_sum($scores);

        // If no keywords matched, use "General"
        if ($scores[$bestCategory] === 0) {
            $bestCategory = 'General';
        }

        // Confidence: share of all matching words that point to the winner
        // (e.g. 9 of 10 matches for Mathematics = 90%). Zero matches = 0%.
        $confidence = $totalMatches > 0
            ? (int) round($scores[$bestCategory] / $totalMatches * 100)
            : 0;

        // Low-confidence classifications are flagged for admin review
        $reviewThreshold = (int) SystemConfig::getValue('classification_review_threshold', 40);
        $needsReview = $confidence < $reviewThreshold;

        // Find or create the category in the database
        $category = TopicCategory::firstOrCreate(
            [
                'group_id' => $topic->group_id,
                'category_name' => $bestCategory,
            ],
            [
                'keyword_hints' => implode(',', $keywordMap[$bestCategory] ?? []),
            ]
        );

        // Update the topic with the category and classification metadata
        $topic->update([
            'category_id' => $category->id,
            'classification_confidence' => $confidence,
            'classification_needs_review' => $needsReview,
        ]);

        return $category;
    }

    /**
     * Build the keyword map for a group: built-in defaults merged with
     * any admin-added keyword_hints stored on the group's categories.
     *
     * @return array<string, list<string>>
     */
    private function keywordMapForGroup(?int $groupId): array
    {
        $map = $this->categoryKeywords;

        if ($groupId === null) {
            return $map;
        }

        $dbCategories = TopicCategory::forGroup($groupId)
            ->whereNotNull('keyword_hints')
            ->pluck('keyword_hints', 'category_name');

        foreach ($dbCategories as $categoryName => $hints) {
            $keywords = array_values(array_filter(array_map(
                fn (string $hint) => strtolower(trim($hint)),
                explode(',', $hints),
            )));

            $map[$categoryName] = array_values(array_unique(array_merge(
                $map[$categoryName] ?? [],
                $keywords,
            )));
        }

        // General stays a pure fallback with no keywords of its own
        $map['General'] = [];

        return $map;
    }

    /**
     * Classify all topics in a group
     * Run once to bulk-classify existing topics
     */
    public function classifyGroupTopics($groupId)
    {
        $topics = Topic::where('group_id', $groupId)
            ->whereNull('category_id')  // Only unclassified
            ->get();

        foreach ($topics as $topic) {
            $this->classifyTopic($topic);
        }

        return count($topics);
    }
}
