<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TaskTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->project = Project::factory()->create(['user_id' => $this->user->id]);
        Sanctum::actingAs($this->user);
    }

    private function addTask(string $title, string $status = 'pending'): int
    {
        return $this->postJson("/api/projects/{$this->project->id}/tasks", ['title' => $title, 'status' => $status])
            ->assertCreated()
            ->json('id');
    }

    private function titles(string $status): array
    {
        return collect($this->getJson("/api/projects/{$this->project->id}/tasks?status={$status}")->json())
            ->pluck('title')
            ->all();
    }

    public function test_a_task_is_created_with_defaults()
    {
        $this->postJson("/api/projects/{$this->project->id}/tasks", ['title' => 'Learn testing'])
            ->assertCreated()
            ->assertJsonPath('title', 'Learn testing')
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('priority', 'normal')
            ->assertJsonPath('position', 0)
            ->assertJsonPath('due_date', null);
    }

    public function test_new_tasks_go_to_the_bottom_of_their_column()
    {
        $this->addTask('First');
        $this->addTask('Second');
        $this->addTask('Other column', 'done');

        $this->assertSame(['First', 'Second'], $this->titles('pending'));
        $this->assertSame(1, Task::where('title', 'Second')->first()->position);
        $this->assertSame(0, Task::where('title', 'Other column')->first()->position);
    }

    public function test_task_data_is_validated()
    {
        $this->postJson("/api/projects/{$this->project->id}/tasks", [
            'description' => 'no title', 'status' => 'p', 'priority' => 'whenever', 'due_date' => '15/11/2026',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'status', 'priority', 'due_date']);

        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_a_due_date_is_saved_and_returned()
    {
        $this->postJson("/api/projects/{$this->project->id}/tasks", ['title' => 'Pay rent', 'due_date' => '2026-12-01'])
            ->assertCreated()
            ->assertJsonPath('due_date', '2026-12-01');
    }

    public function test_tasks_can_be_filtered_and_searched()
    {
        Task::factory()->create(['project_id' => $this->project->id, 'title' => 'Write the report', 'status' => 'done', 'priority' => 'high']);
        Task::factory()->create(['project_id' => $this->project->id, 'title' => 'Buy milk', 'status' => 'pending', 'priority' => 'low']);

        $url = "/api/projects/{$this->project->id}/tasks";

        $this->getJson($url)->assertOk()->assertJsonCount(2);
        $this->getJson($url . '?status=done')->assertOk()->assertJsonCount(1);
        $this->getJson($url . '?priority=low')->assertOk()->assertJsonCount(1)->assertJsonPath('0.title', 'Buy milk');
        $this->getJson($url . '?search=report')->assertOk()->assertJsonCount(1)->assertJsonPath('0.title', 'Write the report');
        $this->getJson($url . '?status=finished')->assertStatus(422)->assertJsonValidationErrors('status');
    }

    public function test_a_task_can_be_updated()
    {
        $id = $this->addTask('Old title');

        $this->putJson("/api/tasks/{$id}", ['title' => 'New title', 'priority' => 'urgent', 'due_date' => '2026-12-24'])
            ->assertOk()
            ->assertJsonPath('title', 'New title')
            ->assertJsonPath('priority', 'urgent')
            ->assertJsonPath('due_date', '2026-12-24');
    }

    public function test_an_update_can_change_a_single_field_and_cannot_blank_the_title()
    {
        $id = $this->addTask('Keep me');

        $this->putJson("/api/tasks/{$id}", ['priority' => 'high'])->assertOk();
        $this->assertSame('Keep me', Task::find($id)->title);

        $this->putJson("/api/tasks/{$id}", ['title' => ''])->assertStatus(422)->assertJsonValidationErrors('title');
    }

    public function test_changing_the_status_moves_the_task_to_the_bottom_of_the_new_column()
    {
        $a = $this->addTask('A');
        $this->addTask('B');
        $this->addTask('Already done', 'done');

        $this->putJson("/api/tasks/{$a}", ['status' => 'done'])->assertOk();

        $this->assertSame(['B'], $this->titles('pending'));
        $this->assertSame(['Already done', 'A'], $this->titles('done'));
        $this->assertSame(0, Task::where('title', 'B')->first()->position);
    }

    public function test_a_task_can_be_moved_inside_its_column()
    {
        $this->addTask('A');
        $this->addTask('B');
        $c = $this->addTask('C');

        $this->postJson("/api/tasks/{$c}/move", ['status' => 'pending', 'position' => 0])->assertOk();

        $this->assertSame(['C', 'A', 'B'], $this->titles('pending'));
    }

    public function test_a_task_can_be_moved_to_another_column()
    {
        $a = $this->addTask('A');
        $this->addTask('B');
        $this->addTask('X', 'in-progress');
        $this->addTask('Y', 'in-progress');

        $this->postJson("/api/tasks/{$a}/move", ['status' => 'in-progress', 'position' => 1])->assertOk();

        $this->assertSame(['B'], $this->titles('pending'));
        $this->assertSame(['X', 'A', 'Y'], $this->titles('in-progress'));
        $this->assertSame([0, 1, 2], Task::where('status', 'in-progress')->orderBy('position')->pluck('position')->all());
    }

    public function test_moving_checks_its_input()
    {
        $id = $this->addTask('A');

        $this->postJson("/api/tasks/{$id}/move", ['status' => 'nope', 'position' => -1])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status', 'position']);
    }

    public function test_deleting_a_task_closes_the_gap_in_its_column()
    {
        $this->addTask('A');
        $b = $this->addTask('B');
        $this->addTask('C');

        $this->deleteJson("/api/tasks/{$b}")->assertOk();

        $this->assertSame(['A', 'C'], $this->titles('pending'));
        $this->assertSame([0, 1], Task::orderBy('position')->pluck('position')->all());
    }

    public function test_other_peoples_tasks_cannot_be_reached()
    {
        $theirs = Project::factory()->create();
        $task = Task::factory()->create(['project_id' => $theirs->id, 'title' => 'Secret']);

        $this->getJson("/api/projects/{$theirs->id}/tasks")->assertNotFound();
        $this->postJson("/api/projects/{$theirs->id}/tasks", ['title' => 'Intruder'])->assertNotFound();
        $this->getJson("/api/tasks/{$task->id}")->assertNotFound();
        $this->putJson("/api/tasks/{$task->id}", ['title' => 'Hacked'])->assertNotFound();
        $this->postJson("/api/tasks/{$task->id}/move", ['status' => 'done', 'position' => 0])->assertNotFound();
        $this->deleteJson("/api/tasks/{$task->id}")->assertNotFound();

        $this->assertSame('Secret', $task->fresh()->title);
        $this->assertDatabaseCount('tasks', 1);
    }
}
