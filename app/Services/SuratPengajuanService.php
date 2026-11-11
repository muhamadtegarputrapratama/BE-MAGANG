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

public function render(PengajuanMagang $pengajuan, bool $denganQr): string
{
    $pengajuan->loadMissing('user');

    $user = $pengajuan->user;

    if (!$user) {
        throw new \RuntimeException(
            'Data mahasiswa pemilik pengajuan tidak ditemukan.'
        );
    }

    // Ambil anggota yang tersimpan di database.
    $anggota = $pengajuan->anggota ?? [];

    // Jika belum ada anggota, gunakan data mahasiswa pemilik pengajuan.
    if (empty($anggota)) {
        $anggota = [
            [
                'nama' => $user->name,
                'nim' => $user->nim,
                'departemen' => $user->departemen,
            ],
        ];
    }

    $html = view('surat.pengajuan', [
        'anggota' => $anggota,
        'nama' => $user->name,
        'nim' => $user->nim,
        'departemen' => $user->departemen,

        'fakultas' => config('magang.fakultas'),
        'kota' => config('magang.kota'),

        'instansi' => $pengajuan->instansi,
        'alamat' => $pengajuan->alamat_instansi,

        'tanggalMulai' => $this->tanggal($pengajuan->tanggal_mulai),
        'tanggalSelesai' => $this->tanggal($pengajuan->tanggal_selesai),

        'tanggalSurat' => $this->tanggal(
            $pengajuan->ditandatangani_at ?? now()
        ),

        'qr' => $denganQr ? $this->buatQr($pengajuan) : null,
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
