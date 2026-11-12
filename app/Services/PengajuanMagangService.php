<?php

namespace App\Services;

use App\Enums\StatusPengajuan;
use App\Models\PengajuanMagang;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use App\Support\Departemen;
use Throwable;

class PengajuanMagangService
{
    /**
     * Singkatan yang selalu ditulis KAPITAL pada nama instansi dan alamat
     * (mis. "pt" atau "Pt" menjadi "PT"). Tambahan bisa dibuat di config/magang.php
     * lewat kunci 'singkatan_instansi' (array string) tanpa mengubah kode ini.
     */
    private const SINGKATAN = [
        'pt', 'cv', 'ud', 'pd', 'bumn', 'bumd', 'upt', 'uptd',
        'kph', 'ksda', 'bksda', 'bbksda', 'bpdas', 'bpdashl', 'bpkh', 'bpth',
        'tn', 'tni', 'polri', 'klhk', 'klh', 'brin', 'lipi', 'ipb', 'ui', 'ugm', 'itb', 'lsm',
        'dki', 'diy', 'ntt', 'ntb', 'ri', 'rt', 'rw', 'km',
        'ii', 'iii', 'iv', 'vi', 'vii', 'viii', 'ix',
    ];

    public function __construct(private SuratPengajuanService $surat) {}

    public function buatDraft(User $user, array $data): PengajuanMagang
    {
        $pengajuan = new PengajuanMagang();

        $pengajuan->instansi = $this->formatInstansi($data['instansi']);
        $pengajuan->alamat_instansi = $this->formatInstansi($data['alamat_instansi']);
        $pengajuan->tanggal_mulai = $data['tanggal_mulai'];
        $pengajuan->tanggal_selesai = $data['tanggal_selesai'];

        // Instansi kedua (opsional) -> surat kedua.
        $this->isiInstansiKedua($pengajuan, $data);

        $pengajuan->user_id = $user->id;
        $pengajuan->nama = $user->name;

        // Simpan ketua dan anggota kelompok.
        $pengajuan->anggota = $this->susunAnggota(
            $user,
            $data['anggota'] ?? []
        );

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

        // Surat final instansi 1 dan 2 (bila ada) akan dibatalkan.
        $fileLama = [
            $pengajuan->file_surat_pengajuan,
            $pengajuan->file_surat_pengajuan_kedua,
        ];

        $pengajuan->instansi = $this->formatInstansi($data['instansi']);
        $pengajuan->alamat_instansi = $this->formatInstansi($data['alamat_instansi']);
        $pengajuan->tanggal_mulai = $data['tanggal_mulai'];
        $pengajuan->tanggal_selesai = $data['tanggal_selesai'];

        // Instansi kedua hanya disentuh jika dikirim (null/kosong berarti dihapus).
        if (array_key_exists('instansi_kedua', $data)) {
            $this->isiInstansiKedua($pengajuan, $data);
        }

        // Perbarui anggota jika dikirim.
        if (array_key_exists('anggota', $data)) {
            $pengajuan->loadMissing('user');

            $pengajuan->anggota = $this->susunAnggota(
                $pengajuan->user,
                $data['anggota'] ?? []
            );
        }

        // Batalkan tanda tangan sebelumnya.
        $pengajuan->ditandatangani_at = null;
        $pengajuan->file_surat_pengajuan = null;
        $pengajuan->file_surat_pengajuan_kedua = null;

        $pengajuan->save();

        foreach ($fileLama as $path) {
            $this->hapusFile($path);
        }

        return $pengajuan->refresh();
    }

    /**
     * Tahap 2: preview surat untuk instansi ke-$urutan (1 atau 2).
     * Kalau sudah ditandatangani, tampilkan versi final.
     */
    public function previewSurat(PengajuanMagang $pengajuan, int $urutan = 1): string
    {
        abort_if(
            $urutan < 1 || $urutan > $pengajuan->jumlahInstansi(),
            404,
            'Surat untuk instansi tersebut tidak ada.'
        );

        if ($pengajuan->sudahDitandatangani()) {
            $path = $pengajuan->fileSuratPengajuan($urutan);
            $disk = Storage::disk('local');

            // Pastikan file surat yang ditandatangani masih tersedia.
            abort_unless(
                $path && $disk->exists($path),
                404,
                'File surat yang ditandatangani tidak ditemukan.'
            );

            return $disk->get($path);
        }

        // Jika belum ditandatangani, tampilkan preview tanpa QR.
        return $this->surat->render($pengajuan, denganQr: false, urutan: $urutan);
    }

    /**
     * Tahap 2b: tanda tangan. Surat final dengan QR dibuat dan disimpan,
     * satu PDF untuk tiap instansi (1 atau 2 surat).
     */
    public function tandaTangani(
        PengajuanMagang $pengajuan
    ): PengajuanMagang {
        $this->pastikanBisaDiedit($pengajuan);

        if ($pengajuan->sudahDitandatangani()) {
            return $pengajuan;
        }

        $pathBaru = [];

        try {
            // Atur timestamp agar data surat yang dirender sesuai.
            $pengajuan->ditandatangani_at = now();

            foreach ($pengajuan->daftarInstansi() as $instansi) {
                $path = $this->surat->simpan($pengajuan, $instansi['urutan']);

                if (! $path) {
                    throw new \RuntimeException('Gagal menyimpan surat pengajuan.');
                }

                $pathBaru[$instansi['urutan']] = $path;
            }

            $pengajuan->file_surat_pengajuan = $pathBaru[1];
            $pengajuan->file_surat_pengajuan_kedua = $pathBaru[2] ?? null;
            $pengajuan->save();

            return $pengajuan->refresh();
        } catch (Throwable $e) {
            // Bersihkan file baru jika proses gagal.
            foreach ($pathBaru as $path) {
                $this->hapusFile($path);
            }

            throw $e;
        }
    }

