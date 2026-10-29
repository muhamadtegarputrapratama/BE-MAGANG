<?php

namespace App\Http\Controllers\Mahasiswa;

use App\Enums\StatusPengajuan;
use App\Http\Controllers\Controller;
use App\Http\Requests\KirimPengajuanRequest;
use App\Http\Requests\StorePengajuanMagangRequest;
use App\Http\Resources\PengajuanMagangMahasiswaResource;
use App\Models\PengajuanMagang;
use App\Services\PengajuanMagangService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PengajuanMagangController extends Controller
{
    public function __construct(private PengajuanMagangService $service) {}

    public function index(Request $request)
    {
        $daftar = PengajuanMagang::where('user_id', $request->user()->id)
            ->latest()
            ->get();

        return PengajuanMagangMahasiswaResource::collection($daftar);
    }

    public function store(StorePengajuanMagangRequest $request)
    {
        // Satu pengajuan berjalan per mahasiswa: yang perlu revisi diperbaiki, bukan dibuat ulang.
        $berjalan = PengajuanMagang::where('user_id', $request->user()->id)
            ->whereIn('status', [
                StatusPengajuan::MenungguVerifikasi->value,
                StatusPengajuan::Revisi->value,
            ])
            ->exists();

        if ($berjalan) {
            throw ValidationException::withMessages([
                'pengajuan' => 'Kamu masih memiliki pengajuan yang sedang diproses atau perlu direvisi. '
                    . 'Perbaiki pengajuan tersebut atau tunggu hasil verifikasinya.',
            ]);
        }

        $pengajuan = $this->service->buatDraft($request->user(), $request->validated());

        return $this->respons($pengajuan, 'Data pengajuan disimpan sebagai draft.', 201);
    }

    public function show(Request $request, PengajuanMagang $pengajuan)
    {
        $this->pastikanPemilik($request, $pengajuan);

        return new PengajuanMagangMahasiswaResource($pengajuan);
    }

    public function update(StorePengajuanMagangRequest $request, PengajuanMagang $pengajuan)
    {
        $this->pastikanPemilik($request, $pengajuan);

        $pengajuan = $this->service->perbarui($pengajuan, $request->validated());

        return $this->respons($pengajuan, 'Data pengajuan diperbarui. Silakan tanda tangan ulang.');
    }

    public function preview(Request $request, PengajuanMagang $pengajuan)
    {
        $this->pastikanPemilik($request, $pengajuan);

        return response($this->service->previewSurat($pengajuan), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="surat-pengajuan.pdf"',
        ]);
    }

    /** Menampilkan atau mengunduh Surat Pengantar Magang yang sudah disetujui admin. */
    public function suratMagang(Request $request, PengajuanMagang $pengajuan)
    {
        $this->pastikanPemilik($request, $pengajuan);

        abort_unless(
            $pengajuan->status === StatusPengajuan::Setuju && $pengajuan->file_surat_magang,
            404,
            'Surat pengantar magang belum tersedia atau pengajuan belum disetujui.'
        );

        $disk = Storage::disk('local');
        abort_unless($disk->exists($pengajuan->file_surat_magang), 404, 'File surat pengantar magang tidak ditemukan.');

        $mode = $request->query('mode', 'preview');
        $namaFile = "surat-pengantar-magang-{$pengajuan->id}.pdf";

        return $mode === 'download'
            ? $disk->download($pengajuan->file_surat_magang, $namaFile)
            : $disk->response($pengajuan->file_surat_magang, $namaFile);
    }

    public function tandaTangan(Request $request, PengajuanMagang $pengajuan)
    {
        $this->pastikanPemilik($request, $pengajuan);

        $pengajuan = $this->service->tandaTangani($pengajuan);

        return $this->respons($pengajuan, 'Surat pengajuan berhasil ditandatangani.');
    }

    public function batalTandaTangan(Request $request, PengajuanMagang $pengajuan)
    {
        $this->pastikanPemilik($request, $pengajuan);

        $pengajuan = $this->service->batalkanTandaTangan($pengajuan);

        return $this->respons($pengajuan, 'Tanda tangan dibatalkan.');
    }

    public function kirim(KirimPengajuanRequest $request, PengajuanMagang $pengajuan)
    {
        $this->pastikanPemilik($request, $pengajuan);

        $pengajuan = $this->service->kirim(
            $pengajuan,
            $request->file('bukti_spp'),
            $request->file('surat_rekomendasi'),
        );

        return $this->respons($pengajuan, 'Pengajuan berhasil dikirim ke admin.');
    }

    private function pastikanPemilik(Request $request, PengajuanMagang $pengajuan): void
    {
        abort_unless(
            (int) $pengajuan->user_id === (int) $request->user()->id,
            403
        );
    }

    private function respons(PengajuanMagang $pengajuan, string $pesan, int $status = 200)
    {
        return (new PengajuanMagangMahasiswaResource($pengajuan))
            ->additional(['message' => $pesan])
            ->response()
            ->setStatusCode($status);
    }
}