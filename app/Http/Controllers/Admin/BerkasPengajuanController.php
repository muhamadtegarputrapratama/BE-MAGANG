<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StatusPengajuan;
use App\Http\Controllers\Controller;
use App\Models\PengajuanMagang;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BerkasPengajuanController extends Controller
{
    /** jenis di URL => kolom di tabel */
    private const BERKAS = [
        'surat-pengajuan'   => 'file_surat_pengajuan',
        'bukti-spp'         => 'file_bukti_spp',
        'surat-rekomendasi' => 'file_surat_rekomendasi',
        'surat-magang'      => 'file_surat_magang',
    ];

    public function tampil(Request $request, PengajuanMagang $pengajuan, string $jenis)
    {
        $mode = $request->query('mode', 'preview');

        // Kompatibel dengan link bentuk lama: /berkas/download?nama=surat-magang
        if (in_array($jenis, ['preview', 'download'], true)) {
            $mode  = $jenis;
            $jenis = (string) $request->query('nama', $request->query('jenis', ''));
        }

        abort_unless(isset(self::BERKAS[$jenis]), 404, "Jenis berkas tidak dikenal: {$jenis}");
        abort_if($pengajuan->status === StatusPengajuan::Draft, 404, 'Pengajuan belum dikirim.');

        $path = $pengajuan->{self::BERKAS[$jenis]};

        abort_if(! $path, 404, 'Berkas belum tersedia.');

        $lokasi = $this->cariFile($path);

        abort_if(! $lokasi, 404, "File tidak ditemukan di penyimpanan: {$path}");

        [$disk, $pathAsli] = $lokasi;

        $ekstensi = pathinfo($pathAsli, PATHINFO_EXTENSION);
        $nama     = "{$jenis}-{$pengajuan->id}.{$ekstensi}";

        return $mode === 'download'
            ? $disk->download($pathAsli, $nama)
            : $disk->response($pathAsli, $nama);   // tampil langsung di browser (inline)
    }

    /**
     * Mencari file di disk privat dulu, lalu disk public (untuk data lama).
     * Awalan "public/" atau "storage/" pada path lama ikut dicoba tanpa awalan itu.
     *
     * @return array{0: FilesystemAdapter, 1: string}|null
     */
    private function cariFile(string $path): ?array
    {
        $kandidat = array_values(array_unique([
            $path,
            ltrim((string) preg_replace('#^(public/|storage/)#', '', $path), '/'),
        ]));

        foreach (['local', 'public'] as $namaDisk) {
            /** @var FilesystemAdapter $disk */
            $disk = Storage::disk($namaDisk);

            foreach ($kandidat as $p) {
                if ($disk->exists($p)) {
                    return [$disk, $p];
                }
            }
        }

        return null;
    }
}