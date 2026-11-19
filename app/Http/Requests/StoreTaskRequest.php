<?php

namespace App\Http\Requests;

use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'title'       => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status'      => ['sometimes', Rule::in(Task::STATUSES)],
            'priority'    => ['sometimes', Rule::in(Task::PRIORITIES)],
            'due_date'    => ['nullable', 'date_format:Y-m-d'],
        ];
    }
}
