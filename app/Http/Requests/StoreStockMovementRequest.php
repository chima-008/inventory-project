<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreStockMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $allowedFields = [
            'type',
            'quantity',
            'reason',
            'notes',
        ];

        $rules = [
            'type' => [
                'required',
                'string',
                'in:in,out',
            ],

            'quantity' => [
                'required',
                'integer',
                'min:1',
            ],

            'reason' => [
                'required',
                'string',
                'max:255',
            ],

            'notes' => [
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