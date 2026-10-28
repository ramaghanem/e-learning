<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),

            ],

            'bio' => ['nullable', 'string', 'max:500'],

            'phone' => ['nullable', 'string', 'max:30'],

            'profile_picture' => [
            'nullable',
            'image',
            'mimes:jpg,jpeg,png',
            'max:2048',
        ],
        ];
    }
}
