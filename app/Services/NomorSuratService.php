<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class NomorSuratService
{
    /**
     * Membuat nomor surat berikutnya, aman dari bentrok (baris dikunci selama transaksi).
     * Dipanggil di dalam transaksi persetujuan, jadi nomor tidak "terbuang" kalau proses gagal.
     */
    public function berikutnya(?int $tahun = null): string
    {
        $tahun ??= now()->year;
        $awal = max((int) config('magang.nomor_surat.mulai_dari', 1), 1);

        return DB::transaction(function () use ($tahun, $awal) {
            DB::table('nomor_surat_urut')->insertOrIgnore([
                'tahun'    => $tahun,
                'terakhir' => $awal - 1,
            ]);

            $baris = DB::table('nomor_surat_urut')
                ->where('tahun', $tahun)
                ->lockForUpdate()
                ->first();

            $urut = $baris->terakhir + 1;

            DB::table('nomor_surat_urut')
                ->where('tahun', $tahun)
                ->update(['terakhir' => $urut]);

            return str_replace(
                ['{urut}', '{tahun}'],
                [$urut, $tahun],
                config('magang.nomor_surat.format'),
            );
        });
    }
}