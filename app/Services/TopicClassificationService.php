<?php

namespace App\Services;

use App\Models\Topic;
use App\Models\TopicCategory;

class TopicClassificationService
{
    /**
     * Keywords that define each category
     * In a real system, this would be in a database
     * For MVP, we keep it simple
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
     * Classify a topic based on its title and description
     * Returns the best matching category
     */
    public function classifyTopic(Topic $topic)
    {
        $text = strtolower($topic->title.' '.$topic->description);
        $scores = [];

        // Score each category based on keyword matches
        foreach ($this->categoryKeywords as $categoryName => $keywords) {
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

        // If no keywords matched, use "General"
        if ($scores[$bestCategory] === 0) {
            $bestCategory = 'General';
        }

        // Find or create the category in the database
        $category = TopicCategory::firstOrCreate(
            [
                'group_id' => $topic->group_id,
                'category_name' => $bestCategory,
            ],
            [
                'keyword_hints' => implode(',', $this->categoryKeywords[$bestCategory]),
            ]
        );

        // Update the topic with the category
        $topic->update(['category_id' => $category->id]);

        return $category;
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
