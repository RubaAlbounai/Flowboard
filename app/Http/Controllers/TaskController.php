<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TaskController extends Controller
{
    // GET /api/projects/{project}/tasks?status=done&priority=high&search=report
    public function index(Request $request, Project $project)
    {
        $this->ensureOwner($request, $project->user_id);

        $request->validate([
            'status'   => ['nullable', Rule::in(Task::STATUSES)],
            'priority' => ['nullable', Rule::in(Task::PRIORITIES)],
        ]);

        $tasks = $project->tasks()
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->when($request->query('priority'), fn ($query, $priority) => $query->where('priority', $priority))
            ->when($request->query('search'), fn ($query, $search) => $query->where('title', 'like', '%' . $search . '%'))
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        return response()->json($tasks, 200);
    }

    // POST /api/projects/{project}/tasks
    public function store(StoreTaskRequest $request, Project $project)
    {
        $this->ensureOwner($request, $project->user_id);

        $data = $request->validated();
        $status = $data['status'] ?? Task::PENDING;

        // A new task goes to the bottom of its column.
        $data['position'] = $this->nextPosition($project, $status);

        $task = $project->tasks()->create($data);

        return response()->json($task, 201);
    }

    public function show(Request $request, Task $task)
    {
        $this->ensureTaskOwner($request, $task);

        return response()->json($task, 200);
    }

    public function update(UpdateTaskRequest $request, Task $task)
    {
        $this->ensureTaskOwner($request, $task);

        $data = $request->validated();

        // Changing the status from the form puts the task at the bottom of the new column.
        if (isset($data['status']) && $data['status'] !== $task->status) {
            $oldStatus = $task->status;
            $data['position'] = $this->nextPosition($task->project, $data['status']);
            $task->update($data);
            Task::reindexColumn($task->project_id, $oldStatus);
        } else {
            $task->update($data);
        }

        return response()->json($task, 200);
    }

    // POST /api/tasks/{task}/move  { "status": "done", "position": 0 }
    public function move(Request $request, Task $task)
    {
        $this->ensureTaskOwner($request, $task);

        $data = $request->validate([
            'status'   => ['required', Rule::in(Task::STATUSES)],
            'position' => ['required', 'integer', 'min:0'],
        ]);

        DB::transaction(function () use ($task, $data) {
            $oldStatus = $task->status;

            // The other tasks of the target column, in order, without this one.
            $ids = Task::where('project_id', $task->project_id)
                ->where('status', $data['status'])
                ->where('id', '!=', $task->id)
                ->orderBy('position')
                ->orderBy('id')
                ->pluck('id')
                ->all();

            array_splice($ids, min($data['position'], count($ids)), 0, [$task->id]);

            $task->update(['status' => $data['status']]);

            foreach ($ids as $position => $id) {
                Task::whereKey($id)->update(['position' => $position]);
            }

            if ($oldStatus !== $data['status']) {
                Task::reindexColumn($task->project_id, $oldStatus);
            }
        });

        return response()->json($task->fresh(), 200);
    }

    public function destroy(Request $request, Task $task)
    {
        $this->ensureTaskOwner($request, $task);

        $task->delete();
        Task::reindexColumn($task->project_id, $task->status);

        return response()->json(['message' => 'Task deleted successfully'], 200);
    }

    private function ensureTaskOwner(Request $request, Task $task): void
    {
        abort_unless($task->project && (int) $task->project->user_id === (int) $request->user()->id, 404);
    }

    private function nextPosition(Project $project, string $status): int
    {
        $max = $project->tasks()->where('status', $status)->max('position');

        return $max === null ? 0 : $max + 1;
    }
}
