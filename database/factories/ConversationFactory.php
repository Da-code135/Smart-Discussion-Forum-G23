<?php

namespace Database\Factories;

use App\Models\Conversation;
use App\Models\Group;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Conversation>
 */
class ConversationFactory extends Factory
{
    protected $model = Conversation::class;

    public function definition(): array
    {
        return [
            'group_id' => Group::factory(),
            'type' => 'direct',
            'name' => null,
            'last_activity_at' => now(),
        ];
    }

    /**
     * Indicate that the conversation is a direct (1-to-1) conversation.
     */
    public function direct(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'direct',
            'name' => null,
        ]);
    }

    /**
     * Indicate that the conversation is a group conversation.
     */
    public function group(string $name = null): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'group',
            'name' => $name ?? $this->faker->sentence(3),
        ]);
    }
}
