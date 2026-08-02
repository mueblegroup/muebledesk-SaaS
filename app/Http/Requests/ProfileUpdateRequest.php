<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email:rfc,dns', 'max:255', Rule::unique(User::class)->ignore($this->user()->id)],
            'phone' => ['required', 'string', 'max:30'],
            'job_title' => ['required', 'string', 'max:100'],
            'address' => ['required', 'string', 'max:1000'],
            'country_code' => ['required', 'string', 'size:2'],
            'preferred_timezone' => ['required', 'timezone'],
        ];
    }
}
