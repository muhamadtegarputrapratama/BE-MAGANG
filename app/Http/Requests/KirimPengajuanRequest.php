<?php

namespace App\Http\Requests;

use App\Enums\StatusPengajuan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class KirimPengajuanRequest extends FormRequest
{
    public function authorize(): bool
    {
        $pengajuan = $this->route('pengajuan');
        $user = $this->user();

        // Harus login dan hanya boleh mengirim pengajuan miliknya.
        if (! $user || ! $pengajuan) {
            return false;
        }

        if ((int) $pengajuan->user_id !== (int) $user->id) {
            return false;
        }

        // Pengajuan hanya bisa dikirim ketika draft atau revisi.
        return $pengajuan->bisaDiedit();
    }

    public function rules(): array
    {
        $pengajuan = $this->route('pengajuan');

        return [
            'bukti_spp' => [
                Rule::requiredIf(! $pengajuan?->file_bukti_spp),
                'file',
                'mimes:pdf,jpg,jpeg,png',
                'max:2048',
            ],

            'surat_rekomendasi' => [
                Rule::requiredIf(! $pengajuan?->file_surat_rekomendasi),
                'file',
                'mimes:pdf,jpg,jpeg,png',
                'max:2048',
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'bukti_spp' => 'bukti SPP',
            'surat_rekomendasi' => 'surat rekomendasi',
        ];
    }
}