    /** Membatalkan tanda tangan: semua surat final dan QR dihapus, preview kembali tanpa QR. */
    public function batalkanTandaTangan(
        PengajuanMagang $pengajuan
    ): PengajuanMagang {
        $this->pastikanBisaDiedit($pengajuan);

        if (! $pengajuan->sudahDitandatangani()) {
            return $pengajuan;
        }

        // Simpan path file lama sebelum mengubah data.
        $fileLama = [
            $pengajuan->file_surat_pengajuan,
            $pengajuan->file_surat_pengajuan_kedua,
        ];

        // Hapus referensi tanda tangan dari database.
        $pengajuan->ditandatangani_at = null;
        $pengajuan->file_surat_pengajuan = null;
        $pengajuan->file_surat_pengajuan_kedua = null;
        $pengajuan->save();

        // Hapus PDF lama setelah database berhasil disimpan.
        foreach ($fileLama as $path) {
            $this->hapusFile($path);
        }

        return $pengajuan->refresh();
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

    /**
     * Instansi kedua: terisi bila nama instansi dikirim, selain itu semua kolomnya dikosongkan.
     */
    private function isiInstansiKedua(PengajuanMagang $pengajuan, array $data): void
    {
        if (filled($data['instansi_kedua'] ?? null)) {
            $pengajuan->instansi_kedua = $this->formatInstansi($data['instansi_kedua']);
            $pengajuan->alamat_instansi_kedua = $this->formatInstansi($data['alamat_instansi_kedua']);
            $pengajuan->tanggal_mulai_kedua = $data['tanggal_mulai_kedua'];
            $pengajuan->tanggal_selesai_kedua = $data['tanggal_selesai_kedua'];

            return;
        }

        $pengajuan->instansi_kedua = null;
        $pengajuan->alamat_instansi_kedua = null;
        $pengajuan->tanggal_mulai_kedua = null;
        $pengajuan->tanggal_selesai_kedua = null;
    }

    /**
     * Menyusun daftar kelompok: ketua selalu di urutan pertama, lalu anggota tambahan.
     * Total dibatasi config('magang.maks_anggota') (default 10, termasuk ketua).
     */
    private function susunAnggota(User $user, ?array $anggotaTambahan): array
    {
        $nimKetua = strtoupper((string) $user->nim);

        $anggota = [
            [
                'user_id' => $user->id,
                'nama' => $user->name,
                'nim' => $user->nim,
                'departemen' => $user->departemen,
            ],
        ];

        foreach ($anggotaTambahan ?? [] as $member) {
            $nim = strtoupper(trim($member['nim']));

            if ($nim === $nimKetua) {
                throw ValidationException::withMessages([
                    'anggota' => 'NIM ketua kelompok tidak perlu dimasukkan kembali.',
                ]);
            }

            // Ambil departemen otomatis berdasarkan NIM
            $departemen = Departemen::dariNim($nim);

            if (! $departemen) {
                throw ValidationException::withMessages([
                    "anggota" => "NIM {$nim} memiliki kode departemen yang tidak dikenal.",
                ]);
            }

            $anggota[] = [
                'user_id' => null,
                'nama' => $this->formatNama($member['nama']),
                'nim' => $nim,
                'departemen' => $departemen['kode'],
            ];
        }

        $maks = (int) config('magang.maks_anggota', 10);

        if (count($anggota) > $maks) {
            throw ValidationException::withMessages([
                'anggota' => "Maksimal {$maks} mahasiswa per pengajuan (termasuk ketua).",
            ]);
        }

        return $anggota;
    }

    /**
     * Nama instansi / alamat: tiap kata diawali huruf kapital, dan singkatan
     * (PT, CV, KPH, KM, RT, dst.) ditulis kapital penuh.
     */
    private function formatInstansi(string $value): string
    {
        $value = $this->formatNama($value);

        $singkatan = array_unique(array_map(
            'mb_strtolower',
            array_merge(self::SINGKATAN, (array) config('magang.singkatan_instansi', []))
        ));

        $pola = '/\b(' . implode('|', array_map(
            fn ($kata) => preg_quote($kata, '/'),
            $singkatan
        )) . ')\b/iu';

        return preg_replace_callback(
            $pola,
            fn ($cocok) => mb_strtoupper($cocok[1]),
            $value
        );
    }

    private function formatNama(string $value): string
    {
        $value = mb_strtolower(trim($value));

        return preg_replace_callback(
            "/(^|[\s'-])([\p{L}])/u",
            fn ($matches) => $matches[1] . mb_strtoupper($matches[2]),
            $value
        );
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