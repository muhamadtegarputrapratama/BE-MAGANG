<?php

namespace App\Http\Resources;

use App\Enums\StatusPengajuan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * Sama seperti PengajuanMagangResource, ditambah daftar berkas (dengan link bertanda tangan)
 * supaya mahasiswa bisa melihat berkas yang sudah mereka kirim.
 */
class PengajuanMagangMahasiswaResource extends PengajuanMagangResource
{
    public function toArray(Request $request): array
    {
        $setuju = $this->status === StatusPengajuan::Setuju;

        return array_merge(parent::toArray($request), [
            // Nomor surat hanya diberikan setelah pengajuan disetujui
            'nomor_surat'       => $setuju ? $this->nomor_surat : null,
            'nomor_surat_kedua' => $setuju ? $this->nomor_surat_kedua : null,

            // Menggantikan 'berkas' bawaan (yang hanya true/false) dengan daftar berkas + link
            'berkas'      => $this->daftarBerkas(),
        ]);
    }

    private function daftarBerkas(): array
    {
        // Bila ada dua instansi, label surat diberi keterangan instansi 1 / 2.
        $dua = $this->jumlahInstansi() === 2;

        $berkas = [
            'surat-pengajuan'       => ['label' => 'Surat Pengajuan' . ($dua ? ' (Instansi 1)' : ''), 'path' => $this->file_surat_pengajuan],
            'surat-pengajuan-kedua' => ['label' => 'Surat Pengajuan (Instansi 2)',                     'path' => $this->file_surat_pengajuan_kedua],
            'bukti-spp'             => ['label' => 'Bukti Pembayaran SPP',                             'path' => $this->file_bukti_spp],
            'surat-rekomendasi'     => ['label' => 'Surat Rekomendasi',                                'path' => $this->file_surat_rekomendasi],
            'surat-magang'          => ['label' => 'Surat Pengantar Magang' . ($dua ? ' (Instansi 1)' : ''), 'path' => $this->file_surat_magang],
            'surat-magang-kedua'    => ['label' => 'Surat Pengantar Magang (Instansi 2)',              'path' => $this->file_surat_magang_kedua],
        ];

        $hasil = [];

        foreach ($berkas as $jenis => $item) {
            if (! $item['path']) {
                continue;
            }

            $hasil[] = [
                'jenis'        => $jenis,
                'label'        => $item['label'],
                'ekstensi'     => strtolower(pathinfo($item['path'], PATHINFO_EXTENSION)),
                'preview_url'  => $this->urlBerkas($jenis, 'preview'),
                'download_url' => $this->urlBerkas($jenis, 'download'),
            ];
        }

        return $hasil;
    }

    /** Link sementara (30 menit) bertanda tangan; hanya dibuat untuk pemilik pengajuan. */
    private function urlBerkas(string $jenis, string $mode): string
    {
        return URL::temporarySignedRoute(
            'admin.pengajuan.berkas',
            now()->addMinutes(30),
            [
                'pengajuan' => $this->id,
                'jenis'     => $jenis,
                'mode'      => $mode,
            ]
        );
    }
}