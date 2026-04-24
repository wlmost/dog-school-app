<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Register Request
 *
 * Validates user registration data.
 * Admins can create any user, trainers can only create customers.
 */
class RegisterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        
        // No authenticated user
        if (!$user) {
            return false;
        }
        
        // Admins can register any user
        if ($user->isAdmin()) {
            return true;
        }
        
        // Trainers can only register customers
        if ($user->isTrainer()) {
            $role = $this->input('role');
            return $role === 'customer';
        }
        
        return false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['nullable', 'string', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
            'role' => ['required', 'string', Rule::in(['admin', 'trainer', 'customer'])],
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'Die E-Mail-Adresse ist erforderlich.',
            'email.email' => 'Bitte geben Sie eine gültige E-Mail-Adresse ein.',
            'email.unique' => 'Diese E-Mail-Adresse wird bereits verwendet.',
            'password.required' => 'Das Passwort ist erforderlich.',
            'password.confirmed' => 'Die Passwort-Bestätigung stimmt nicht überein.',
            'role.required' => 'Die Rolle ist erforderlich.',
            'role.in' => 'Die ausgewählte Rolle ist ungültig.',
        ];
    }
}
