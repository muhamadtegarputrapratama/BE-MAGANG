<?php

namespace App\Http\Resources;

use App\Models\PengajuanMagang;
use App\Support\Departemen;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PengajuanMagangAdminResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Pada daftar, jumlahnya sudah dihitung satu kali per halaman oleh controller;
        // pada detail, dihitung di sini.
        $atribut = $this->resource->getAttributes();
        $jumlahPengajuan = array_key_exists('jumlah_pengajuan_mahasiswa', $atribut)
            ? (int) $atribut['jumlah_pengajuan_mahasiswa']
            : $this->resource->jumlahPengajuanMahasiswa();
        $ambang = PengajuanMagang::ambangPeringatan();

        return [
            'id' => $this->id,

            // Peringatan untuk admin: mahasiswa yang sama (NIM sama) sudah mengajukan 2 kali atau lebih.
            'pengajuan_mahasiswa' => [
                'jumlah'           => $jumlahPengajuan,
                'ambang'           => $ambang,
                'perlu_peringatan' => $jumlahPengajuan >= $ambang,
            ],

            'mahasiswa' => [
                'nama'            => $this->user->name,
                'nim'             => $this->user->nim,
                'email'           => $this->user->email,
                'no_wa'           => $this->user->no_wa,
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