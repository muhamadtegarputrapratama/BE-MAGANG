<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StatusPengajuan;
use App\Http\Controllers\Controller;
use App\Http\Requests\UbahStatusPengajuanRequest;
use App\Http\Resources\PengajuanMagangAdminDetailResource;
use App\Http\Resources\PengajuanMagangAdminResource;
use App\Models\PengajuanMagang;
use App\Services\VerifikasiPengajuanService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;

class PengajuanMagangController extends Controller
{
    public function __construct(private VerifikasiPengajuanService $verifikasi)
    {
    }

    /** Halaman tabel: semua pengajuan yang sudah dikirim (tanpa draft). */
    public function index(Request $request)
    {
        $filter = $request->validate([
            'status'   => ['nullable', Rule::in(['menunggu_verifikasi', 'setuju', 'revisi'])],
            'q'        => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $daftar = PengajuanMagang::with('user')
            ->where('status', '!=', StatusPengajuan::Draft->value)
            ->when($filter['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filter['q'] ?? null, function ($query, $kata) {
                $query->where(function ($query) use ($kata) {
                    $query->where('instansi', 'like', "%{$kata}%")
                        // SESUAIKAN nama kolom users (name, nim)
                        ->orWhereHas('user', fn ($user) => $user
                            ->where('name', 'like', "%{$kata}%")
                            ->orWhere('nim', 'like', "%{$kata}%"));
                });
            })
            ->orderByDesc('dikirim_at')
            ->paginate($filter['per_page'] ?? 15)
            ->withQueryString();

        return PengajuanMagangAdminResource::collection($daftar);
    }

    /** Tombol "Lihat Detail": data lengkap dan daftar file. */
    public function show(PengajuanMagang $pengajuan)
    {
        // draft belum dikirim, jadi admin tidak boleh melihatnya
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

    public function berkas(PengajuanMagang $pengajuan, string $jenis, string $nama)
{
    $path = match ($jenis) {
        'spp'         => $pengajuan->file_bukti_spp,
        'rekomendasi' => $pengajuan->file_surat_rekomendasi,
        default       => null,
    };

    abort_unless($path && Storage::disk('local')->exists($path), 404);

    return Storage::disk('local')->response($path);
}

}
