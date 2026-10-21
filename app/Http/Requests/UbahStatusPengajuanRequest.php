<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UbahStatusPengajuanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status'  => ['required', Rule::in(['setuju', 'revisi'])],
            'catatan' => ['required_if:status,revisi', 'nullable', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'status'  => 'status',
            'catatan' => 'catatan revisi',
        ];
    }

    public function messages(): array
    {
        return [
            'catatan.required_if' => 'Catatan wajib diisi jika pengajuan perlu direvisi.',
        ];
    }
}