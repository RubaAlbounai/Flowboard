<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProjectFactory extends Factory
{
    public function definition()
    {
        return [
            'user_id'     => User::factory(),
            'name'        => $this->faker->words(2, true),
            'color'       => '#6d5dfc',
            'description' => $this->faker->sentence(),
        ];
    }
}
