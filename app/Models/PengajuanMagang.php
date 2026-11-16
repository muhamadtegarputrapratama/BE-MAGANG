<?php

namespace App\Models;

use App\Enums\StatusPengajuan;
use App\Support\Departemen;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PengajuanMagang extends Model
{
    protected $table = 'pengajuan_magang';

    // Nomor surat dan path file sengaja tidak di sini: diisi sistem, bukan lewat mass assignment.
    protected $fillable = [
        'nama',
        'anggota',
        'instansi',
        'alamat_instansi',
        'tanggal_mulai',
        'tanggal_selesai',
        'instansi_kedua',
        'alamat_instansi_kedua',
        'tanggal_mulai_kedua',
        'tanggal_selesai_kedua',
    ];

    protected function casts(): array
    {
        return [
            'status' => StatusPengajuan::class,
            'anggota' => 'array',
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
            'tanggal_mulai_kedua' => 'date',
            'tanggal_selesai_kedua' => 'date',
            'diverifikasi_at' => 'datetime',
            'ditandatangani_at' => 'datetime',
            'dikirim_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function diverifikasiOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diverifikasi_oleh');
    }

    public function sudahDitandatangani(): bool
    {
        return $this->ditandatangani_at !== null;
    }

    /** Mahasiswa hanya boleh mengubah saat draft atau diminta revisi. */
    public function bisaDiedit(): bool
    {
        return in_array($this->status, [StatusPengajuan::Draft, StatusPengajuan::Revisi], true);
    }

    /* =========================================================
       INSTANSI (maksimal 2) -> satu instansi = satu surat
    ========================================================= */

    /** 1 bila hanya instansi pertama, 2 bila instansi kedua juga diisi. */
    public function jumlahInstansi(): int
    {
        return filled($this->instansi_kedua) ? 2 : 1;
    }

    /** Instansi tujuan berurutan; 'urutan' 1 atau 2 menentukan surat yang mana. */
    public function daftarInstansi(): array
    {
        $daftar = [[
            'urutan'  => 1,
            'nama'    => $this->instansi,
            'alamat'  => $this->alamat_instansi,
            'mulai'   => $this->tanggal_mulai,
            'selesai' => $this->tanggal_selesai,
            'nomor'   => $this->nomor_surat,
        ]];

        if ($this->jumlahInstansi() === 2) {
            $daftar[] = [
                'urutan'  => 2,
                'nama'    => $this->instansi_kedua,
                'alamat'  => $this->alamat_instansi_kedua,
                'mulai'   => $this->tanggal_mulai_kedua,
                'selesai' => $this->tanggal_selesai_kedua,
                'nomor'   => $this->nomor_surat_kedua,
            ];
        }

        return $daftar;
    }

    /** Path PDF form pengajuan (yang ditandatangani mahasiswa) untuk instansi ke-$urutan. */
    public function fileSuratPengajuan(int $urutan = 1): ?string
    {
        return match ($urutan) {
            1       => $this->file_surat_pengajuan,
            2       => $this->file_surat_pengajuan_kedua,
            default => null,
        };
    }

    /** Path PDF surat pengantar magang untuk instansi ke-$urutan. */
    public function fileSuratMagang(int $urutan = 1): ?string
    {
        return match ($urutan) {
            1       => $this->file_surat_magang,
            2       => $this->file_surat_magang_kedua,
            default => null,
        };
    }

    /* =========================================================
       MAHASISWA: ketua selalu dari akun (users), anggota dari kolom "anggota"
    ========================================================= */

    /**
     * Anggota kelompok SELAIN ketua.
     *
     * Ketua tidak diambil dari kolom "anggota", melainkan selalu dari akun (users),
     * supaya nama / NIM ketua tidak pernah usang atau dobel setelah diubah admin.
     * Entri ketua di kolom "anggota" (snapshot lama) dikenali dari "user_id" yang terisi
     * atau NIM yang sama dengan ketua, lalu dilewati. NIM kembar juga dilewati.
     *
     * @return array<int, array{nama: string, nim: string, departemen: ?string}>
     */
    public function anggotaTambahan(): array
    {
        $this->loadMissing('user');

        $terpakai = [strtoupper(trim((string) $this->user?->nim)) => true];
        $hasil = [];

        foreach ($this->anggota ?? [] as $a) {
            $nim = strtoupper(trim((string) ($a['nim'] ?? '')));

            if ($nim === '' || ! empty($a['user_id']) || isset($terpakai[$nim])) {
                continue;
            }

            $terpakai[$nim] = true;

            $hasil[] = [
                'nama'       => (string) ($a['nama'] ?? ''),
                'nim'        => $nim,
                'departemen' => $a['departemen'] ?? null, // kode departemen
            ];
        }

        return $hasil;
    }

    /** Jumlah mahasiswa dalam pengajuan, termasuk ketua. */
    public function jumlahMahasiswa(): int
    {
        return 1 + count($this->anggotaTambahan());
    }

    /**
     * Daftar mahasiswa yang dicetak di surat: pengaju (ketua) selalu nomor 1 dengan data
     * terbaru dari akun, lalu anggota tambahan. Departemen diubah dari kode menjadi nama.
     * Total maksimal config('magang.maks_anggota').
     */
    public function daftarAnggotaCetak(): array
    {
        $this->loadMissing('user');
        $user = $this->user;

        $daftar = [[
            'nama'       => $user->name,
            'nim'        => $user->nim,
            'departemen' => Departemen::nama($user->departemen),
        ]];

        foreach ($this->anggotaTambahan() as $a) {
            $daftar[] = [
                'nama'       => $a['nama'],
                'nim'        => $a['nim'],
                'departemen' => Departemen::nama($a['departemen']),
            ];
        }

        return array_slice($daftar, 0, (int) config('magang.maks_anggota', 10));
    }
}