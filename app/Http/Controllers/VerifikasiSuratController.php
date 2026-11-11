<?php

namespace App\Http\Controllers;

use App\Models\PengajuanMagang;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Support\Departemen;

class VerifikasiSuratController extends Controller
{
    /**
     * Dituju oleh QR. Menampilkan PDF surat pengajuan itu sendiri. Publik, tanpa login.
     * Surat instansi kedua memakai ?urutan=2 (QR-nya sudah membawa parameter itu).
     */
    public function surat(Request $request, string $kode)
    {
        $pengajuan = PengajuanMagang::where('kode_verifikasi', $kode)
            ->whereNotNull('ditandatangani_at')
            ->first();

        $file = $pengajuan?->fileSuratPengajuan((int) $request->query('urutan', 1));

        abort_if(
            ! $pengajuan || ! $file,
            404,
            'Surat tidak ditemukan atau tanda tangan sudah dibatalkan.'
        );

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('local');

        abort_if(! $disk->exists($file), 404, 'File surat tidak ditemukan.');

        // tampil langsung di browser (inline)
        return $disk->response($file, 'surat-pengajuan.pdf', [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /** Versi data (JSON) untuk kebutuhan aplikasi, datanya sengaja minimal. */
    public function show(Request $request, string $kode)
    {
        $pengajuan = PengajuanMagang::with('user')
            ->where('kode_verifikasi', $kode)
            ->whereNotNull('ditandatangani_at')
            ->first();

        $urutan   = (int) $request->query('urutan', 1);
        $instansi = $pengajuan?->daftarInstansi()[$urutan - 1] ?? null;

        if (! $pengajuan || ! $instansi) {
            return response()->json([
                'valid'   => false,
                'message' => 'Surat tidak ditemukan atau tidak valid.',
            ], 404);
        }

        return response()->json([
            'valid' => true,
            'data'  => [
                // SESUAIKAN dengan nama kolom di tabel users milik temanmu
                'nama'       => $pengajuan->user?->name ?? $pengajuan->nama,
                'nim'        => $pengajuan->user?->nim,
                'departemen' => Departemen::nama($pengajuan->user?->departemen),

                'instansi'               => $instansi['nama'],
                'tanggal_ditandatangani' => $pengajuan->ditandatangani_at?->toDateString(),
                'status'                 => $pengajuan->status?->label(),
            ],
        ]);
    }
}