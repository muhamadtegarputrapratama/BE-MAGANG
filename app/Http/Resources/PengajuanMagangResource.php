<?php

namespace App\Http\Resources;

use App\Enums\StatusPengajuan;
use App\Support\Departemen;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PengajuanMagangResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing('user');

        $setuju = $this->status === StatusPengajuan::Setuju;
        $kodeDepartemen = $this->user?->departemen;

        return [
            'id' => $this->id,

            // Data ketua selalu dari akun, jadi ikut berubah bila admin mengedit nama / NIM.
            'mahasiswa' => [
                'nama'            => $this->user?->name,
                'nim'             => $this->user?->nim,
                'departemen'      => $kodeDepartemen ? Departemen::nama($kodeDepartemen) : null,
                'departemen_kode' => $kodeDepartemen,
            ],

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

            // Anggota kelompok SELAIN ketua (ketua ada di 'mahasiswa').
            // Ketua sengaja tidak ikut, supaya saat revisi tidak terkirim balik sebagai anggota.
            'jumlah_mahasiswa' => $this->jumlahMahasiswa(),
            'anggota' => collect($this->anggotaTambahan())
                ->map(fn (array $a) => [
                    'nama'       => $a['nama'],
                    'nim'        => $a['nim'],
                    'departemen' => $a['departemen'] ? Departemen::nama($a['departemen']) : null,
                ])
                ->values()
                ->all(),

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