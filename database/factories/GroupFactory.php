<?php

namespace Database\Factories;

use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Group>
 */
class GroupFactory extends Factory
{
    protected $model = Group::class;

    public function definition(): array
    {
        return [
            'group_name' => $this->faker->company,
            'description' => $this->faker->sentence,
            'group_type' => 'student',
            'created_by' => User::factory(),
        ];
    }
}
