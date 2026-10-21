<?php

namespace App\Services;

use App\Enums\StatusPengajuan;
use App\Models\PengajuanMagang;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class VerifikasiPengajuanService
{
    public function __construct(
        private NomorSuratService $nomorSurat,
        private SuratMagangService $suratMagang,
    ) {
    }

    /** Admin menyetujui: nomor surat dibuat, surat pengantar magang dibuat dan disimpan. */
    public function setujui(PengajuanMagang $pengajuan, User $admin): PengajuanMagang
    {
        $pathSurat = null;

        try {
            return DB::transaction(function () use ($pengajuan, $admin, &$pathSurat) {
                $p = $this->kunciDanPastikanMenunggu($pengajuan);

                $p->nomor_surat = $this->nomorSurat->berikutnya();
                $p->status = StatusPengajuan::Setuju;
                $p->diverifikasi_oleh = $admin->id;
                $p->diverifikasi_at = now();

                $pathSurat = $this->suratMagang->simpan($p);
                $p->file_surat_magang = $pathSurat;
                $p->save();

                return $p;
            });
        } catch (Throwable $e) {
            if ($pathSurat) {
                Storage::disk('local')->delete($pathSurat);
            }

            throw $e;
        }
    }

    /** Admin meminta revisi: catatan tersimpan, mahasiswa bisa memperbaiki. */
    public function mintaRevisi(PengajuanMagang $pengajuan, User $admin, string $catatan): PengajuanMagang
    {
        return DB::transaction(function () use ($pengajuan, $admin, $catatan) {
            $p = $this->kunciDanPastikanMenunggu($pengajuan);

            $p->status = StatusPengajuan::Revisi;
            $p->catatan_revisi = $catatan;
            $p->diverifikasi_oleh = $admin->id;
            $p->diverifikasi_at = now();
            $p->save();

            return $p;
        });
    }

    /** Ambil ulang barisnya dengan kunci, supaya dua admin tidak memproses pengajuan yang sama. */
    private function kunciDanPastikanMenunggu(PengajuanMagang $pengajuan): PengajuanMagang
    {
        $p = PengajuanMagang::whereKey($pengajuan->id)->lockForUpdate()->firstOrFail();

        if ($p->status !== StatusPengajuan::MenungguVerifikasi) {
            throw ValidationException::withMessages([
                'status' => 'Hanya pengajuan berstatus menunggu verifikasi yang bisa diproses.',
            ]);
        }

        return $p;
    }
}