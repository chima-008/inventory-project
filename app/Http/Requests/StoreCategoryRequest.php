<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $businessId = $this->user()->business_id;

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
                Rule::unique('categories', 'name')
                    ->where('business_id', $businessId),
            ],

            'slug' => [
                'required',
                'string',
                'max:255',
                Rule::unique('categories', 'slug')
                    ->where('business_id', $businessId),
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