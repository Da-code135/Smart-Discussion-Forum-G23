<?php

namespace Database\Factories;

use App\Models\Post;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    protected $model = Post::class;

    public function definition(): array
    {
        return [
            'topic_id' => Topic::factory(),
            'user_id' => User::factory(),
            'content' => $this->faker->paragraph,
            'is_removed' => false,
            'is_reported' => false,
        ];
    }

    public function reported(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'is_reported' => true,
            ];
        });
    }

    public function removed(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'is_removed' => true,
            ];
        });
    }
}
