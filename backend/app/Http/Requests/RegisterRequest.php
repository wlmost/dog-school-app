<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
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

        // Only authenticated admins and trainers may register new users.
        // Trainers are further restricted to the "customer" role in rules().
        return $user && ($user->isAdmin() || $user->isTrainer());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // The set of roles a caller may assign is derived exclusively from
        // the authenticated caller's own role (server-side auth state), not
        // from any client-supplied field. Admins may register any role;
        // trainers may only register customers.
        $allowedRoles = $this->user()?->isAdmin()
            ? ['admin', 'trainer', 'customer']
            : ['customer'];

        return [
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['nullable', 'string', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
            'role' => ['required', 'string', Rule::in($allowedRoles)],
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
