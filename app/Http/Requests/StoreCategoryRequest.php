<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
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
                'unique:categories,name',
            ],
            'slug' => [
                'required',
                'string',
                'max:255',
                'unique:categories,slug',
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