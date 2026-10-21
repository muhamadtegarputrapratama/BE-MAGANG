<?php

namespace App\Services;

use App\Enums\StatusPengajuan;
use App\Models\PengajuanMagang;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PengajuanMagangService
{
    public function __construct(private SuratPengajuanService $surat)
    {
    }

    /** Tahap 1: simpan data form sebagai draft (belum terlihat admin). */
    public function buatDraft(User $user, array $data): PengajuanMagang
    {
        return PengajuanMagang::create([
            ...$data,
            'user_id'         => $user->id,
            'status'          => StatusPengajuan::Draft,
            'kode_verifikasi' => Str::random(32),
        ]);
    }

    /** Ubah data form. Tanda tangan dibatalkan karena isi surat berubah. */
    public function perbarui(PengajuanMagang $pengajuan, array $data): PengajuanMagang
    {
        $this->pastikanBisaDiedit($pengajuan);

        $this->hapusFile($pengajuan->file_surat_pengajuan);

        $pengajuan->update([
            ...$data,
            'ditandatangani_at'    => null,
            'file_surat_pengajuan' => null,
        ]);

        return $pengajuan;
    }

    /** Tahap 2: preview surat. Kalau sudah ditandatangani, tampilkan versi final. */
    public function previewSurat(PengajuanMagang $pengajuan): string
    {
        if ($pengajuan->sudahDitandatangani()) {
            return Storage::disk('local')->get($pengajuan->file_surat_pengajuan);
        }

        return $this->surat->render($pengajuan, denganQr: false);
    }

    /** Tahap 2b: tanda tangan, yaitu surat final dengan QR dibuat dan disimpan. */
    public function tandaTangani(PengajuanMagang $pengajuan): PengajuanMagang
    {
        $this->pastikanBisaDiedit($pengajuan);

        if ($pengajuan->sudahDitandatangani()) {
            return $pengajuan;
        }

        $pengajuan->ditandatangani_at = now();
        $pengajuan->file_surat_pengajuan = $this->surat->simpan($pengajuan);
        $pengajuan->save();

        return $pengajuan;
    }

    /** Tahap 3-4: simpan dua file, lalu kirim ke admin. */
    public function kirim(
        PengajuanMagang $pengajuan,
        ?UploadedFile $buktiSpp,
        ?UploadedFile $suratRekomendasi,
    ): PengajuanMagang {
        $this->pastikanBisaDiedit($pengajuan);

        if (! $pengajuan->sudahDitandatangani()) {
            throw ValidationException::withMessages([
                'tanda_tangan' => 'Surat pengajuan harus ditandatangani terlebih dahulu.',
            ]);
        }

        if (! ($buktiSpp || $pengajuan->file_bukti_spp) || ! ($suratRekomendasi || $pengajuan->file_surat_rekomendasi)) {
            throw ValidationException::withMessages([
                'berkas' => 'Bukti SPP dan surat rekomendasi wajib diupload.',
            ]);
        }

        $folder = "pengajuan/{$pengajuan->id}";
        $baru = [];
        $lama = [];

        if ($buktiSpp) {
            $lama[] = $pengajuan->file_bukti_spp;
            $baru['file_bukti_spp'] = $buktiSpp->store($folder, 'local');
        }

        if ($suratRekomendasi) {
            $lama[] = $pengajuan->file_surat_rekomendasi;
            $baru['file_surat_rekomendasi'] = $suratRekomendasi->store($folder, 'local');
        }

        $pengajuan->update([
            ...$baru,
            'status'     => StatusPengajuan::MenungguVerifikasi,
            'dikirim_at' => now(),
        ]);

        foreach ($lama as $path) {
            $this->hapusFile($path);
        }

        return $pengajuan;
    }

    private function pastikanBisaDiedit(PengajuanMagang $pengajuan): void
    {
        if (! $pengajuan->bisaDiedit()) {
            throw ValidationException::withMessages([
                'status' => 'Pengajuan sudah dikirim dan tidak bisa diubah.',
            ]);
        }
    }

    private function hapusFile(?string $path): void
    {
        if ($path) {
            Storage::disk('local')->delete($path);
        }
    }
}