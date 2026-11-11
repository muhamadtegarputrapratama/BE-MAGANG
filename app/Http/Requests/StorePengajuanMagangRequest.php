<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
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

        // Total mahasiswa per pengajuan = 10 TERMASUK ketua, jadi anggota tambahan maksimal 9.
        $maksTambahan = max((int) config('magang.maks_anggota', 10) - 1, 0);

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

            // Instansi kedua (opsional) -> menghasilkan surat kedua
            'instansi_kedua' => [
                'nullable',
                'string',
                'max:255',
                'different:instansi',
            ],

            'alamat_instansi_kedua' => [
                'required_with:instansi_kedua',
                'nullable',
                'string',
                'max:500',
            ],

            'tanggal_mulai_kedua' => [
                'required_with:instansi_kedua',
                'nullable',
                'date',
                $isUpdate
                    ? 'after_or_equal:1900-01-01'
                    : 'after_or_equal:today',
            ],

            'tanggal_selesai_kedua' => [
                'required_with:instansi_kedua',
                'nullable',
                'date',
                'after:tanggal_mulai_kedua',
            ],

            // Anggota tambahan selain ketua (ketua ditambahkan otomatis oleh service)
            'anggota' => [
                'sometimes',
                'nullable',
                'array',
                "max:{$maksTambahan}",
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
                'distinct:ignore_case',
            ],

            'anggota.*.departemen' => [
                'required',
                'string',
                'max:100',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'anggota.max'              => 'Maksimal :max anggota tambahan (total ' . config('magang.maks_anggota', 10) . ' mahasiswa termasuk ketua).',
            'anggota.*.nim.regex'      => 'NIM anggota harus berupa satu huruf diikuti 10 angka, contoh E1401241051.',
            'anggota.*.nim.distinct'   => 'NIM anggota tidak boleh sama.',
            'instansi_kedua.different' => 'Instansi kedua tidak boleh sama dengan instansi pertama.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $nimKetua = strtoupper((string) $this->user()?->nim);

            foreach ((array) $this->input('anggota', []) as $i => $a) {
                if (strtoupper((string) ($a['nim'] ?? '')) === $nimKetua) {
                    $v->errors()->add("anggota.$i.nim", 'NIM ketua kelompok tidak perlu dimasukkan kembali.');
                }
            }
        });
    }

    public function attributes(): array
    {
        return [
            'instansi' => 'nama instansi',
            'alamat_instansi' => 'alamat instansi',
            'tanggal_mulai' => 'tanggal mulai',
            'tanggal_selesai' => 'tanggal selesai',
            'instansi_kedua' => 'nama instansi kedua',
            'alamat_instansi_kedua' => 'alamat instansi kedua',
            'tanggal_mulai_kedua' => 'tanggal mulai instansi kedua',
            'tanggal_selesai_kedua' => 'tanggal selesai instansi kedua',
            'anggota' => 'anggota kelompok',
            'anggota.*.nama' => 'nama anggota',
            'anggota.*.nim' => 'NIM anggota',
            'anggota.*.departemen' => 'departemen anggota',
        ];
    }
}