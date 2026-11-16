<?php

namespace App\Services;

use App\Enums\StatusPengajuan;
use App\Models\PengajuanMagang;
use App\Models\User;
use App\Support\Departemen;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
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

    /* =========================================================
       MAHASISWA
    ========================================================= */

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
        $pengajuan->anggota = $this->susunAnggota($user, $data['anggota'] ?? []);
        $pengajuan->status = StatusPengajuan::Draft;
        $pengajuan->kode_verifikasi = Str::random(32);

        $pengajuan->save();

        return $pengajuan;
    }

    public function perbarui(PengajuanMagang $pengajuan, array $data): PengajuanMagang
    {
        $this->pastikanBisaDiedit($pengajuan);
        $pengajuan->loadMissing('user');

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

        // Ketua selalu mengikuti akun (bisa saja sudah diubah admin), anggota tambahan
        // diganti bila dikirim dan dipertahankan bila tidak.
        $pengajuan->nama = $pengajuan->user->name;
        $pengajuan->anggota = $this->anggotaBaru($pengajuan, $data);

        // Batalkan tanda tangan sebelumnya.
        $pengajuan->ditandatangani_at = null;
        $pengajuan->file_surat_pengajuan = null;
        $pengajuan->file_surat_pengajuan_kedua = null;

        $pengajuan->save();

        $this->hapusFiles($fileLama);

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
    public function tandaTangani(PengajuanMagang $pengajuan): PengajuanMagang
    {
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
            $this->hapusFiles($pathBaru);

            throw $e;
        }
    }

    /** Membatalkan tanda tangan: semua surat final dan QR dihapus, preview kembali tanpa QR. */
    public function batalkanTandaTangan(PengajuanMagang $pengajuan): PengajuanMagang
    {
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
        $this->hapusFiles($fileLama);

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
            'file_bukti_spp'         => $pengajuan->file_bukti_spp,
            'file_surat_rekomendasi' => $pengajuan->file_surat_rekomendasi,
        ];

        try {
            // Simpan berkas baru tanpa menghapus berkas lama.
            if ($buktiSpp) {
                $berkasBaru['file_bukti_spp'] = $this->simpanBerkas($buktiSpp, $folder, 'bukti SPP');
            }

            if ($suratRekomendasi) {
                $berkasBaru['file_surat_rekomendasi'] = $this->simpanBerkas($suratRekomendasi, $folder, 'surat rekomendasi');
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
            $this->hapusFiles($berkasBaru);

            throw $e;
        }

        // Hapus berkas lama hanya setelah database berhasil diperbarui.
        foreach ($berkasBaru as $kolom => $path) {
            $pathLama = $berkasLama[$kolom] ?? null;

            if ($pathLama && $pathLama !== $path) {
                $this->hapusFile($pathLama);
            }
        }

        return $pengajuan->refresh();
    }

    /* =========================================================
       ADMIN
    ========================================================= */

    /**
     * Memperbarui data pengajuan magang oleh admin (termasuk data mahasiswa pengaju,
     * anggota kelompok, instansi 1, dan instansi 2).
     *
     * Ketua dan akun mahasiswa adalah satu data: nama / NIM / departemen yang diubah di sini
     * langsung disimpan ke akun (users), dan ketua di surat selalu dibaca dari akun.
     *
     * @param array<string, mixed> $data
     * @throws ValidationException
     */
    public function perbaruiOlehAdmin(PengajuanMagang $pengajuan, array $data): PengajuanMagang
    {
        $pengajuan->loadMissing('user');

        $fileBaru = [];
        $fileLama = [];

        try {
            DB::transaction(function () use ($pengajuan, $data, &$fileBaru, &$fileLama): void {
                $user = $pengajuan->user;

                // 1. Data mahasiswa pengaju (akun)
                if (isset($data['nama'])) {
                    $user->name = $this->formatNama($data['nama']);
                }

                if (isset($data['nim'])) {
                    $nim = strtoupper(trim($data['nim']));
                    $departemen = Departemen::dariNim($nim);

                    if (! $departemen) {
                        throw ValidationException::withMessages([
                            'nim' => 'Departemen untuk NIM ini tidak dikenali.',
                        ]);
                    }

                    $user->nim = $nim;
                    $user->departemen = $departemen['kode'];
                }

                if ($user->isDirty()) {
                    $user->save();
                }

                $pengajuan->nama = $user->name;

                // 2. Instansi pertama
                if (isset($data['instansi'])) {
                    $pengajuan->instansi = $this->formatInstansi($data['instansi']);
                }

                if (isset($data['alamat_instansi'])) {
                    $pengajuan->alamat_instansi = $this->formatInstansi($data['alamat_instansi']);
                }

                foreach (['tanggal_mulai', 'tanggal_selesai'] as $kolom) {
                    if (isset($data[$kolom])) {
                        $pengajuan->{$kolom} = $data[$kolom];
                    }
                }

                // 3. Instansi kedua (kosong berarti dihapus beserta surat finalnya)
                if (array_key_exists('instansi_kedua', $data)) {
                    $this->isiInstansiKedua($pengajuan, $data);

                    if ($pengajuan->jumlahInstansi() === 1 && $pengajuan->file_surat_pengajuan_kedua) {
                        $fileLama[] = $pengajuan->file_surat_pengajuan_kedua;
                        $pengajuan->file_surat_pengajuan_kedua = null;
                    }
                }

                // 4. Anggota: ketua dibangun ulang dari akun, jadi tidak ada data ketua lama yang tersisa.
                $pengajuan->anggota = $this->anggotaBaru($pengajuan, $data);

                $pengajuan->save();

                // 5. Surat yang sudah ditandatangani dirender ulang dengan data terbaru.
                if ($pengajuan->sudahDitandatangani()) {
                    foreach ([1 => 'file_surat_pengajuan', 2 => 'file_surat_pengajuan_kedua'] as $urutan => $kolom) {
                        if ($urutan > $pengajuan->jumlahInstansi()) {
                            continue;
                        }

                        if ($pengajuan->{$kolom}) {
                            $fileLama[] = $pengajuan->{$kolom};
                        }

                        $path = $this->surat->simpan($pengajuan, $urutan);

                        if (! $path) {
                            throw new \RuntimeException('Gagal menyimpan ulang surat pengajuan.');
                        }

                        $fileBaru[] = $path;
                        $pengajuan->{$kolom} = $path;
                    }

                    $pengajuan->save();
                }
            });
        } catch (Throwable $e) {
            // Transaksi dibatalkan: buang surat baru yang sempat dibuat.
            $this->hapusFiles($fileBaru);

            throw $e;
        }

        // Surat lama dihapus setelah database berhasil disimpan
        // (path yang dipakai ulang oleh surat baru tidak ikut dihapus).
        $this->hapusFiles(array_diff($fileLama, $fileBaru));

        return $pengajuan->refresh();
    }

    /* =========================================================
       HELPER
    ========================================================= */

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
     * Daftar anggota yang disimpan: anggota tambahan dari $data bila dikirim,
     * selain itu anggota tambahan yang sudah ada. Ketua selalu disusun ulang dari akun.
     */
    private function anggotaBaru(PengajuanMagang $pengajuan, array $data): array
    {
        $tambahan = array_key_exists('anggota', $data)
            ? ($data['anggota'] ?? [])
            : $pengajuan->anggotaTambahan();

        return $this->susunAnggota($pengajuan->user, $tambahan);
    }

    /**
     * Menyusun daftar kelompok: ketua (dari akun) selalu di urutan pertama, lalu anggota tambahan.
     * Entri yang NIM-nya sama dengan ketua, kembar, atau berasal dari snapshot ketua lama
     * (punya user_id) dilewati begitu saja. Total dibatasi config('magang.maks_anggota').
     */
    private function susunAnggota(User $user, ?array $anggotaTambahan): array
    {
        $nimKetua = strtoupper(trim((string) $user->nim));

        $anggota = [
            [
                'user_id'    => $user->id,
                'nama'       => $user->name,
                'nim'        => $user->nim,
                'departemen' => $user->departemen,
            ],
        ];

        $terpakai = [$nimKetua => true];

        foreach ($anggotaTambahan ?? [] as $member) {
            $nim = strtoupper(trim((string) ($member['nim'] ?? '')));

            if ($nim === '' || ! empty($member['user_id']) || isset($terpakai[$nim])) {
                continue;
            }

            // Ambil departemen otomatis berdasarkan NIM
            $departemen = Departemen::dariNim($nim);

            if (! $departemen) {
                throw ValidationException::withMessages([
                    'anggota' => "NIM {$nim} memiliki kode departemen yang tidak dikenal.",
                ]);
            }

            $terpakai[$nim] = true;

            $anggota[] = [
                'user_id'    => null,
                'nama'       => $this->formatNama((string) ($member['nama'] ?? '')),
                'nim'        => $nim,
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
            "/(^|[\s-])([\p{L}])/u",
            fn ($matches) => $matches[1] . mb_strtoupper($matches[2]),
            $value
        );
    }

    private function simpanBerkas(UploadedFile $file, string $folder, string $nama): string
    {
        $path = $file->store($folder, 'local');

        if (! $path) {
            throw new \RuntimeException("Gagal menyimpan {$nama}.");
        }

        return $path;
    }

    private function pastikanBisaDiedit(PengajuanMagang $pengajuan): void
    {
        if (! $pengajuan->bisaDiedit()) {
            throw ValidationException::withMessages([
                'status' => 'Pengajuan sudah dikirim dan tidak bisa diubah.',
            ]);
        }
    }

    /** @param iterable<int|string, ?string> $paths */
    private function hapusFiles(iterable $paths): void
    {
        foreach ($paths as $path) {
            $this->hapusFile($path);
        }
    }

    private function hapusFile(?string $path): void
    {
        if ($path) {
            Storage::disk('local')->delete($path);
        }
    }
}