<?php

namespace App\Http\Resources;

use App\Support\Departemen;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PengajuanMagangAdminResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'mahasiswa' => [
                // SESUAIKAN dengan nama kolom di tabel users milik temanmu
                'nama'            => $this->user->name,
                'nim'             => $this->user->nim,
                'departemen'      => Departemen::nama($this->user->departemen),
                'departemen_kode' => $this->user->departemen,
            ],
            // Ketua + anggota tambahan (ketua dihitung dari akun, bukan dari snapshot lama).
            'jumlah_mahasiswa' => $this->jumlahMahasiswa(),

            'instansi'        => $this->instansi,
            'instansi_kedua'  => $this->instansi_kedua,
            'jumlah_surat'    => $this->jumlahInstansi(),
            'tanggal_mulai'   => $this->tanggal_mulai?->toDateString(),
            'tanggal_selesai' => $this->tanggal_selesai?->toDateString(),
            'nomor_surat'       => $this->nomor_surat,
            'nomor_surat_kedua' => $this->nomor_surat_kedua,
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
            ],
            'dikirim_at' => $this->dikirim_at?->toIso8601String(),
        ];
    }
}