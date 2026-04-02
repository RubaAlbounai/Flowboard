<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Task;

class TaskController extends Controller
{
    public function index()
    {
        return response()->json(Task::paginate(10), 200);
    }
    
    public function store(Request $request)
    {
        $data = $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable',
            'status'      => 'nullable|string',
        ]);
         
        $task = Task::create($data);

        return response()->json($task, 201);
    }

    public function show($id){
        $task = Task::findOrFail($id);
        return response()->json($task, 200);
    }

    public function update(Request $request, $id){
        
        $task = Task::findOrFail($id);
        
        $data = $request->validate([
        'title' => 'string|max:200',
        'description' => 'nullable|string',
        'status' => 'nullable|in:pending,in-progress,done'
        ]);

        $task->update($data);

        return response()->json($task, 201);
    }

    public function destroy($id){

        $task = Task::findOrFail($id);

        $task->delete();

        return response()->json(['message' => 'Task deleted successfully'],200);
    }
}
