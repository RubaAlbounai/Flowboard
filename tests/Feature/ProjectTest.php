<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProjectTest extends TestCase
{
    use RefreshDatabase;

    public function test_projects_need_a_login()
    {
        $this->getJson('/api/projects')->assertStatus(401);
        $this->postJson('/api/projects', ['name' => 'X'])->assertStatus(401);
    }

    public function test_a_user_only_sees_their_own_projects()
    {
        $me = User::factory()->create();
        Project::factory()->create(['user_id' => $me->id, 'name' => 'Mine']);
        Project::factory()->create(['name' => 'Somebody else']);

        Sanctum::actingAs($me);

        $this->getJson('/api/projects')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.name', 'Mine');
    }

    public function test_the_list_includes_task_counts()
    {
        $me = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $me->id]);
        Task::factory()->count(2)->create(['project_id' => $project->id, 'status' => Task::DONE]);
        Task::factory()->create(['project_id' => $project->id, 'status' => Task::PENDING]);

        Sanctum::actingAs($me);

        $this->getJson('/api/projects')
            ->assertOk()
            ->assertJsonPath('0.tasks_count', 3)
            ->assertJsonPath('0.done_count', 2);
    }

    public function test_a_project_can_be_created_with_a_default_colour()
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/projects', ['name' => 'Launch plan'])
            ->assertCreated()
            ->assertJsonPath('name', 'Launch plan');

        $this->assertDatabaseHas('projects', ['name' => 'Launch plan', 'color' => '#6d5dfc']);
    }

    public function test_project_data_is_validated()
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/projects', ['color' => 'red'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'color']);
    }

    public function test_a_project_can_be_renamed()
    {
        $me = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $me->id]);
        Sanctum::actingAs($me);

        $this->putJson("/api/projects/{$project->id}", ['name' => 'Renamed'])
            ->assertOk()
            ->assertJsonPath('name', 'Renamed');
    }

    public function test_other_peoples_projects_cannot_be_read_changed_or_deleted()
    {
        $project = Project::factory()->create();
        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/projects/{$project->id}")->assertNotFound();
        $this->putJson("/api/projects/{$project->id}", ['name' => 'Hacked'])->assertNotFound();
        $this->deleteJson("/api/projects/{$project->id}")->assertNotFound();

        $this->assertDatabaseHas('projects', ['id' => $project->id]);
    }

    public function test_deleting_a_project_deletes_its_tasks()
    {
        $me = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $me->id]);
        Task::factory()->count(2)->create(['project_id' => $project->id]);
        Sanctum::actingAs($me);

        $this->deleteJson("/api/projects/{$project->id}")->assertOk();

        $this->assertDatabaseCount('projects', 0);
        $this->assertDatabaseCount('tasks', 0);
    }
}
