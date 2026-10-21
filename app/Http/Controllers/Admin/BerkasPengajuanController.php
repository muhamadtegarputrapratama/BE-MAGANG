<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StatusPengajuan;
use App\Http\Controllers\Controller;
use App\Models\PengajuanMagang;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BerkasPengajuanController extends Controller
{
    /** jenis di URL => kolom di tabel */
    private const BERKAS = [
        'surat-pengajuan'   => 'file_surat_pengajuan',
        'bukti-spp'         => 'file_bukti_spp',
        'surat-rekomendasi' => 'file_surat_rekomendasi',
        'surat-magang'      => 'file_surat_magang',
    ];

    public function tampil(Request $request, PengajuanMagang $pengajuan, string $jenis)
    {
        abort_unless(isset(self::BERKAS[$jenis]), 404);
        abort_if($pengajuan->status === StatusPengajuan::Draft, 404);

        $path = $pengajuan->{self::BERKAS[$jenis]};

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('local');

        abort_if(! $path || ! $disk->exists($path), 404);

        $ekstensi = pathinfo($path, PATHINFO_EXTENSION);
        $nama     = "{$jenis}-{$pengajuan->id}.{$ekstensi}";

        return $request->query('mode') === 'download'
            ? $disk->download($path, $nama)
            : $disk->response($path, $nama);   // tampil langsung di browser (inline)
    }
}