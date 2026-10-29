<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StatusPengajuan;
use App\Http\Controllers\Controller;
use App\Http\Requests\UbahStatusPengajuanRequest;
use App\Http\Resources\PengajuanMagangAdminDetailResource;
use App\Http\Resources\PengajuanMagangAdminResource;
use App\Models\PengajuanMagang;
use App\Models\User;
use App\Services\VerifikasiPengajuanService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Support\Departemen;

class PengajuanMagangController extends Controller
{
    public function __construct(private VerifikasiPengajuanService $verifikasi) {}

    /**
     * Halaman tabel: pengajuan yang sudah dikirim (tanpa draft), 20 per halaman.
     * Urutan: nomor surat naik (1, 2, 3, ...), yang belum bernomor di bawah.
     */
    public function index(Request $request)
    {
        $filter = $request->validate([
            'status'     => ['nullable', Rule::in(['menunggu_verifikasi', 'setuju', 'revisi'])],
            'q'          => ['nullable', 'string', 'max:100'],
            'departemen' => ['nullable', 'string', 'max:100'],
            'per_page'   => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        $daftar = $this->urutkanNomorSurat($this->kueri($filter)->with('user'))
            ->paginate($filter['per_page'] ?? 20)
            ->withQueryString();

        return PengajuanMagangAdminResource::collection($daftar);
    }

    /**
     * Jumlah per status (mengikuti pencarian dan departemen yang aktif)
     * dan daftar departemen untuk dropdown.
     */
    public function ringkasan(Request $request)
    {
        $filter = $request->validate([
            'q'          => ['nullable', 'string', 'max:100'],
            'departemen' => ['nullable', 'string', 'max:100'],
        ]);

        $hitung = $this->kueri($filter, denganStatus: false)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        // SESUAIKAN nama kolom departemen di tabel users
        $departemen = User::whereIn(
            'id',
            PengajuanMagang::where('status', '!=', StatusPengajuan::Draft->value)->select('user_id')
        )
            ->whereNotNull('departemen')
            ->distinct()
            ->pluck('departemen')
            ->map(fn($kode) => ['kode' => $kode, 'nama' => Departemen::nama($kode)])
            ->sortBy('nama')
            ->values();
            
        return response()->json([
            'data' => [
                'menunggu_verifikasi' => (int) ($hitung['menunggu_verifikasi'] ?? 0),
                'setuju'              => (int) ($hitung['setuju'] ?? 0),
                'revisi'              => (int) ($hitung['revisi'] ?? 0),
                'departemen'          => $departemen,
            ],
        ]);
    }

    /** Tombol "Detail": data lengkap dan daftar berkas. */
    public function show(PengajuanMagang $pengajuan)
    {
        abort_if($pengajuan->status === StatusPengajuan::Draft, 404);

        return new PengajuanMagangAdminDetailResource($pengajuan->load('user'));
    }

    /** Admin memutuskan: setuju atau revisi (catatan wajib saat revisi). */
    public function ubahStatus(UbahStatusPengajuanRequest $request, PengajuanMagang $pengajuan)
    {
        abort_if($pengajuan->status === StatusPengajuan::Draft, 404);

        if ($request->validated('status') === 'setuju') {
            $pengajuan = $this->verifikasi->setujui($pengajuan, $request->user());
            $pesan = 'Pengajuan disetujui dan surat pengantar magang telah dibuat.';
        } else {
            $pengajuan = $this->verifikasi->mintaRevisi(
                $pengajuan,
                $request->user(),
                $request->validated('catatan'),
            );
            $pesan = 'Pengajuan dikembalikan ke mahasiswa untuk direvisi.';
        }

        return (new PengajuanMagangAdminDetailResource($pengajuan->load('user')))
            ->additional(['message' => $pesan]);
    }

    /**
     * Urutkan berdasarkan angka di depan nomor surat ("8/IT3.F4/PK.01.06/2026" -> 8).
     * Dibaca sebagai angka, bukan teks, supaya 10 tidak muncul sebelum 2.
     * Pengajuan tanpa nomor surat ditaruh paling bawah, urut berdasarkan id
     * (sama dengan urutan di frontend).
     */
    private function urutkanNomorSurat($query)
    {
        $angka = match (DB::connection()->getDriverName()) {
            'pgsql'  => "CAST(split_part(nomor_surat, '/', 1) AS INTEGER)",
            'sqlite' => "CAST(substr(nomor_surat, 1, instr(nomor_surat, '/') - 1) AS INTEGER)",
            default  => "CAST(SUBSTRING_INDEX(nomor_surat, '/', 1) AS UNSIGNED)", // MySQL / MariaDB
        };

        return $query
            ->orderByRaw("CASE WHEN nomor_surat IS NULL OR nomor_surat = '' THEN 1 ELSE 0 END")
            ->orderByRaw("{$angka} ASC")
            ->orderBy('id');
    }

    /** Kueri dasar yang dipakai daftar dan ringkasan, supaya hasilnya selalu sejalan. */
    private function kueri(array $filter, bool $denganStatus = true)
    {
        return PengajuanMagang::query()
            ->where('status', '!=', StatusPengajuan::Draft->value)
            ->when(
                $denganStatus ? ($filter['status'] ?? null) : null,
                fn($query, $status) => $query->where('status', $status)
            )
            ->when(
                $filter['departemen'] ?? null,
                // SESUAIKAN nama kolom departemen di tabel users
                fn($query, $dep) => $query->whereHas('user', fn($user) => $user->where('departemen', $dep))
            )
            ->when($filter['q'] ?? null, function ($query, $kata) {
                $query->where(function ($query) use ($kata) {
                    $query->where('instansi', 'like', "%{$kata}%")
                        ->orWhere('nomor_surat', 'like', "%{$kata}%")
                        // SESUAIKAN nama kolom users (name, nim)
                        ->orWhereHas('user', fn($user) => $user
                            ->where('name', 'like', "%{$kata}%")
                            ->orWhere('nim', 'like', "%{$kata}%"));
                });
            });
    }
}
