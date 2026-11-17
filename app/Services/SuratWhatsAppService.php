<?php

namespace App\Services;

use App\Enums\StatusPengajuan;
use App\Models\PengajuanMagang;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;

class SuratWhatsAppService
{
    /**
     * Membuat link unduh sementara dan URL wa.me untuk satu surat magang.
     * Tidak mengirim apa pun: admin sendiri yang menekan "kirim" di WhatsApp.
     *
     * @return array<string, mixed>
     */
    public function buat(PengajuanMagang $pengajuan, int $urutan): array
    {
        $pengajuan->loadMissing('user');

        if ($pengajuan->status !== StatusPengajuan::Setuju) {
            $this->tolak('status', 'Surat hanya bisa dikirim setelah pengajuan disetujui.');
        }

        $instansi = collect($pengajuan->daftarInstansi())->firstWhere('urutan', $urutan);

        if ($instansi === null) {
            $this->tolak('urutan', "Pengajuan ini tidak memiliki instansi ke-{$urutan}.");
        }

        $path = $pengajuan->fileSuratMagang($urutan);

        if (blank($path)) {
            $this->tolak('urutan', 'Surat magang untuk instansi ini belum tersedia.');
        }

        if (! Storage::disk('local')->exists($path)) {
            $this->tolak('berkas', 'File surat tidak ditemukan di penyimpanan.');
        }

        $noWa = $pengajuan->user?->no_wa;

        if (blank($noWa)) {
            $this->tolak('no_wa', 'Mahasiswa belum memiliki nomor WhatsApp.');
        }

        $nomorTujuan = $this->normalisasiNomor($noWa);

        if ($nomorTujuan === null) {
            $this->tolak('no_wa', 'Format nomor WhatsApp mahasiswa tidak valid.');
        }

        // Masa berlaku link (menit). Default 3 hari; bisa diatur di config/magang.php.
        $menit = max(1, (int) config('magang.link_surat_berlaku_menit', 4320));
        $berlakuSampai = now()->addMinutes($menit);

        $url = URL::temporarySignedRoute('surat-magang.unduh', $berlakuSampai, [
            'pengajuan' => $pengajuan->id,
            'urutan'    => $urutan,
        ]);

        $pesan = $this->susunPesan($pengajuan, $instansi, $url, $berlakuSampai);

        return [
            'urutan'         => $urutan,
            'instansi'       => $instansi['nama'],
            'nomor_surat'    => $instansi['nomor'],
            'nomor_tujuan'   => $nomorTujuan,
            'pesan'          => $pesan,
            'download_url'   => $url,
            'berlaku_sampai' => $berlakuSampai->toIso8601String(),
            'whatsapp_url'   => 'https://wa.me/' . $nomorTujuan . '?text=' . rawurlencode($pesan),
        ];
    }

    /**
     * Mengubah 08xx / +62 8xx / 62-8xx / 8xx menjadi 628xx (format wa.me, tanpa "+").
     * Mengembalikan null bila tidak bisa dikenali sebagai nomor Indonesia.
     */
    public function normalisasiNomor(?string $nomor): ?string
    {
        $digit = preg_replace('/\D+/', '', (string) $nomor); // buang spasi, +, -, kurung

        if ($digit === '') {
            return null;
        }

        if (str_starts_with($digit, '00')) {
            $digit = substr($digit, 2); // 0062... -> 62...
        }

        if (str_starts_with($digit, '62')) {
            // sudah berformat internasional
        } elseif (str_starts_with($digit, '0')) {
            $digit = '62' . substr($digit, 1);
        } elseif (str_starts_with($digit, '8')) {
            $digit = '62' . $digit;
        } else {
            return null;
        }

        // Salah ketik "+62 0812..." -> buang 0 setelah kode negara
        if (str_starts_with($digit, '620')) {
            $digit = '62' . substr($digit, 3);
        }

        return preg_match('/^62\d{8,13}$/', $digit) ? $digit : null;
    }

        private function susunPesan(
        PengajuanMagang $pengajuan,
        array $instansi,
        string $url,
        CarbonInterface $berlakuSampai
    ): string {
        $batas = $berlakuSampai->copy()
            ->timezone('Asia/Jakarta')
            ->locale('id')
            ->translatedFormat('l, j F Y \p\u\k\u\l H.i');

        $baris = [
            $this->sapaanWaktu() . ", {$pengajuan->user->name}.",
            '',
            'Kami dari Admin Fakultas Kehutanan dan Lingkungan IPB menginformasikan bahwa '
                . 'pengajuan magang Anda telah *DISETUJUI* dan Surat Pengantar Magang telah diterbitkan.',
            '',
            '*Rincian surat:*',
            "Instansi tujuan: {$instansi['nama']}",
        ];

        if (filled($instansi['nomor'])) {
            $baris[] = "Nomor surat: {$instansi['nomor']}";
        }

        if ($instansi['mulai'] && $instansi['selesai']) {
            $baris[] = 'Periode magang: ' . $this->tanggal($instansi['mulai'])
                . ' s.d. ' . $this->tanggal($instansi['selesai']);
        }

        $baris = array_merge($baris, [
            '',
            'Silakan unduh Surat Pengantar Magang melalui tautan berikut:',
            $url,
            '',
            "Tautan berlaku sampai {$batas} WIB. Setelah melewati batas waktu tersebut, "
                . 'tautan tidak dapat dibuka lagi. Mohon segera mengunduh dan menyimpan surat.',
            '',
            '*Catatan penting:*',
            '1. Tautan ini bersifat pribadi, mohon tidak dibagikan kepada pihak lain.',
            '2. Periksa kembali nama, NIM, dan data instansi pada surat. Jika ada yang tidak sesuai, '
                . 'segera hubungi kami melalui chat ini.',
            '3. Surat dapat dicetak dan diserahkan kepada instansi tujuan sesuai ketentuan instansi.',
            '',
            'Demikian informasi ini kami sampaikan. Atas perhatian dan kerja samanya, kami ucapkan terima kasih.',
            '',
            'Hormat kami,',
            'Admin',
            'Fakultas Kehutanan dan Lingkungan',
            'Institut Pertanian Bogor',
        ]);

        return implode("\n", $baris);
    }

    /** Sapaan sesuai jam WIB: pagi 04.00-10.59, siang 11.00-14.59, sore 15.00-17.59, malam 18.00-03.59. */
    private function sapaanWaktu(?CarbonInterface $waktu = null): string
    {
        $jam = ($waktu ?? now())->copy()->timezone('Asia/Jakarta')->hour;

        return match (true) {
            $jam >= 4 && $jam < 11  => 'Selamat pagi',
            $jam >= 11 && $jam < 15 => 'Selamat siang',
            $jam >= 15 && $jam < 18 => 'Selamat sore',
            default                 => 'Selamat malam',
        };
    }

    private function tanggal(CarbonInterface $tanggal): string
    {
        return $tanggal->copy()->locale('id')->translatedFormat('j F Y');
    }

    private function tolak(string $kolom, string $pesan): never
    {
        throw ValidationException::withMessages([$kolom => $pesan]);
    }
}
