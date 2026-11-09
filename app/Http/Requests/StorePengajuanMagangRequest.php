<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
            'instansi'        => ['required', 'string', 'max:255'],
            'alamat_instansi' => ['required', 'string', 'max:500'],
            'tanggal_mulai'   => ['required', 'date', $isUpdate ? 'after_or_equal:1900-01-01' : 'after_or_equal:today'],
            'tanggal_selesai' => ['required', 'date', 'after:tanggal_mulai'],
        ];
    }

    public function attributes(): array
    {
        return [
            'instansi'        => 'nama instansi',
            'alamat_instansi' => 'alamat instansi',
            'tanggal_mulai'   => 'tanggal mulai',
            'tanggal_selesai' => 'tanggal selesai',
        ];
    }
}