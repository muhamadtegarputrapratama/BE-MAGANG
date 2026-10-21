<?php

namespace App\Http\Resources;

use App\Enums\StatusPengajuan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PengajuanMagangResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'instansi'        => $this->instansi,
            'alamat_instansi' => $this->alamat_instansi,
            'tanggal_mulai'   => $this->tanggal_mulai->toDateString(),
            'tanggal_selesai' => $this->tanggal_selesai->toDateString(),
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
            ],
            // tampilkan catatan hanya saat status revisi
            'catatan_revisi'       => $this->status === StatusPengajuan::Revisi ? $this->catatan_revisi : null,
            'sudah_ditandatangani' => $this->sudahDitandatangani(),
            'ditandatangani_at'    => $this->ditandatangani_at?->toIso8601String(),
            'dikirim_at'           => $this->dikirim_at?->toIso8601String(),
            'berkas' => [
                'bukti_spp'         => $this->file_bukti_spp !== null,
                'surat_rekomendasi' => $this->file_surat_rekomendasi !== null,
            ],
            'diverifikasi_at' => $this->diverifikasi_at?->toIso8601String(),
            'dibuat_pada'     => $this->created_at->toIso8601String(),
        ];
    }
}