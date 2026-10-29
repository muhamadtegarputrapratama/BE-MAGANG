<?php

namespace App\Services;

use App\Models\PengajuanMagang;
use App\Support\Departemen;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class SuratMagangService
{
    /** Membuat PDF surat pengantar magang dan menyimpannya. Mengembalikan path-nya. */
    public function simpan(PengajuanMagang $pengajuan): string
    {
        $path = "pengajuan/{$pengajuan->id}/surat-magang.pdf";

        Storage::disk('local')->put($path, $this->render($pengajuan));

        return $path;
    }

    public function render(PengajuanMagang $pengajuan): string
    {
        $pengajuan->loadMissing('user');
        $user = $pengajuan->user;
        $penandatangan = config('magang.penandatangan');

        $html = view('surat.suratmagang', [
            'logo' => $this->gambar(config('magang.logo_path')),
            'kop'  => config('magang.kop'),
            'ttd'  => $this->gambar($penandatangan['ttd_path'] ?? null),

            'fakultas'     => config('magang.fakultas'),
            'kota'         => config('magang.kota'),
            'nomor'        => $pengajuan->nomor_surat,
            'tanggalSurat' => $this->tanggal($pengajuan->diverifikasi_at ?? now()),

            'nama'       => $user->name,
            'nim'        => $user->nim,
            // Nama lengkap departemen (Silvikultur, bukan SVK); kode ditampilkan jika tidak dikenal.
            'departemen' => Departemen::namaDariKode($user->departemen) ?? $user->departemen,

            'instansi'       => $pengajuan->instansi,
            'alamat'         => $pengajuan->alamat_instansi,
            'tanggalMulai'   => $this->tanggal($pengajuan->tanggal_mulai),
            'tanggalSelesai' => $this->tanggal($pengajuan->tanggal_selesai),

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

    /** Tanggal berbahasa Indonesia, contoh: "29 Oktober 2026". */
    private function tanggal($nilai): string
    {
        return Carbon::parse($nilai)->locale('id')->translatedFormat('j F Y');
    }

    /** Mengubah gambar di folder public menjadi data URI. Null jika belum ada. */
    private function gambar(?string $relatif): ?string
    {
        if (! $relatif) {
            return null;
        }

        $path = public_path($relatif);

        if (! is_file($path)) {
            return null;
        }

        $mime = mime_content_type($path) ?: 'image/png';

        return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($path));
    }
}