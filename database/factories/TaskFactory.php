<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaskFactory extends Factory
{
    public function definition()
    {
        return [
            'project_id'  => Project::factory(),
            'title'       => $this->faker->sentence(4),
            'description' => $this->faker->sentence(),
            'status'      => $this->faker->randomElement(Task::STATUSES),
            'priority'    => $this->faker->randomElement(Task::PRIORITIES),
            'due_date'    => null,
            'position'    => 0,
        ];
    }
}
