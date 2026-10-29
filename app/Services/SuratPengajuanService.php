<?php
namespace App\Services;

use App\Models\PengajuanMagang;
use App\Support\Departemen;
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
     * Membuat PDF final dengan QR dan menyimpannya ke storage.
     * Mengembalikan path file yang berhasil disimpan.
     */
    public function simpan(PengajuanMagang $pengajuan): string
    {
        // Gunakan nama unik agar tidak menimpa file sebelumnya.
        $path = "pengajuan/{$pengajuan->id}/surat-pengajuan-"
            . Str::uuid()
            . '.pdf';

        // Buat PDF final dengan QR verifikasi.
        $pdf = $this->render($pengajuan, denganQr: true);

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
     * Membuat isi PDF.
     * Tanpa QR digunakan untuk preview sebelum tanda tangan.
     */
    public function render(
        PengajuanMagang $pengajuan,
        bool $denganQr
    ): string {
        $pengajuan->loadMissing('user');

        $user = $pengajuan->user;

        // Pastikan pengajuan memiliki pengguna.
        if (! $user) {
            throw new RuntimeException(
                'Data mahasiswa untuk surat pengajuan tidak ditemukan.'
            );
        }

        // Siapkan data untuk template surat.
        $html = view('surat.pengajuan', [
            'nama' => $user->name,
            'nim' => $user->nim,
            'departemen' => Departemen::namaDariKode($user->departemen) ?? $user->departemen,

            'fakultas' => config('magang.fakultas'),
            'kota' => config('magang.kota'),

            'instansi' => $pengajuan->instansi,
            'alamat' => $pengajuan->alamat_instansi,

            'tanggalMulai' => $this->tanggal($pengajuan->tanggal_mulai),
            'tanggalSelesai' => $this->tanggal($pengajuan->tanggal_selesai),
            'tanggalSurat' => $this->tanggal($pengajuan->ditandatangani_at ?? now()),

            'qr' => $denganQr
                ? $this->buatQr($pengajuan)
                : null,
        ])->render();

        // Konfigurasi Dompdf.
        $options = new Options();

        // Nonaktifkan akses sumber daya remote.
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
     */
    private function buatQr(PengajuanMagang $pengajuan): string
    {
        $verifikasiUrl = rtrim((string) config('magang.verifikasi_url'), '/');
        if ($verifikasiUrl === '' || empty($pengajuan->kode_verifikasi)) {
            throw new RuntimeException('URL verifikasi atau kode verifikasi belum dikonfigurasi.');
        }
        $url = $verifikasiUrl . '/' . $pengajuan->kode_verifikasi;

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
