<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PengajuanMagang;
use App\Services\SuratWhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SuratWhatsAppController extends Controller
{
    public function __construct(private SuratWhatsAppService $layanan) {}

    /** Membuat link unduh sementara dan URL WhatsApp untuk surat magang instansi ke-$urutan. */
    public function buat(Request $request, PengajuanMagang $pengajuan, int $urutan): JsonResponse
    {
        $hasil = $this->layanan->buat($pengajuan, $urutan);

        Log::info('Link surat magang WhatsApp dibuat', [
            'pengajuan_id'   => $pengajuan->id,
            'urutan'         => $urutan,
            'admin_id'       => $request->user()->id,
            'berlaku_sampai' => $hasil['berlaku_sampai'],
        ]);

        return response()->json([
            'message' => 'Link WhatsApp berhasil dibuat. Pesan belum terkirim, kirim secara manual dari WhatsApp.',
            'data'    => $hasil,
        ]);
    }
}
