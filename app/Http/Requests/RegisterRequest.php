<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use App\Enum\Role;

class RegisterRequest extends FormRequest
{
   // Determine if the user is authorized to make this request.
    public function authorize(): bool
    {
        return true;
    }

   
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|in:seller,buyer,admin',
            
        ];
    }
}
