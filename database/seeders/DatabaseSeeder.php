<?php

namespace Database\Seeders;

use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    // Demo data for local use. Do not run this against a real database.
    public function run()
    {
        $user = User::firstOrNew(['email' => 'demo@example.com']);
        $user->forceFill(['name' => 'Demo User', 'password' => Hash::make('password')])->save();

        if ($user->projects()->exists()) {
            return;
        }

        $projects = [
            ['Website redesign', '#6d5dfc', 'New look for the company site', [
                ['Collect feedback from the team', Task::DONE, 'normal', -6],
                ['Design the new home page', Task::DONE, 'high', -3],
                ['Build the pricing page', Task::IN_PROGRESS, 'high', 2],
                ['Write the contact form', Task::IN_PROGRESS, 'normal', 5],
                ['Fix the mobile menu', Task::PENDING, 'urgent', -1],
                ['Compress the images', Task::PENDING, 'low', null],
                ['Set up analytics', Task::PENDING, 'normal', 12],
            ]],
            ['Learning plan', '#22b07d', 'Things to study this month', [
                ['Finish the Laravel testing course', Task::IN_PROGRESS, 'high', 7],
                ['Practise Russian for 20 minutes', Task::PENDING, 'normal', null],
                ['Read about database indexes', Task::PENDING, 'low', 14],
            ]],
        ];

        foreach ($projects as [$name, $color, $description, $tasks]) {
            $project = $user->projects()->create(['name' => $name, 'color' => $color, 'description' => $description]);
            $positions = [];

            foreach ($tasks as [$title, $status, $priority, $days]) {
                $positions[$status] = ($positions[$status] ?? -1) + 1;
                $project->tasks()->create([
                    'title'    => $title,
                    'status'   => $status,
                    'priority' => $priority,
                    'due_date' => $days === null ? null : now()->addDays($days)->toDateString(),
                    'position' => $positions[$status],
                ]);
            }
        }
    }
}
