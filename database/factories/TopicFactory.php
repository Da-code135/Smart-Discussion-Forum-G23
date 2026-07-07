<?php

namespace Database\Factories;

use App\Models\Group;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Topic>
 */
class TopicFactory extends Factory
{
    protected $model = Topic::class;

    public function definition(): array
    {
        return [
            'group_id' => Group::factory(),
            'created_by' => User::factory(),
            'title' => $this->faker->sentence,
            'description' => $this->faker->paragraph,
            'status' => 'active',
            'post_type' => 'discussion',
        ];
    }

    public function question(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'post_type' => 'question',
            ];
        });
    }

    public function archived(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'archived',
            ];
        });
    }
}
