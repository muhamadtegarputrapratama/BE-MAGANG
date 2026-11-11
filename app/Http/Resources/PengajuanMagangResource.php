<?php

namespace App\Http\Resources;

use App\Enums\StatusPengajuan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PengajuanMagangResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $setuju = $this->status === StatusPengajuan::Setuju;

        return [
            'id'              => $this->id,
            'instansi'        => $this->instansi,
            'alamat_instansi' => $this->alamat_instansi,
            'tanggal_mulai'   => $this->tanggal_mulai?->toDateString(),
            'tanggal_selesai' => $this->tanggal_selesai?->toDateString(),

            // Instansi kedua (null bila mahasiswa hanya mengajukan satu instansi)
            'instansi_kedua'        => $this->instansi_kedua,
            'alamat_instansi_kedua' => $this->alamat_instansi_kedua,
            'tanggal_mulai_kedua'   => $this->tanggal_mulai_kedua?->toDateString(),
            'tanggal_selesai_kedua' => $this->tanggal_selesai_kedua?->toDateString(),

            // Banyaknya surat = banyaknya instansi (1 atau 2)
            'jumlah_surat' => $this->jumlahInstansi(),

            // Ketua (urutan pertama, memuat user_id) + anggota kelompok
            'anggota' => $this->anggota,

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
                'bukti_spp'          => $this->file_bukti_spp !== null,
                'surat_rekomendasi'  => $this->file_surat_rekomendasi !== null,
                'surat_magang'       => $this->file_surat_magang !== null,
                'surat_magang_kedua' => $this->file_surat_magang_kedua !== null,
            ],
            'surat_magang_tersedia'       => $setuju && ! empty($this->file_surat_magang),
            'surat_magang_kedua_tersedia' => $setuju && ! empty($this->file_surat_magang_kedua),
            'diverifikasi_at' => $this->diverifikasi_at?->toIso8601String(),
            'dibuat_pada'     => $this->created_at->toIso8601String(),
        ];
    }
}   