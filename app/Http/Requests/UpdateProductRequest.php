<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        $product = $this->route('product');

        abort_unless(
            $this->user() !== null
            && $product !== null
            && $product->business_id === $this->user()->business_id,
            404
        );

        return true;
    }

    public function rules(): array
    {
        $product = $this->route('product');
        $businessId = $this->user()->business_id;

        $allowedFields = [
            'category_id',
            'name',
            'slug',
            'sku',
            'description',
            'price',
            'low_stock_threshold',
            'is_active',
        ];

        $rules = [
            'category_id' => [
                'required',
                'integer',
                Rule::exists('categories', 'id')
                    ->where('business_id', $businessId),
            ],

            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'slug' => [
                'required',
                'string',
                'max:255',
                Rule::unique('products', 'slug')
                    ->where('business_id', $businessId)
                    ->ignore($product),
            ],

            'sku' => [
                'required',
                'string',
                'max:255',
                Rule::unique('products', 'sku')
                    ->where('business_id', $businessId)
                    ->ignore($product),
            ],

            'description' => [
                'nullable',
                'string',
            ],

            'price' => [
                'required',
                'numeric',
                'min:0',
            ],

            'low_stock_threshold' => [
                'required',
                'integer',
                'min:0',
            ],

            'is_active' => [
                'required',
                'boolean',
            ],
        ];

        foreach (array_diff(array_keys($this->all()), $allowedFields) as $field) {
            $rules[$field] = ['prohibited'];
        }

        return $rules;
    }
}