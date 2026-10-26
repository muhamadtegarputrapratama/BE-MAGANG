<?php

namespace App\Http\Controllers\Mahasiswa;

use App\Http\Controllers\Controller;
use App\Http\Requests\KirimPengajuanRequest;
use App\Http\Requests\StorePengajuanMagangRequest;
use App\Http\Resources\PengajuanMagangResource;
use App\Models\PengajuanMagang;
use App\Services\PengajuanMagangService;
use Illuminate\Http\Request;

class PengajuanMagangController extends Controller
{
    public function __construct(private PengajuanMagangService $service) {}

    public function index(Request $request)
    {
        $daftar = PengajuanMagang::where('user_id', $request->user()->id)
            ->latest()
            ->get();

        return PengajuanMagangResource::collection($daftar);
    }

    public function store(StorePengajuanMagangRequest $request)
    {
        $pengajuan = $this->service->buatDraft($request->user(), $request->validated());

        return $this->respons($pengajuan, 'Data pengajuan disimpan sebagai draft.', 201);
    }

    public function show(Request $request, PengajuanMagang $pengajuan)
    {
        $this->pastikanPemilik($request, $pengajuan);

        return new PengajuanMagangResource($pengajuan);
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
        abort_unless($pengajuan->user_id === $request->user()->id, 403);
    }

    private function respons(PengajuanMagang $pengajuan, string $pesan, int $status = 200)
    {
        return (new PengajuanMagangResource($pengajuan))
            ->additional(['message' => $pesan])
            ->response()
            ->setStatusCode($status);
    }
}
