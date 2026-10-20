<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalisasi input sebelum divalidasi.
     */
    protected function prepareForValidation(): void
    {
        $name = $this->input('name');
        $nim = $this->input('nim');
        $email = $this->input('email');

        $this->merge([
            'name' => is_string($name)
                ? mb_convert_case(trim(preg_replace('/\s+/', ' ', $name)), MB_CASE_TITLE, 'UTF-8')
                : $name,
            'nim' => is_string($nim) ? strtoupper(trim($nim)) : $nim,
            'email' => is_string($email) ? strtolower(trim($email)) : $email,
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'nim' => [
                'required',
                'string',
                'regex:/^E(141|241|341|441)[0-9]{7}$/',
                'unique:users,nim',
            ],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama wajib diisi.',
            'nim.required' => 'NIM wajib diisi.',
            'nim.regex' => 'Format NIM tidak valid. Contoh: E1412300001 (E141, E241, E341, atau E441).',
            'nim.unique' => 'NIM sudah terdaftar.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email sudah terdaftar.',
            'password.required' => 'Password wajib diisi.',
            'password.min' => 'Password minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
        ];
    }
}
