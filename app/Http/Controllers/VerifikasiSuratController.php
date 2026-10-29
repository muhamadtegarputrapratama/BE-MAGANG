<?php

namespace App\Http\Controllers;

use App\Models\PengajuanMagang;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use App\Support\Departemen;

class VerifikasiSuratController extends Controller
{
    /** Dituju oleh QR. Menampilkan PDF surat pengajuan itu sendiri. Publik, tanpa login. */
    public function surat(string $kode)
    {
        $pengajuan = PengajuanMagang::where('kode_verifikasi', $kode)
            ->whereNotNull('ditandatangani_at')
            ->first();

        abort_if(
            ! $pengajuan || ! $pengajuan->file_surat_pengajuan,
            404,
            'Surat tidak ditemukan atau tanda tangan sudah dibatalkan.'
        );

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('local');

        abort_if(! $disk->exists($pengajuan->file_surat_pengajuan), 404, 'File surat tidak ditemukan.');

        // tampil langsung di browser (inline)
        return $disk->response($pengajuan->file_surat_pengajuan, 'surat-pengajuan.pdf', [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /** Versi data (JSON) untuk kebutuhan aplikasi, datanya sengaja minimal. */
    public function show(string $kode)
    {
        $pengajuan = PengajuanMagang::with('user')
            ->where('kode_verifikasi', $kode)
            ->whereNotNull('ditandatangani_at')
            ->first();

        if (! $pengajuan) {
            return response()->json([
                'valid'   => false,
                'message' => 'Surat tidak ditemukan atau tidak valid.',
            ], 404);
        }

        return response()->json([
            'valid' => true,
            'data'  => [
                // SESUAIKAN dengan nama kolom di tabel users milik temanmu
                'nama'       => $pengajuan->user->name,
                'nim'        => $pengajuan->user->nim,
                'departemen' => Departemen::nama($pengajuan->user->departemen),

                'instansi'               => $pengajuan->instansi,
                'tanggal_ditandatangani' => $pengajuan->ditandatangani_at->toDateString(),
                'status'                 => $pengajuan->status->label(),
            ],
        ]);
    }
}
