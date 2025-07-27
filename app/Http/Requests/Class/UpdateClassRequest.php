<?php

namespace App\Http\Requests\Class;

use Illuminate\Foundation\Http\FormRequest;

class UpdateClassRequest extends FormRequest
{
    public function authorize()
    {
        return true; // Adjust authorization logic as needed
    }

    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:classes,code,' . $this->route('class'),
            'capacity' => 'nullable|integer|min:1',
            'description' => 'nullable|string',
            'subjects' => 'array',
            'subjects.*' => 'exists:subjects,id',
        ];
    }

    public function messages()
    {
        return [
            'name.required' => 'The class name is required.',
            'code.required' => 'The class code is required.',
            'code.unique' => 'The class code must be unique.',
            'capacity.integer' => 'The capacity must be an integer.',
        ];
    }
}
