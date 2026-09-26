<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CompleteInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'token' => [
                'required',
                'string',
            ],

            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'token.required' =>
                'Invitation token is required.',

            'name.required' =>
                'Please enter your name.',

            'password.required' =>
                'Please create a password.',

            'password.min' =>
                'Your password must be at least 8 characters.',

            'password.confirmed' =>
                'The password confirmation does not match.',
        ];
    }
}