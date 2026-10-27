<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class PengajuanMagangAdminDetailResource extends PengajuanMagangAdminResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'alamat_instansi' => $this->alamat_instansi,
            'tanggal_mulai'   => $this->tanggal_mulai->toDateString(),
            'tanggal_selesai' => $this->tanggal_selesai->toDateString(),
            'catatan_revisi'  => $this->catatan_revisi,
            'nomor_surat'     => $this->nomor_surat,
            'diverifikasi_at' => $this->diverifikasi_at?->toIso8601String(),
            'berkas'          => $this->daftarBerkas(),
            'bukti_spp_url'         => $this->urlBerkas($this->file_bukti_spp, 'spp'),
            'surat_rekomendasi_url' => $this->urlBerkas($this->file_surat_rekomendasi, 'rekomendasi'),
        ]);
    }

    private function daftarBerkas(): array
    {
        $berkas = [
            'surat-pengajuan'   => ['label' => 'Surat Pengajuan',        'path' => $this->file_surat_pengajuan],
            'bukti-spp'         => ['label' => 'Bukti Pembayaran SPP',   'path' => $this->file_bukti_spp],
            'surat-rekomendasi' => ['label' => 'Surat Rekomendasi',      'path' => $this->file_surat_rekomendasi],
            'surat-magang'      => ['label' => 'Surat Pengantar Magang', 'path' => $this->file_surat_magang],
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

   private function urlBerkas(?string $path, string $jenis): ?string
{
    if (! $path) {
        return null;
    }

    return URL::temporarySignedRoute(
        'admin.pengajuan.berkas',
        now()->addMinutes(30),
        ['pengajuan' => $this->id, 'jenis' => $jenis, 'nama' => basename($path)]
    );
}
}
