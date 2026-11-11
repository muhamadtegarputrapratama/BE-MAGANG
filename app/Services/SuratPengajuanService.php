<?php

namespace App\Services;

use App\Models\PengajuanMagang;
use Dompdf\Dompdf;
use Dompdf\Options;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class SuratPengajuanService
{
    /**
     * Membuat PDF final dengan QR untuk instansi ke-$urutan (1 atau 2) dan menyimpannya.
     * Mengembalikan path file yang berhasil disimpan.
     */
    public function simpan(PengajuanMagang $pengajuan, int $urutan = 1): string
    {
        // Gunakan nama unik agar tidak menimpa file sebelumnya.
        $path = "pengajuan/{$pengajuan->id}/surat-pengajuan-{$urutan}-"
            . Str::uuid()
            . '.pdf';

        // Buat PDF final dengan QR verifikasi.
        $pdf = $this->render($pengajuan, denganQr: true, urutan: $urutan);

        // Simpan PDF ke local storage.
        $berhasil = Storage::disk('local')->put($path, $pdf);

        // Pastikan penyimpanan berhasil.
        if (! $berhasil) {
            throw new RuntimeException(
                'Gagal menyimpan PDF surat pengajuan.'
            );
        }

        return $path;
    }

    /**
     * Membuat isi PDF untuk instansi ke-$urutan.
     * Tanpa QR digunakan untuk preview sebelum tanda tangan.
     */
    public function render(
        PengajuanMagang $pengajuan,
        bool $denganQr,
        int $urutan = 1
    ): string {
        $pengajuan->loadMissing('user');

        if (! $pengajuan->user) {
            throw new RuntimeException(
                'Data mahasiswa pemilik pengajuan tidak ditemukan.'
            );
        }

        $instansi = $pengajuan->daftarInstansi()[$urutan - 1]
            ?? throw new RuntimeException("Instansi ke-{$urutan} tidak ditemukan.");

        // Ketua nomor 1 + anggota tambahan. Departemen sudah berupa nama (bukan kode).
        $daftar  = $pengajuan->daftarAnggotaCetak();
        $pengaju = $daftar[0];

        $html = view('surat.pengajuan', [
            'anggota' => $daftar,
            'nama' => $pengaju['nama'],
            'nim' => $pengaju['nim'],
            'departemen' => $pengaju['departemen'],

            'fakultas' => config('magang.fakultas'),
            'kota' => config('magang.kota'),

            'instansi' => $instansi['nama'],
            'alamat' => $instansi['alamat'],

            'tanggalMulai' => $this->tanggal($instansi['mulai']),
            'tanggalSelesai' => $this->tanggal($instansi['selesai']),

            'tanggalSurat' => $this->tanggal(
                $pengajuan->ditandatangani_at ?? now()
            ),

            'qr' => $denganQr ? $this->buatQr($pengajuan, $urutan) : null,
        ])->render();

        $options = new Options();
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    /** Tanggal berbahasa Indonesia, contoh: "29 Oktober 2026". */
    private function tanggal($nilai): string
    {
        return Carbon::parse($nilai)->locale('id')->translatedFormat('j F Y');
    }

    /**
     * Membuat QR Code yang mengarah ke halaman verifikasi surat.
     * Surat instansi kedua memakai "?urutan=2" supaya QR-nya membuka surat yang tepat.
     */
    private function buatQr(PengajuanMagang $pengajuan, int $urutan = 1): string
    {
        $verifikasiUrl = rtrim((string) config('magang.verifikasi_url'), '/');

        if ($verifikasiUrl === '' || empty($pengajuan->kode_verifikasi)) {
            throw new RuntimeException('URL verifikasi atau kode verifikasi belum dikonfigurasi.');
        }

        $url = $verifikasiUrl . '/' . $pengajuan->kode_verifikasi;

        if ($urutan > 1) {
            $url .= '?urutan=' . $urutan;
        }

        $qrCode = new QrCode(
            data: $url,
            size: 200,
            margin: 5
        );

        return (new PngWriter())
            ->write($qrCode)
            ->getDataUri();
    }
}