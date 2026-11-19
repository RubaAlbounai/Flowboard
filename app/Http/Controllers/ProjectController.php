<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProjectRequest;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProjectController extends Controller
{
    // GET /api/projects: only the projects of the logged-in user
    public function index(Request $request)
    {
        $projects = $request->user()->projects()
            ->withCount([
                'tasks',
                'tasks as done_count' => fn ($query) => $query->where('status', Task::DONE),
            ])
            ->orderBy('name')
            ->get();

        return response()->json($projects, 200);
    }

    public function store(ProjectRequest $request)
    {
        $project = $request->user()->projects()->create($request->validated());

        return response()->json($project->loadCount('tasks'), 201);
    }

    public function show(Request $request, Project $project)
    {
        $this->ensureOwner($request, $project->user_id);

        return response()->json($project->loadCount('tasks'), 200);
    }

    public function update(ProjectRequest $request, Project $project)
    {
        $this->ensureOwner($request, $project->user_id);

        $project->update($request->validated());

        return response()->json($project, 200);
    }

    public function destroy(Request $request, Project $project)
    {
        $this->ensureOwner($request, $project->user_id);

        DB::transaction(function () use ($project) {
            $project->tasks()->delete();
            $project->delete();
        });

        return response()->json(['message' => 'Project deleted successfully'], 200);
    }
}
