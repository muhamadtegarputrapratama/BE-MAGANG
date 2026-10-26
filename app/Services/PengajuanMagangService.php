<?php

namespace App\Services;

use App\Enums\StatusPengajuan;
use App\Models\PengajuanMagang;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use Throwable;

class PengajuanMagangService
{
    public function __construct(private SuratPengajuanService $surat) {}

    public function buatDraft(User $user, array $data): PengajuanMagang
    {
        $pengajuan = new PengajuanMagang();


        $pengajuan->instansi = $data['instansi'];
        $pengajuan->alamat_instansi = $data['alamat_instansi'];
        $pengajuan->tanggal_mulai = $data['tanggal_mulai'];
        $pengajuan->tanggal_selesai = $data['tanggal_selesai'];


        $pengajuan->user_id = $user->id;
        $pengajuan->nama = $user->name;
        $pengajuan->status = StatusPengajuan::Draft;
        $pengajuan->kode_verifikasi = Str::random(32);

        $pengajuan->save();

        return $pengajuan;
    }

    public function perbarui(
        PengajuanMagang $pengajuan,
        array $data
    ): PengajuanMagang {
        $this->pastikanBisaDiedit($pengajuan);

        $this->hapusFile($pengajuan->file_surat_pengajuan);

        // Hanya data form yang boleh diperbarui.
        $pengajuan->instansi = $data['instansi'];
        $pengajuan->alamat_instansi = $data['alamat_instansi'];
        $pengajuan->tanggal_mulai = $data['tanggal_mulai'];
        $pengajuan->tanggal_selesai = $data['tanggal_selesai'];

        // Perubahan data membatalkan tanda tangan sebelumnya.
        $pengajuan->ditandatangani_at = null;
        $pengajuan->file_surat_pengajuan = null;

        $pengajuan->save();

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

    /** Membatalkan tanda tangan: surat final dan QR dihapus, preview kembali tanpa QR. */
    public function batalkanTandaTangan(PengajuanMagang $pengajuan): PengajuanMagang
    {
        $this->pastikanBisaDiedit($pengajuan);

        if (! $pengajuan->sudahDitandatangani()) {
            return $pengajuan;
        }

        $this->hapusFile($pengajuan->file_surat_pengajuan);

        $pengajuan->ditandatangani_at = null;
        $pengajuan->file_surat_pengajuan = null;
        $pengajuan->save();

        return $pengajuan;
    }

   public function kirim(
    PengajuanMagang $pengajuan,
    ?UploadedFile $buktiSpp,
    ?UploadedFile $suratRekomendasi
): PengajuanMagang {
    $this->pastikanBisaDiedit($pengajuan);

    if (! $pengajuan->sudahDitandatangani()) {
        throw ValidationException::withMessages([
            'tanda_tangan' => 'Surat pengajuan harus ditandatangani terlebih dahulu.',
        ]);
    }

    if (
        (! $buktiSpp && ! $pengajuan->file_bukti_spp) ||
        (! $suratRekomendasi && ! $pengajuan->file_surat_rekomendasi)
    ) {
        throw ValidationException::withMessages([
            'berkas' => 'Bukti SPP dan surat rekomendasi wajib tersedia.',
        ]);
    }

    $folder = "pengajuan/{$pengajuan->id}";
    $berkasBaru = [];
    $berkasLama = [
        $pengajuan->file_bukti_spp,
        $pengajuan->file_surat_rekomendasi,
    ];

    try {
        // Simpan berkas baru tanpa menghapus berkas lama.
        if ($buktiSpp) {
            $path = $buktiSpp->store($folder, 'local');

            if (! $path) {
                throw new \RuntimeException('Gagal menyimpan bukti SPP.');
            }

            $berkasBaru['file_bukti_spp'] = $path;
        }

        if ($suratRekomendasi) {
            $path = $suratRekomendasi->store($folder, 'local');

            if (! $path) {
                throw new \RuntimeException('Gagal menyimpan surat rekomendasi.');
            }

            $berkasBaru['file_surat_rekomendasi'] = $path;
        }

        // Perbarui database dalam transaksi.
        DB::transaction(function () use ($pengajuan, $berkasBaru) {
            foreach ($berkasBaru as $kolom => $path) {
                $pengajuan->{$kolom} = $path;
            }

            $pengajuan->status = StatusPengajuan::MenungguVerifikasi;
            $pengajuan->dikirim_at = now();
            $pengajuan->save();
        });
    } catch (Throwable $e) {
        // Jika proses gagal, bersihkan berkas baru yang sempat tersimpan.
        foreach ($berkasBaru as $path) {
            $this->hapusFile($path);
        }

        throw $e;
    }

    // Hapus berkas lama hanya setelah database berhasil diperbarui.
    foreach ($berkasBaru as $kolom => $path) {
        $pathLama = match ($kolom) {
            'file_bukti_spp' => $berkasLama[0],
            'file_surat_rekomendasi' => $berkasLama[1],
            default => null,
        };

        if ($pathLama && $pathLama !== $path) {
            $this->hapusFile($pathLama);
        }
    }

    return $pengajuan->refresh();
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
