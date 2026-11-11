<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePengajuanMagangRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'instansi' => [
                'required',
                'string',
                'max:255',
            ],

            'alamat_instansi' => [
                'required',
                'string',
                'max:5000',
            ],

            'tanggal_mulai' => [
                'required',
                'date_format:Y-m-d',
            ],

            'tanggal_selesai' => [
                'required',
                'date_format:Y-m-d',
                'after:tanggal_mulai',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'instansi.required' => 'Nama instansi wajib diisi.',
            'alamat_instansi.required' => 'Alamat instansi wajib diisi.',
            'tanggal_mulai.required' => 'Tanggal mulai wajib diisi.',
            'tanggal_selesai.required' => 'Tanggal selesai wajib diisi.',
            'tanggal_selesai.after' => 'Tanggal selesai harus setelah tanggal mulai.',
        ];
    }
}
