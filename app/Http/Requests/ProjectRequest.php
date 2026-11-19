<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProjectRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        // On update ("PUT") every field is optional, on create the name is required.
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'name'        => [$required, 'string', 'max:100'],
            'color'       => ['sometimes', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
