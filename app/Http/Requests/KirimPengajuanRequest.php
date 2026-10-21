<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class KirimPengajuanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $pengajuan = $this->route('pengajuan');

        // Wajib jika belum pernah diupload. Saat revisi, boleh upload ulang sebagian saja.
        return [
            'bukti_spp' => [
                Rule::requiredIf(! $pengajuan?->file_bukti_spp),
                'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048',
            ],
            'surat_rekomendasi' => [
                Rule::requiredIf(! $pengajuan?->file_surat_rekomendasi),
                'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048',
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'bukti_spp'         => 'bukti SPP',
            'surat_rekomendasi' => 'surat rekomendasi',
        ];
    }
}