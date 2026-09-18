<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $category = $this->route('category');

        $allowedFields = [
            'name',
            'slug',
            'description',
        ];

        $rules = [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('categories', 'name')->ignore($category),
            ],
            'slug' => [
                'required',
                'string',
                'max:255',
                Rule::unique('categories', 'slug')->ignore($category),
            ],
            'description' => [
                'nullable',
                'string',
            ],
        ];

        foreach (array_diff(array_keys($this->all()), $allowedFields) as $field) {
            $rules[$field] = ['prohibited'];
        }

        return $rules;
    }
}