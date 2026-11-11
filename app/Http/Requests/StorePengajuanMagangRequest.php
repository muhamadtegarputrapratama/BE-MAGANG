<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePengajuanMagangRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isUpdate = $this->isMethod('PUT') || $this->isMethod('PATCH');

        return [
            'instansi' => [
                'required',
                'string',
                'max:255',
            ],

            'alamat_instansi' => [
                'required',
                'string',
                'max:500',
            ],

            'tanggal_mulai' => [
                'required',
                'date',
                $isUpdate
                    ? 'after_or_equal:1900-01-01'
                    : 'after_or_equal:today',
            ],

            'tanggal_selesai' => [
                'required',
                'date',
                'after:tanggal_mulai',
            ],

   'anggota' => [
    'sometimes',
    'array',
    'max:50',
],

'anggota.*.nama' => [
    'required',
    'string',
    'max:255',
],

'anggota.*.nim' => [
    'required',
    'string',
    'regex:/^[A-Za-z][0-9]{10}$/',
    'distinct',
],

'anggota.*.departemen' => [
    'required',
    'string',
    'max:100',
],
        ];
    }

    public function attributes(): array
    {
        return [
            'instansi' => 'nama instansi',
            'alamat_instansi' => 'alamat instansi',
            'tanggal_mulai' => 'tanggal mulai',
            'tanggal_selesai' => 'tanggal selesai',
            'anggota' => 'anggota kelompok',
            'anggota.*' => 'NIM anggota',
        ];
    }
}
