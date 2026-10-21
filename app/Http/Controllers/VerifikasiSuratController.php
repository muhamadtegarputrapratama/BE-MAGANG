<?php

namespace App\Http\Controllers;

use App\Models\PengajuanMagang;

class VerifikasiSuratController extends Controller
{
    /** Dituju oleh QR di surat pengajuan. Publik, tanpa login, datanya sengaja minimal. */
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
                'departemen' => $pengajuan->user->departemen,

                'instansi'               => $pengajuan->instansi,
                'tanggal_ditandatangani' => $pengajuan->ditandatangani_at->toDateString(),
                'status'                 => $pengajuan->status->label(),
            ],
        ]);
    }
}