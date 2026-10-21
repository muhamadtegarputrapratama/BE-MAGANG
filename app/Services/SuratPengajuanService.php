<?php

namespace App\Services;

use App\Models\PengajuanMagang;
use Dompdf\Dompdf;
use Dompdf\Options;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Facades\Storage;

class SuratPengajuanService
{
    /** Membuat PDF final (dengan QR), disimpan ke storage. Mengembalikan path-nya. */
    public function simpan(PengajuanMagang $pengajuan): string
    {
        $path = "pengajuan/{$pengajuan->id}/surat-pengajuan.pdf";

        Storage::disk('local')->put($path, $this->render($pengajuan, denganQr: true));

        return $path;
    }

    /** Membuat isi PDF. Tanpa QR dipakai untuk preview sebelum tanda tangan. */
    public function render(PengajuanMagang $pengajuan, bool $denganQr): string
    {
        $pengajuan->loadMissing('user');
        $user = $pengajuan->user;

        $html = view('surat.pengajuan', [
            // SESUAIKAN dengan nama kolom di tabel users milik temanmu
            'nama'       => $user->name,
            'nim'        => $user->nim,
            'departemen' => $user->departemen,

            'fakultas'       => config('magang.fakultas'),
            'kota'           => config('magang.kota'),
            'instansi'       => $pengajuan->instansi,
            'alamat'         => $pengajuan->alamat_instansi,
            'tanggalMulai'   => $pengajuan->tanggal_mulai->translatedFormat('j F Y'),
            'tanggalSelesai' => $pengajuan->tanggal_selesai->translatedFormat('j F Y'),
            'tanggalSurat'   => ($pengajuan->ditandatangani_at ?? now())->translatedFormat('j F Y'),
            'qr'             => $denganQr ? $this->buatQr($pengajuan) : null,
        ])->render();

        $options = new Options();
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    private function buatQr(PengajuanMagang $pengajuan): string
    {
        $url = rtrim(config('magang.frontend_url'), '/') . '/verifikasi/' . $pengajuan->kode_verifikasi;

        return (new PngWriter())
            ->write(new QrCode(data: $url, size: 200, margin: 5))
            ->getDataUri();
    }
}