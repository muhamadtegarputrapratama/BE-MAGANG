<?php

namespace App\Services;

use App\Models\PengajuanMagang;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class SuratMagangService
{
    /**
     * Membuat PDF surat pengantar magang untuk instansi ke-$urutan (1 atau 2) dan menyimpannya.
     * Surat pertama memakai nama file lama (surat-magang.pdf) agar data lama tetap cocok.
     */
    public function simpan(PengajuanMagang $pengajuan, int $urutan = 1): string
    {
        $path = $urutan > 1
            ? "pengajuan/{$pengajuan->id}/surat-magang-{$urutan}.pdf"
            : "pengajuan/{$pengajuan->id}/surat-magang.pdf";

        $berhasil = Storage::disk('local')->put(
            $path,
            $this->render($pengajuan, $urutan)
        );

        if (! $berhasil) {
            throw new \RuntimeException(
                'Gagal menyimpan PDF surat pengantar magang.'
            );
        }

        return $path;
    }

    /**
     * Membuat PDF surat pengantar magang untuk instansi ke-$urutan.
     * Isi surat 1 memuat instansi 1, isi surat 2 memuat instansi 2.
     */
    public function render(PengajuanMagang $pengajuan, int $urutan = 1): string
    {
        $pengajuan->loadMissing('user');

        $penandatangan = config('magang.penandatangan');

        $instansi = $pengajuan->daftarInstansi()[$urutan - 1]
            ?? throw new \InvalidArgumentException("Instansi ke-{$urutan} tidak ditemukan.");

        $html = view('surat.suratmagang', [
            'logo' => $this->gambar(config('magang.logo_path')),
            'kop'  => config('magang.kop'),
            'ttd'  => $this->gambar($penandatangan['ttd_path'] ?? null),

            'fakultas'     => config('magang.fakultas'),
            'kota'         => config('magang.kota'),
            'nomor'        => $instansi['nomor'],
            'tanggalSurat' => $this->tanggal(
                $pengajuan->diverifikasi_at ?? now()
            ),

            // Pengaju nomor 1 + anggota tambahan (maks. 10 orang).
            'anggota' => $pengajuan->daftarAnggotaCetak(),

            // Data instansi untuk surat ini.
            'instansi'       => $instansi['nama'],
            'alamat'         => $instansi['alamat'],
            'tanggalMulai'   => $this->tanggal($instansi['mulai']),
            'tanggalSelesai' => $this->tanggal($instansi['selesai']),

            'jabatan'           => $penandatangan['jabatan'],
            'namaPenandatangan' => $penandatangan['nama'],
            'nip'               => $penandatangan['nip'],
        ])->render();

        $options = new Options();
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    /**
     * Format tanggal berbahasa Indonesia.
     * Contoh: 29 Oktober 2026.
     */
    private function tanggal($nilai): string
    {
        return Carbon::parse($nilai)
            ->locale('id')
            ->translatedFormat('j F Y');
    }

    /**
     * Mengubah gambar di folder public menjadi data URI.
     */
    private function gambar(?string $relatif): ?string
    {
        if (! $relatif) {
            return null;
        }

        $path = public_path($relatif);

        if (! is_file($path)) {
            return null;
        }

        $mime = mime_content_type($path);

        if (! in_array($mime, ['image/png', 'image/jpeg'], true)) {
            return null;
        }

        $konten = file_get_contents($path);

        if ($konten === false) {
            return null;
        }

        return 'data:' . $mime . ';base64,' . base64_encode($konten);
    }
}