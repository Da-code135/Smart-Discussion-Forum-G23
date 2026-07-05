<?php

namespace Database\Factories;

use App\Models\ModerationLog;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ModerationLog>
 */
class ModerationLogFactory extends Factory
{
    protected $model = ModerationLog::class;

    public function definition(): array
    {
        return [
            'post_id' => Post::factory(),
            'admin_id' => User::factory(),
            'action' => 'removed',
            'reason' => $this->faker->sentence,
        ];
    }

    public function ignored(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'action' => 'ignored',
            ];
        });
    }

    public function warned(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'action' => 'warned',
            ];
        });
    }
}