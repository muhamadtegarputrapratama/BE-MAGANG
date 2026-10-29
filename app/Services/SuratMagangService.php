<?php

namespace App\Services;

use App\Models\PengajuanMagang;
use Dompdf\Dompdf;
use Dompdf\Options;
use App\Support\Departemen;
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
            'tanggalSurat' => ($pengajuan->diverifikasi_at ?? now())->translatedFormat('j F Y'),

            'nama'       => $user->name,
            'nim'        => $user->nim,
            'departemen' => Departemen::nama($user->departemen),

            'instansi'       => $pengajuan->instansi,
            'alamat'         => $pengajuan->alamat_instansi,
            'tanggalMulai'   => $pengajuan->tanggal_mulai->translatedFormat('j F Y'),
            'tanggalSelesai' => $pengajuan->tanggal_selesai->translatedFormat('j F Y'),

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
