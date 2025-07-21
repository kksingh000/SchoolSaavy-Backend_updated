<?php

namespace App\Http\Requests\Class;

use Illuminate\Foundation\Http\FormRequest;

class StoreClassRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'grade_level' => 'required|integer|min:1|max:12',
            'section' => 'nullable|string|max:10',
            'capacity' => 'required|integer|min:1',
            'teacher_id' => 'nullable|exists:teachers,id',
            'room_number' => 'nullable|string|max:50',
            'description' => 'nullable|string',
        ];
    }
}
