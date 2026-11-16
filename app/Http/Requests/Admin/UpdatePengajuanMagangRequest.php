<?php

namespace App\Http\Requests\Admin;

use App\Support\Departemen;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePengajuanMagangRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $pengajuan = $this->route('pengajuan');
        $userId = $pengajuan?->user_id ?? $pengajuan?->user?->id;

        $afterMulaiPertama = $this->input('tanggal_mulai')
            ? 'after:tanggal_mulai'
            : 'after:' . ($pengajuan?->tanggal_mulai?->toDateString() ?? '1970-01-01');

        $afterMulaiKedua = $this->input('tanggal_mulai_kedua')
            ? 'after:tanggal_mulai_kedua'
            : 'after:' . ($pengajuan?->tanggal_mulai_kedua?->toDateString() ?? '1970-01-01');

        return [
            // Data Mahasiswa Pengaju
            'nama' => [
                'sometimes',
                'required',
                'string',
                'max:255',
            ],

            'nim' => [
                'sometimes',
                'required',
                'string',
                'regex:/^[A-Za-z][0-9]{10}$/',
                Rule::unique('users', 'nim')->ignore($userId),
            ],

            // Data Instansi Pertama
            'instansi' => [
                'sometimes',
                'required',
                'string',
                'max:255',
            ],

            'alamat_instansi' => [
                'sometimes',
                'required',
                'string',
                'max:5000',
            ],

            'tanggal_mulai' => [
                'sometimes',
                'required',
                'date_format:Y-m-d',
            ],

            'tanggal_selesai' => [
                'sometimes',
                'required',
                'date_format:Y-m-d',
                $afterMulaiPertama,
            ],

            // Data Instansi Kedua (opsional)
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
                'max:5000',
            ],

            'tanggal_mulai_kedua' => [
                'required_with:instansi_kedua',
                'nullable',
                'date_format:Y-m-d',
            ],

            'tanggal_selesai_kedua' => [
                'required_with:instansi_kedua',
                'nullable',
                'date_format:Y-m-d',
                $afterMulaiKedua,
            ],

            // Data Anggota Kelompok
            'anggota' => [
                'sometimes',
                'nullable',
                'array',
                'max:' . config('magang.maks_anggota', 10),
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
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nama.required' => 'Nama mahasiswa pengaju wajib diisi.',
            'nim.required' => 'NIM mahasiswa pengaju wajib diisi.',
            'nim.regex' => 'Format NIM mahasiswa pengaju tidak valid, contoh: E1401241051.',
            'nim.unique' => 'NIM mahasiswa pengaju sudah digunakan oleh akun lain.',
            'instansi.required' => 'Nama instansi wajib diisi.',
            'alamat_instansi.required' => 'Alamat instansi wajib diisi.',
            'tanggal_mulai.required' => 'Tanggal mulai wajib diisi.',
            'tanggal_selesai.required' => 'Tanggal selesai wajib diisi.',
            'tanggal_selesai.after' => 'Tanggal selesai harus setelah tanggal mulai.',
            'instansi_kedua.different' => 'Instansi kedua tidak boleh sama dengan instansi pertama.',
            'alamat_instansi_kedua.required_with' => 'Alamat instansi kedua wajib diisi jika nama instansi kedua diisi.',
            'tanggal_mulai_kedua.required_with' => 'Tanggal mulai instansi kedua wajib diisi jika nama instansi kedua diisi.',
            'tanggal_selesai_kedua.required_with' => 'Tanggal selesai instansi kedua wajib diisi jika nama instansi kedua diisi.',
            'tanggal_selesai_kedua.after' => 'Tanggal selesai instansi kedua harus setelah tanggal mulai instansi kedua.',
            'anggota.max' => 'Total mahasiswa maksimal ' . config('magang.maks_anggota', 10) . ' orang.',
            'anggota.*.nama.required' => 'Nama anggota wajib diisi.',
            'anggota.*.nim.required' => 'NIM anggota wajib diisi.',
            'anggota.*.nim.regex' => 'Format NIM anggota tidak valid, contoh: E1401241051.',
            'anggota.*.nim.distinct' => 'NIM anggota tidak boleh sama.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $nimInput = $this->input('nim');
            $pengajuan = $this->route('pengajuan');
            $nimPengaju = strtoupper(trim((string) ($nimInput ?: $pengajuan?->user?->nim)));

            if ($nimInput && ! Departemen::dariNim($nimInput)) {
                $v->errors()->add('nim', 'Departemen untuk NIM ini tidak dikenali.');
            }

            $anggota = (array) $this->input('anggota', []);
            $anggotaBukanKetua = 0;

            foreach ($anggota as $i => $a) {
                $nimAnggota = strtoupper(trim((string) ($a['nim'] ?? '')));
                if ($nimAnggota !== '' && $nimAnggota !== $nimPengaju) {
                    $anggotaBukanKetua++;
                    if (! Departemen::dariNim($nimAnggota)) {
                        $v->errors()->add("anggota.$i.nim", "Departemen untuk NIM {$nimAnggota} tidak dikenali.");
                    }
                }
            }

            $maksTambahan = max((int) config('magang.maks_anggota', 10) - 1, 0);
            if ($anggotaBukanKetua > $maksTambahan) {
                $v->errors()->add('anggota', "Maksimal {$maksTambahan} anggota tambahan (total " . config('magang.maks_anggota', 10) . " mahasiswa termasuk ketua).");
            }
        });
    }
}
